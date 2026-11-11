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

namespace App\Import\Pancake;

use InvalidArgumentException;

enum Entity: string
{
    case Clients = 'clients';
    case Invoices = 'invoices';
    case RecurringInvoices = 'recurring_invoices';
    case Payments = 'payments';

    /** @return array<int, self> */
    public static function importOrder(): array
    {
        return [
            self::Clients,
            self::Invoices,
            self::RecurringInvoices,
            self::Payments,
        ];
    }

    /** @return array<int, self> */
    public static function fromOption(?string $entities): array
    {
        if ($entities === null || trim($entities) === '') {
            return self::importOrder();
        }

        $selected = [];

        foreach (explode(',', $entities) as $value) {
            $normalized = str_replace(['-', ' '], '_', strtolower(trim($value)));
            $entity = self::fromAlias($normalized);

            if (! $entity) {
                throw new InvalidArgumentException(sprintf(
                    'Unsupported Pancake entity [%s]. Supported entities: %s.',
                    trim($value),
                    implode(', ', array_map(fn(self $item): string => $item->value, self::importOrder())),
                ));
            }

            $selected[$entity->value] = $entity;
        }

        return array_values(array_filter(
            self::importOrder(),
            fn(self $entity): bool => isset($selected[$entity->value]),
        ));
    }

    public function endpoint(): string
    {
        return $this->value;
    }

    public function destinationLabel(): string
    {
        return match ($this) {
            self::Clients => 'client',
            self::Invoices => 'invoice',
            self::RecurringInvoices => 'recurring invoice',
            self::Payments => 'payment',
        };
    }

    private static function fromAlias(string $value): ?self
    {
        return match ($value) {
            'client' => self::Clients,
            'invoice' => self::Invoices,
            'recurring', 'recurring_invoice' => self::RecurringInvoices,
            'payment' => self::Payments,
            default => self::tryFrom($value),
        };
    }
}
