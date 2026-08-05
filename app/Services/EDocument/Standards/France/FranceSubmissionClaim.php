<?php

namespace App\Services\EDocument\Standards\France;

use App\Models\TransactionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FranceSubmissionClaim
{
    public const TOKEN = 'submission_claim_token';

    public const EXPIRES_AT = 'submission_claim_expires_at';

    private const LEASE_MINUTES = 15;

    /**
     * @param array<int, int> $eventIds
     */
    public function claim(array $eventIds): ?string
    {
        $eventIds = collect($eventIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($eventIds === []) {
            return null;
        }

        return DB::transaction(function () use ($eventIds): ?string {
            $events = TransactionEvent::query()
                ->whereIn('id', $eventIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($events->count() !== count($eventIds)
                || $events->contains(fn (TransactionEvent $event): bool => $this->isActive($event))) {
                return null;
            }

            $token = Str::uuid()->toString();
            $expiresAt = now()->addMinutes(self::LEASE_MINUTES)->toIso8601String();

            foreach ($events as $event) {
                $event->payment_request = [
                    ...($event->payment_request ?? []),
                    self::TOKEN => $token,
                    self::EXPIRES_AT => $expiresAt,
                ];
                $event->save();
            }

            return $token;
        }, attempts: 3);
    }

    public function isActive(TransactionEvent $event): bool
    {
        $token = (string) data_get($event->payment_request, self::TOKEN, '');
        $expiresAt = (string) data_get($event->payment_request, self::EXPIRES_AT, '');

        return $token !== ''
            && $expiresAt !== ''
            && CarbonImmutable::parse($expiresAt)->isFuture();
    }

    public function isOwnedBy(TransactionEvent $event, string $token): bool
    {
        return $token !== ''
            && hash_equals($token, (string) data_get($event->payment_request, self::TOKEN, ''));
    }

    public function hasActiveClaimForInvoice(int $invoiceId, bool $lockForUpdate = false): bool
    {
        $query = TransactionEvent::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('event_id', array_merge(
                TransactionEvent::FR_REPORTING_EVENTS,
                TransactionEvent::FR_PAYMENT_NOTIFICATION_EVENTS,
            ))
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->contains(fn (TransactionEvent $event): bool => $this->isActive($event));
    }

    /**
     * @param array<int, int> $eventIds
     */
    public function release(array $eventIds, string $token): void
    {
        DB::transaction(function () use ($eventIds, $token): void {
            TransactionEvent::query()
                ->whereIn('id', $eventIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (TransactionEvent $event) use ($token): void {
                    if (! $this->isOwnedBy($event, $token)) {
                        return;
                    }

                    $request = $event->payment_request ?? [];
                    unset($request[self::TOKEN], $request[self::EXPIRES_AT]);
                    $event->payment_request = $request;
                    $event->save();
                });
        }, attempts: 3);
    }
}
