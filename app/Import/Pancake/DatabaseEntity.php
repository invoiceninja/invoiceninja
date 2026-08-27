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
use Illuminate\Support\Str;

enum DatabaseEntity: string
{
    case Company = 'company';
    case TaxRates = 'tax_rates';
    case Clients = 'clients';
    case Products = 'products';
    case Vendors = 'vendors';
    case ExpenseCategories = 'expense_categories';
    case TaskStatuses = 'task_statuses';
    case Projects = 'projects';
    case Tasks = 'tasks';
    case Invoices = 'invoices';
    case Quotes = 'quotes';
    case Credits = 'credits';
    case RecurringInvoices = 'recurring_invoices';
    case Payments = 'payments';
    case Expenses = 'expenses';
    case Notes = 'notes';
    case Documents = 'documents';

    /** @return array<int, self> */
    public static function importOrder(): array
    {
        return [
            self::Company,
            self::TaxRates,
            self::Clients,
            self::Products,
            self::Vendors,
            self::ExpenseCategories,
            self::TaskStatuses,
            self::Projects,
            self::Tasks,
            self::Invoices,
            self::Quotes,
            self::Credits,
            self::RecurringInvoices,
            self::Payments,
            self::Expenses,
            self::Notes,
            self::Documents,
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
                    'Unsupported Pancake database entity [%s]. Supported entities: %s.',
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
        return match ($this) {
            self::Notes => 'activities/notes',
            default => $this->value,
        };
    }

    public function label(): string
    {
        return Str::singular(str_replace('_', ' ', $this->value));
    }

    /** @return array<int, string> */
    public function requiredTables(): array
    {
        return match ($this) {
            self::Company => ['business_identities'],
            self::TaxRates => ['taxes'],
            self::Clients => ['clients', 'currencies', 'invoices'],
            self::Products => ['items'],
            self::Vendors => ['project_expenses_suppliers'],
            self::ExpenseCategories => ['project_expenses_categories'],
            self::TaskStatuses => ['project_task_statuses'],
            self::Projects => ['projects', 'clients'],
            self::Tasks => ['project_tasks', 'project_times', 'projects', 'clients'],
            self::Invoices, self::Quotes, self::Credits, self::RecurringInvoices => ['invoices', 'invoice_rows', 'partial_payments', 'clients'],
            self::Payments => ['partial_payments', 'invoices', 'clients'],
            self::Expenses => ['project_expenses', 'projects', 'clients'],
            self::Notes => ['notes', 'comments', 'contact_log'],
            self::Documents => ['files', 'project_files', 'comments', 'invoices', 'project_expenses', 'projects', 'clients'],
        };
    }

    private static function fromAlias(string $value): ?self
    {
        return match ($value) {
            'tax', 'taxes', 'tax_rate' => self::TaxRates,
            'client' => self::Clients,
            'product', 'item', 'items' => self::Products,
            'vendor', 'supplier', 'suppliers' => self::Vendors,
            'expense_category', 'categories' => self::ExpenseCategories,
            'task_status' => self::TaskStatuses,
            'project' => self::Projects,
            'task', 'time', 'times', 'time_entries' => self::Tasks,
            'invoice' => self::Invoices,
            'quote', 'estimate', 'estimates', 'proposal', 'proposals' => self::Quotes,
            'credit', 'credit_note', 'credit_notes' => self::Credits,
            'recurring', 'recurring_invoice' => self::RecurringInvoices,
            'payment' => self::Payments,
            'expense' => self::Expenses,
            'note', 'comments', 'history' => self::Notes,
            'document', 'file', 'files', 'attachments' => self::Documents,
            default => self::tryFrom($value),
        };
    }
}
