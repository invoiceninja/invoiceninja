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

use App\Casts\PurchaseOrderSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * PurchaseOrderSync.
 */
class PurchaseOrderSync implements Castable
{
    public function __construct(
        public string $qb_id = '',
        public array $invitations = [],
        public bool $dn_completed = false,
        public string $dn_document_hashed_id = '',
    ){}
     /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return PurchaseOrderSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            qb_id: $data['qb_id'] ?? '',
            invitations: $data['invitations'] ?? [],
            dn_completed: $data['dn_completed'] ?? false,
            dn_document_hashed_id: $data['dn_document_hashed_id'] ?? '',
        );
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
        $this->invitations = array_filter($this->invitations, function($invitation) use ($invitation_key) {
            return $invitation['invitation_key'] !== $invitation_key;
        });
        // Re-index the array to maintain numeric keys
        $this->invitations = array_values($this->invitations);
    }
}
