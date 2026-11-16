<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use App\Mail\Admin\TerminalDeclinedObject;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\AppLink;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * A card declined on an in-person payment while nobody was watching. Apple's Tap to Pay on iPhone
 * requirement 5.12: a decline the merchant did not see must reach the merchant, so this is an alert about
 * their own transaction, not a subscription, and it never goes to nobody. The user who started it always
 * hears of it while they are in the company; once they are gone, whoever takes every payment failure does,
 * and failing them the account owner.
 *
 * Plain strings and model keys only: never the charge, its decline code or anything about the cardholder.
 */
class TerminalDeclinedMailer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UserNotifies;

    public function __construct(public Invoice $invoice, public Company $company, public int $user_id, public string $amount)
    {
    }

    public function handle(): void
    {
        MultiDB::setDb($this->company->db);

        $locale = App::getLocale();

        try {
            foreach ($this->recipients() as $user) {
                $mail_obj = (new TerminalDeclinedObject(
                    $this->company,
                    (string) $this->invoice->number,
                    (string) $this->invoice->client->present()->name(),
                    $this->amount,
                    AppLink::forRecord('invoices', $this->invoice),
                ))->build();

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer($mail_obj);
                $nmo->company = $this->company;
                $nmo->to_user = $user;
                $nmo->settings = $this->company->settings;

                NinjaMailerJob::dispatch($nmo);
            }
        } finally {
            /* The mail is built in the company's language: whatever runs next gets its own back */
            App::forgetInstance('translator');
            App::setLocale($locale);
        }
    }

    /** @return Collection<int, User> */
    private function recipients(): Collection
    {
        /* In the company, with a user that is neither archived nor deleted: CompanyUser::user() reaches trashed users too */
        $company_users = $this->company->company_users()
            ->withoutTrashed()
            ->whereHas('user', fn ($query) => $query->withoutTrashed())
            ->with('user')
            ->get();

        /* Their own transaction: whatever their notification settings say */
        $starter = $company_users->firstWhere('user_id', $this->user_id);

        if ($starter) {
            return collect([$starter->user]);
        }

        $opted_in = $company_users
            ->filter(fn (CompanyUser $company_user) => in_array('mail', $this->findUserEntityNotificationType($this->invoice, $company_user, $this->fallbackPermissions($company_user))))
            ->map(fn (CompanyUser $company_user) => $company_user->user);

        if ($opted_in->isNotEmpty()) {
            return $opted_in->unique('id')->values();
        }

        /* Nobody takes payment failures: the merchant still has to hear of it */
        $owner = $company_users->firstWhere('is_owner', true)->user ?? $this->company->owner();

        return collect($owner && !$owner->trashed() ? [$owner] : []);
    }

    /* Every payment failure, or their own: the invoice's user and its assignee may take only those for their invoices */
    private function fallbackPermissions(CompanyUser $company_user): array
    {
        $permissions = ['payment_failure_all', 'all_notifications'];

        if (in_array($company_user->user_id, [$this->invoice->user_id, $this->invoice->assigned_user_id])) {
            $permissions[] = 'payment_failure_user';
        }

        return $permissions;
    }
}
