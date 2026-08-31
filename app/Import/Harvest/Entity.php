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

namespace App\Import\Harvest;

use InvalidArgumentException;

enum Entity: string
{
    case Clients = 'clients';
    case Users = 'users';
    case Projects = 'projects';
    case Tasks = 'tasks';
    case TimeEntries = 'time_entries';
    case ExpenseCategories = 'expense_categories';
    case Expenses = 'expenses';
    case Invoices = 'invoices';
    case InvoicePayments = 'invoice_payments';
    case Estimates = 'estimates';

    /** @return array<int, self> */
    public static function importOrder(): array
    {
        return [
            self::Clients,
            self::Users,
            self::Tasks,
            self::ExpenseCategories,
            self::Projects,
            self::TimeEntries,
            self::Expenses,
            self::Invoices,
            self::Estimates,
            self::InvoicePayments,
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
                    'Unsupported Harvest entity [%s]. Supported entities: %s.',
                    trim($value),
                    implode(', ', array_map(fn(self $item): string => $item->value, self::importOrder())),
                ));
            }

            $selected[$entity->value] = $entity;
        }

        $entities = array_values($selected);

        return array_values(array_filter(
            self::importOrder(),
            fn(self $entity): bool => in_array($entity, $entities, true),
        ));
    }

    public function endpoint(): string
    {
        return match ($this) {
            self::Tasks => 'products',
            self::TimeEntries => 'tasks',
            self::InvoicePayments => 'payments',
            self::Estimates => 'quotes',
            default => $this->value,
        };
    }

    public function destinationLabel(): string
    {
        return match ($this) {
            self::Clients => 'client',
            self::Users => 'user',
            self::Projects => 'project',
            self::Tasks => 'product',
            self::TimeEntries => 'task',
            self::ExpenseCategories => 'expense category',
            self::Expenses => 'expense',
            self::Invoices => 'invoice',
            self::InvoicePayments => 'payment',
            self::Estimates => 'quote',
        };
    }

    private static function fromAlias(string $value): ?self
    {
        return match ($value) {
            'client', 'contact', 'contacts' => self::Clients,
            'user', 'person', 'people', 'team' => self::Users,
            'project' => self::Projects,
            'task', 'task_type', 'task_types', 'product', 'products' => self::Tasks,
            'time', 'time_entry', 'timesheet', 'timesheets' => self::TimeEntries,
            'expense_category', 'categories' => self::ExpenseCategories,
            'expense' => self::Expenses,
            'invoice' => self::Invoices,
            'invoice_payment', 'payment', 'payments' => self::InvoicePayments,
            'estimate', 'quote', 'quotes' => self::Estimates,
            default => self::tryFrom($value),
        };
    }

}
