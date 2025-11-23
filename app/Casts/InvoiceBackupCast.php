<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www/elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use App\DataMapper\InvoiceBackup;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class InvoiceBackupCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return new InvoiceBackup();
        }

        $data = json_decode($value, true) ?? [];

        return InvoiceBackup::fromArray($data);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return [$key => null];
        }

        // Ensure we're dealing with our object type
        if (! $value instanceof InvoiceBackup) {
            // Attempt to create the instance from legacy data before throwing
            try {
                if (is_object($value)) {
                    $value = InvoiceBackup::fromArray((array) $value);
                }
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Value must be an InvoiceBackup instance. Legacy data conversion failed: ' . $e->getMessage());
            }
        }

        return [
            $key => json_encode([
                'guid' => $value->guid,
                'cancellation' => $value->cancellation ? [
                    'adjustment' => $value->cancellation->adjustment,
                    'status_id' => $value->cancellation->status_id,
                ] : [],
                'parent_invoice_id' => $value->parent_invoice_id,
                'parent_invoice_number' => $value->parent_invoice_number,
                'document_type' => $value->document_type,
                'child_invoice_ids' => $value->child_invoice_ids->toArray(),
                'redirect' => $value->redirect,
                'adjustable_amount' => $value->adjustable_amount,
                'notes' => $value->notes,
            ])
        ];
    }
}
