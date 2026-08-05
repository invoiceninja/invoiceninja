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

namespace App\DataMapper;

use App\Casts\InvoiceSyncCast;
use App\Enum\InvoiceQbStatus;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * InvoiceSync.
 */
class InvoiceSync implements Castable
{
    public function __construct(
        public string $qb_id = '',
        public array $invitations = [],
        public bool $dn_completed = false,
        public string $dn_document_hashed_id = '',
        public string $qb_status = '',
        public string $qb_sync_token = '',
        public string $qb_status_message = '',
    ) {}

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return InvoiceSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            qb_id: $data['qb_id'] ?? '',
            invitations: $data['invitations'] ?? [],
            dn_completed: $data['dn_completed'] ?? false,
            dn_document_hashed_id: $data['dn_document_hashed_id'] ?? '',
            qb_status: $data['qb_status'] ?? '',
            qb_sync_token: $data['qb_sync_token'] ?? '',
            qb_status_message: $data['qb_status_message'] ?? '',
        );
    }

    public function status(): InvoiceQbStatus
    {
        return InvoiceQbStatus::tryFrom($this->qb_status) ?? InvoiceQbStatus::Syncable;
    }

    public function isLinked(): bool
    {
        return $this->qb_id !== '';
    }

    public function markSynced(string $qb_id, string $sync_token = '', bool $clear_status_message = true): void
    {
        $this->qb_id = $qb_id;
        $this->qb_status = InvoiceQbStatus::Synced->value;
        $this->qb_sync_token = $sync_token;

        if ($clear_status_message) {
            $this->qb_status_message = '';
        }
    }

    public function markSyncable(bool $clear_status_message = true): void
    {
        $this->qb_status = InvoiceQbStatus::Syncable->value;

        if ($clear_status_message) {
            $this->qb_status_message = '';
        }
    }

    public function markLinkable(string $message): void
    {
        $this->qb_status = InvoiceQbStatus::Linkable->value;
        $this->qb_status_message = $message;
    }

    public function markAmountMismatch(string $message): void
    {
        $this->qb_status = InvoiceQbStatus::AmountMismatch->value;
        $this->qb_status_message = $message;
    }

    public function markDataMismatch(string $message): void
    {
        $this->qb_status = InvoiceQbStatus::DataMismatch->value;
        $this->qb_status_message = $message;
    }

    public function markPushFailure(string $message): void
    {
        $this->qb_status_message = $message;
    }

    public function clearStatusMessage(): void
    {
        $this->qb_status_message = '';
    }

    /**
     * Add an invitation to the invitations array
     *
     * @param string $invitation_key The invitation key
     * @param string $dn_id The DocuNinja ID
     * @param string $dn_invitation_id The DocuNinja invitation ID
     * @param string $dn_sig The DocuNinja signature
     */
    public function addInvitation(
        string $invitation_key,
        string $dn_id,
        string $dn_invitation_id,
        string $dn_sig
    ): void {
        $this->invitations[] = [
            'invitation_key' => $invitation_key,
            'dn_id' => $dn_id,
            'dn_invitation_id' => $dn_invitation_id,
            'dn_sig' => $dn_sig,
        ];
    }

    /**
     * Get invitation data by invitation key
     *
     * @param string $invitation_key The invitation key
     * @return array|null The invitation data or null if not found
     */
    public function getInvitation(string $invitation_key): ?array
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation['invitation_key'] === $invitation_key) {
                return $invitation;
            }
        }
        return null;
    }

    /**
     * Remove an invitation by invitation key
     *
     * @param string $invitation_key The invitation key
     */
    public function removeInvitation(string $invitation_key): void
    {
        $this->invitations = array_filter($this->invitations, function ($invitation) use ($invitation_key) {
            return $invitation['invitation_key'] !== $invitation_key;
        });
        // Re-index the array to maintain numeric keys
        $this->invitations = array_values($this->invitations);
    }
}
