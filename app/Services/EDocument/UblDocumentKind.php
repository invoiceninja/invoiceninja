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

namespace App\Services\EDocument;

use App\Models\Credit;
use App\Models\Invoice;

/**
 * PEPPOL UBL document kind for Invoice Ninja entities.
 *
 * Credit notes (381) cover Credit models and negative invoices.
 * Invoices (380) are positive-amount Invoice models.
 */
enum UblDocumentKind
{
    case Invoice;
    case CreditNote;

    public static function fromEntity(Invoice|Credit $entity): self
    {
        if ($entity instanceof Credit) {
            return self::CreditNote;
        }

        if ((float) $entity->amount < 0) {
            return self::CreditNote;
        }

        return self::Invoice;
    }

    public function isCreditNote(): bool
    {
        return $this === self::CreditNote;
    }

    public function typeCode(): int
    {
        return match ($this) {
            self::Invoice => 380,
            self::CreditNote => 381,
        };
    }
}
