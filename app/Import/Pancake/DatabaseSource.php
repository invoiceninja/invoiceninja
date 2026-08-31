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

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class DatabaseSource
{
    private ConnectionInterface $connection;

    private string $prefix = 'pancake_';

    private ?int $business_identity_id = null;

    private ?string $files_root = null;

    private bool $attachments_enabled = true;

    /** @var array<int, int>|null */
    private ?array $client_ids = null;

    /** @var array<int, int>|null */
    private ?array $project_ids = null;

    /** @var array<int, int>|null */
    private ?array $invoice_ids = null;

    /** @var array<int, array{primary: ?string, codes: array<int, string|null>}>|null */
    private ?array $client_currency_variants = null;

    /** @var array<int, string>|null */
    private ?array $currency_codes = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DatabaseTransformer $transformer,
    ) {}

    public function configure(
        string $connection,
        string $prefix,
        ?int $business_identity_id,
        ?string $files_root,
    ): self {
        $this->connection = $this->database->connection($connection);
        $this->prefix = $prefix;
        $this->business_identity_id = $business_identity_id;
        $this->files_root = $files_root ? rtrim($files_root, DIRECTORY_SEPARATOR) : null;
        $this->attachments_enabled = true;
        $this->client_ids = null;
        $this->project_ids = null;
        $this->invoice_ids = null;
        $this->client_currency_variants = null;
        $this->currency_codes = null;
        $this->transformer->useTimezone($this->timezone());

        return $this;
    }

    /** @param array<int, DatabaseEntity> $entities */
    public function validate(array $entities): void
    {
        $schema = $this->connection->getSchemaBuilder();
        $missing = [];

        foreach ($entities as $entity) {
            foreach ($entity->requiredTables() as $table) {
                if (! $schema->hasTable($this->table($table))) {
                    $missing[$table] = $this->table($table);
                }
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('The Pancake database is missing required tables: ' . implode(', ', $missing));
        }
    }

    public function resolveBusinessIdentity(?int $requested): ?int
    {
        if (! $this->connection->getSchemaBuilder()->hasTable($this->table('clients'))) {
            return $requested;
        }

        $referenced = $this->query('clients')
            ->whereNotNull('business_identity')
            ->where('business_identity', '>', 0)
            ->distinct()
            ->orderBy('business_identity')
            ->pluck('business_identity')
            ->map(fn(mixed $id): int => (int) $id)
            ->all();

        if ($requested !== null) {
            if ($requested === 0) {
                return 0;
            }

            if ($this->hasTable('business_identities')
                && ! $this->query('business_identities')->where('id', $requested)->exists()) {
                throw new InvalidArgumentException("Pancake business identity [{$requested}] does not exist.");
            }

            if ($referenced !== [] && ! in_array($requested, $referenced, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Pancake business identity [%d] is not referenced by any client. Available identities: %s.',
                    $requested,
                    implode(', ', $referenced),
                ));
            }

            return $requested;
        }

        if (count($referenced) > 1) {
            throw new InvalidArgumentException(
                'The Pancake database contains clients for multiple business identities ('
                . implode(', ', $referenced)
                . '). Run the import once per identity/API token using --business-identity; use 0 for unassigned clients.',
            );
        }

        if (count($referenced) === 1) {
            return $referenced[0];
        }

        if ($this->connection->getSchemaBuilder()->hasTable($this->table('business_identities'))) {
            $identities = $this->query('business_identities')->orderBy('id')->pluck('id');

            if ($identities->count() === 1) {
                return (int) $identities->first();
            }
        }

        return null;
    }

    public function useBusinessIdentity(?int $business_identity_id): self
    {
        $this->business_identity_id = $business_identity_id;
        $this->client_ids = null;
        $this->project_ids = null;
        $this->invoice_ids = null;
        $this->client_currency_variants = null;

        return $this;
    }

    public function withoutAttachments(): self
    {
        $this->attachments_enabled = false;

        return $this;
    }

    /** @return iterable<int, array<string, mixed>> */
    public function records(DatabaseEntity $entity): iterable
    {
        return match ($entity) {
            DatabaseEntity::Company => $this->companyRecords(),
            DatabaseEntity::TaxRates => $this->taxRateRecords(),
            DatabaseEntity::Clients => $this->clientRecords(),
            DatabaseEntity::Products => $this->productRecords(),
            DatabaseEntity::Vendors => $this->vendorRecords(),
            DatabaseEntity::ExpenseCategories => $this->expenseCategoryRecords(),
            DatabaseEntity::TaskStatuses => $this->taskStatusRecords(),
            DatabaseEntity::Projects => $this->projectRecords(),
            DatabaseEntity::Tasks => $this->taskRecords(),
            DatabaseEntity::Invoices, DatabaseEntity::Quotes, DatabaseEntity::Credits, DatabaseEntity::RecurringInvoices => $this->documentRecords($entity),
            DatabaseEntity::Payments => $this->paymentRecords(),
            DatabaseEntity::Expenses => $this->expenseRecords(),
            DatabaseEntity::Notes => $this->noteRecords(),
            DatabaseEntity::Documents => $this->uploadRecords(),
        };
    }

    /** @return array<string, int> */
    public function unsupportedCounts(): array
    {
        $mapped_tables = [
            'business_identities',
            'clients',
            'clients_meta',
            'clients_taxes',
            'comments',
            'contact_log',
            'currencies',
            'files',
            'invoice_rows',
            'invoice_rows_taxes',
            'invoices',
            'items',
            'items_taxes',
            'notes',
            'partial_payments',
            'project_expenses',
            'project_expenses_categories',
            'project_expenses_suppliers',
            'project_files',
            'project_task_statuses',
            'project_tasks',
            'project_timers',
            'project_times',
            'project_updates',
            'project_milestones',
            'projects',
            'proposal_sections',
            'proposals',
            'settings',
            'taxes',
            'client_ticket_support_rate_matrix',
            'ticket_history',
            'ticket_posts',
            'ticket_priorities',
            'ticket_statuses',
            'tickets',
        ];
        $counts = [];

        foreach ($this->connection->getSchemaBuilder()->getTableListing() as $table_name) {
            $table_name = (string) $table_name;
            $short_name = str_contains($table_name, '.') ? substr($table_name, strrpos($table_name, '.') + 1) : $table_name;

            if (! str_starts_with($short_name, $this->prefix)) {
                continue;
            }

            $logical_name = substr($short_name, strlen($this->prefix));

            if (in_array($logical_name, $mapped_tables, true)) {
                continue;
            }

            $count = $this->connection->table($short_name)->count();

            if ($count > 0) {
                $counts[$short_name] = $count;
            }
        }

        return $counts;
    }

    public function fingerprint(string $api_url, string $api_token): string
    {
        $config = $this->connection->getConfig();

        return hash('sha256', implode('|', [
            (string) ($config['host'] ?? ''),
            (string) ($config['port'] ?? ''),
            (string) ($config['database'] ?? ''),
            $this->prefix,
            (string) $this->business_identity_id,
            rtrim($api_url, '/'),
            hash('sha256', $api_token),
        ]));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function companyRecords(): iterable
    {
        if ($this->business_identity_id === null) {
            return [];
        }

        $identity = $this->query('business_identities')->find($this->business_identity_id);

        if (! is_object($identity)) {
            return [];
        }

        $record = $this->transformer->company($identity);

        if ($this->attachments_enabled && trim((string) $identity->logo_filename) !== '') {
            $record['upload'] = [
                'path' => $this->resolveFile((string) $identity->logo_filename),
                'filename' => basename((string) $identity->logo_filename),
            ];
        }

        return [$record];
    }

    /** @return iterable<int, array<string, mixed>> */
    private function taxRateRecords(): iterable
    {
        return $this->query('taxes')->orderBy('id')->get()->map(
            fn(object $tax): array => $this->transformer->taxRate($tax),
        );
    }

    /** @return iterable<int, array<string, mixed>> */
    private function clientRecords(): iterable
    {
        $client_ids = $this->clientIds();
        $clients = $this->query('clients')->whereIn('id', $client_ids)->orderBy('id')->get();
        $meta = $this->optionalGrouped('clients_meta', 'client_id', $client_ids);
        $client_taxes = $this->optionalGrouped('clients_taxes', 'client_id', $client_ids);
        $taxes = $this->keyed('taxes');
        $variants = $this->clientCurrencyVariants();
        $records = collect();

        foreach ($clients as $client) {
            $client_variants = $variants[(int) $client->id] ?? ['primary' => null, 'codes' => [null]];

            foreach ($client_variants['codes'] as $currency_code) {
                $is_primary = $currency_code === $client_variants['primary'];
                $source_id = $is_primary
                    ? $client->id
                    : $this->currencyClientSourceId((int) $client->id, (string) $currency_code);
                $records->push($this->transformer->client(
                    $client,
                    $meta[(int) $client->id] ?? [],
                    $client_taxes[(int) $client->id] ?? [],
                    $taxes,
                    $currency_code,
                    $source_id,
                    ! $is_primary,
                ));
            }
        }

        return $records;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function productRecords(): iterable
    {
        $items = $this->query('items')->orderBy('id')->get();
        $item_taxes = [];

        if ($this->hasTable('items_taxes')) {
            $item_taxes = $this->connection->table($this->table('items_taxes') . ' as links')
                ->join($this->table('taxes') . ' as taxes', 'taxes.id', '=', 'links.tax_id')
                ->select('links.item_id', 'taxes.*')
                ->orderBy('links.id')
                ->get()
                ->groupBy('item_id')
                ->map(fn(Collection $rows): array => $rows->all())
                ->all();
        }

        return $items->map(fn(object $item): array => $this->transformer->product(
            $item,
            $item_taxes[(int) $item->id] ?? $this->taxesForDirectId((int) $item->tax_id),
        ));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function vendorRecords(): iterable
    {
        return $this->query('project_expenses_suppliers')->orderBy('id')->get()
            ->map(fn(object $supplier): array => $this->transformer->vendor($supplier));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function expenseCategoryRecords(): iterable
    {
        $categories = $this->query('project_expenses_categories')->orderBy('id')->get();
        $names = $categories->pluck('name', 'id');

        return $categories->map(fn(object $category): array => $this->transformer->expenseCategory(
            $category,
            (int) $category->parent_id > 0 ? (string) $names->get((int) $category->parent_id) : null,
        ));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function taskStatusRecords(): iterable
    {
        return $this->query('project_task_statuses')->orderBy('id')->get()
            ->map(fn(object $status): array => $this->transformer->taskStatus($status));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function projectRecords(): iterable
    {
        return $this->query('projects')->whereIn('id', $this->projectIds())->orderBy('id')->get()
            ->map(fn(object $project): array => $this->transformer->project(
                $project,
                $this->clientSourceId((int) $project->client_id, (int) $project->currency_id),
            ));
    }

    /** @return iterable<int, array<string, mixed>> */
    private function taskRecords(): iterable
    {
        $tasks = $this->query('project_tasks')->whereIn('project_id', $this->projectIds())->orderBy('id')->get();
        $times = $this->query('project_times')
            ->whereIn('project_id', $this->projectIds())
            ->orderBy('id')
            ->get();
        $task_ids = $tasks->pluck('id')->map(fn(mixed $id): int => (int) $id)->all();

        if ($this->hasTable('project_timers') && $task_ids !== []) {
            foreach ($this->query('project_timers')->whereIn('task_id', $task_ids)->orderBy('id')->get() as $timer) {
                $times->push((object) [
                    'id' => "timer:{$timer->id}",
                    'project_id' => 0,
                    'task_id' => $timer->task_id,
                    'date' => $timer->start_timestamp,
                    'start_time' => '',
                    'end_time' => '',
                    'minutes' => (int) $timer->current_seconds / 60,
                    'note' => "Pancake timer {$timer->id}",
                    '_start_timestamp' => (int) $timer->start_timestamp,
                    '_end_timestamp' => (int) $timer->start_timestamp + max(0, (int) $timer->current_seconds),
                ]);
            }
        }

        $times_by_task = $times->groupBy(fn(object $time): int => (int) ($time->task_id ?: 0));
        $projects = $this->query('projects')->whereIn('id', $this->projectIds())->get()->keyBy('id');
        $records = collect();

        foreach ($tasks as $task) {
            $records->push(...$this->transformer->tasks(
                $task,
                $times_by_task->get((int) $task->id, collect())->all(),
            ));
        }

        foreach ($times->filter(fn(object $time): bool => ! in_array((int) $time->task_id, $task_ids, true)) as $time) {
            $project = $projects->get((int) $time->project_id);

            if ($project) {
                $records->push($this->transformer->standaloneTime($time, $project));
            }
        }

        return $records;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function documentRecords(DatabaseEntity $entity): iterable
    {
        $invoices = $this->query('invoices')->whereIn('id', $this->invoiceIds())->orderBy('id')->get()
            ->filter(fn(object $invoice): bool => $this->transformer->documentEntity($invoice) === $entity);
        $unique_ids = $invoices->pluck('unique_id')->filter()->values()->all();
        $rows = $this->query('invoice_rows')
            ->whereIn('unique_id', $unique_ids)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('unique_id');
        $row_ids = $rows->flatten(1)->pluck('id')->all();
        $row_taxes = $this->invoiceRowTaxes($row_ids);
        $taxes = $this->keyed('taxes');
        $partials = $this->query('partial_payments')
            ->whereIn('unique_invoice_id', $unique_ids)
            ->orderBy('id')
            ->get()
            ->groupBy('unique_invoice_id');

        foreach ($rows->flatten(1) as $row) {
            if (($row_taxes[(int) $row->id] ?? []) === [] && (int) $row->tax_id > 0 && isset($taxes[(int) $row->tax_id])) {
                $row_taxes[(int) $row->id] = [$taxes[(int) $row->tax_id]];
            }
        }

        $records = $invoices->map(fn(object $invoice): array => $this->transformer->document(
            $invoice,
            $rows->get((string) $invoice->unique_id, collect())->all(),
            $row_taxes,
            $partials->get((string) $invoice->unique_id, collect())->all(),
            $this->clientSourceId((int) $invoice->client_id, (int) $invoice->currency_id),
        ));

        if ($entity === DatabaseEntity::Quotes && $this->hasTable('proposals')) {
            $proposals = $this->query('proposals')
                ->whereIn('client_id', $this->clientIds())
                ->orderBy('id')
                ->get();

            foreach ($proposals as $proposal) {
                $sections = $this->hasTable('proposal_sections')
                    ? $this->query('proposal_sections')->where('proposal_id', $proposal->id)->orderBy('page_key')->orderBy('key')->get()->all()
                    : [];
                $project = (int) $proposal->project_id > 0
                    ? $this->query('projects')->find((int) $proposal->project_id)
                    : null;
                $client_source_id = is_object($project)
                    ? $this->clientSourceId((int) $proposal->client_id, (int) $project->currency_id)
                    : $proposal->client_id;
                $records->push($this->transformer->proposal($proposal, $sections, $client_source_id));
            }
        }

        return $records;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function paymentRecords(): iterable
    {
        $invoices = $this->query('invoices')->whereIn('id', $this->invoiceIds())->orderBy('id')->get()
            ->filter(fn(object $invoice): bool => $this->transformer->documentEntity($invoice) === DatabaseEntity::Invoices);
        $by_unique_id = $invoices->keyBy('unique_id');
        $partials = $this->query('partial_payments')
            ->whereIn('unique_invoice_id', $by_unique_id->keys()->all())
            ->where('is_paid', 1)
            ->orderBy('id')
            ->get();
        $records = collect();
        $invoices_with_partials = [];

        foreach ($partials as $partial) {
            $invoice = $by_unique_id->get((string) $partial->unique_invoice_id);

            if (! $invoice) {
                continue;
            }

            $records->push($this->transformer->payment(
                $partial,
                $invoice,
                $partial->id,
                $this->clientSourceId((int) $invoice->client_id, (int) $invoice->currency_id),
            ));
            $invoices_with_partials[(int) $invoice->id] = true;
        }

        foreach ($invoices as $invoice) {
            if (isset($invoices_with_partials[(int) $invoice->id])
                || (int) $invoice->is_paid !== 1
                || ($this->number($invoice->payment_gross) <= 0 && $this->number($invoice->amount) <= 0)) {
                continue;
            }

            $paid_amount = $this->number($invoice->payment_gross) ?: $this->number($invoice->amount);
            $payment = (object) [
                'amount' => $paid_amount,
                'payment_gross' => $paid_amount,
                'payment_date' => $invoice->payment_date,
                'payment_method' => $invoice->payment_type,
                'payment_type' => $invoice->payment_type,
                'txn_id' => $invoice->txn_id,
                'payment_status' => $invoice->payment_status,
                'transaction_fee' => 0,
                'notes' => '',
            ];
            $records->push($this->transformer->payment(
                $payment,
                $invoice,
                "invoice:{$invoice->id}",
                $this->clientSourceId((int) $invoice->client_id, (int) $invoice->currency_id),
            ));
        }

        return $records;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function expenseRecords(): iterable
    {
        $projects = $this->query('projects')->whereIn('id', $this->projectIds())->get()->keyBy('id');
        $expenses = $this->query('project_expenses')->whereIn('project_id', $this->projectIds())->orderBy('id')->get();

        return $expenses->map(function (object $expense) use ($projects): array {
            $project = $projects->get((int) $expense->project_id);

            if (! $project) {
                throw new RuntimeException("Pancake expense {$expense->id} references a missing project.");
            }

            return $this->transformer->expense(
                $expense,
                $project,
                $this->taxesForDirectId((int) $expense->tax_id),
                $this->clientSourceId((int) $project->client_id, (int) $project->currency_id),
            );
        });
    }

    /** @return iterable<int, array<string, mixed>> */
    private function noteRecords(): iterable
    {
        $records = collect();

        foreach ($this->query('notes')->whereIn('client_id', $this->clientIds())->orderBy('id')->get() as $note) {
            $records->push($this->transformer->note(
                "note:{$note->id}",
                trim("Pancake client note ({$note->submitted})\n\n{$note->note}"),
                DatabaseEntity::Clients,
                $note->client_id,
                "Client note {$note->id}",
            ));
        }

        foreach ($this->query('contact_log')->whereIn('client_id', $this->clientIds())->orderBy('id')->get() as $contact) {
            $records->push($this->transformer->note(
                "contact:{$contact->id}",
                trim(implode("\n", array_filter([
                    "Pancake contact log: {$contact->method}",
                    "Contact: {$contact->contact}",
                    "Subject: {$contact->subject}",
                    "Sent: " . $this->displayTimestamp($contact->sent_date),
                    (int) $contact->duration > 0 ? "Duration: {$contact->duration} seconds" : '',
                    '',
                    (string) $contact->content,
                ]))),
                DatabaseEntity::Clients,
                $contact->client_id,
                "Contact log {$contact->id}",
            ));
        }

        foreach ($this->query('comments')->whereIn('client_id', $this->clientIds())->orderBy('id')->get() as $comment) {
            [$parent_entity, $parent_id] = $this->commentParent($comment);
            $records->push($this->transformer->note(
                "comment:{$comment->id}",
                trim(implode("\n", array_filter([
                    "Pancake comment by {$comment->user_name} on " . $this->displayTimestamp($comment->created),
                    (int) $comment->item_id > 0 ? "Pancake parent: {$comment->item_type}:{$comment->item_id}" : '',
                    (int) $comment->is_private === 1 ? 'Visibility: private' : 'Visibility: client-visible',
                    '',
                    (string) $comment->comment,
                ]))),
                $parent_entity,
                $parent_id,
                "Comment {$comment->id}",
            ));
        }

        if ($this->hasTable('project_updates')) {
            foreach ($this->query('project_updates')->whereIn('project_id', $this->projectIds())->orderBy('id')->get() as $update) {
                $records->push($this->transformer->note(
                    "project_update:{$update->id}",
                    trim("Pancake project update on " . $this->displayTimestamp($update->created) . "\n\n{$update->name}"),
                    DatabaseEntity::Projects,
                    $update->project_id,
                    "Project update {$update->id}",
                ));
            }
        }

        if ($this->hasTable('project_milestones')) {
            foreach ($this->query('project_milestones')->whereIn('project_id', $this->projectIds())->orderBy('id')->get() as $milestone) {
                $records->push($this->transformer->note(
                    "project_milestone:{$milestone->id}",
                    trim(implode("\n", array_filter([
                        "Pancake milestone: {$milestone->name}",
                        (string) $milestone->description,
                        (int) $milestone->target_date > 0
                            ? "Target: " . $this->displayTimestamp($milestone->target_date)
                            : '',
                    ]))),
                    DatabaseEntity::Projects,
                    $milestone->project_id,
                    "Project milestone {$milestone->id}",
                ));
            }
        }

        if ($this->hasTable('client_ticket_support_rate_matrix')) {
            $priorities = $this->keyed('ticket_priorities');
            $taxes = $this->keyed('taxes');

            foreach ($this->query('client_ticket_support_rate_matrix')->whereIn('client_id', $this->clientIds())->orderBy('id')->get() as $rate) {
                $priority = $priorities[(int) $rate->priority_id] ?? null;
                $tax = $taxes[(int) $rate->tax_id] ?? null;
                $records->push($this->transformer->note(
                    "support_rate:{$rate->id}",
                    trim(implode("\n", array_filter([
                        'Pancake support rate',
                        'Priority: ' . (is_object($priority) ? $priority->title : $rate->priority_id),
                        "Rate: {$rate->rate}",
                        $tax ? "Tax: {$tax->name} ({$tax->value}%)" : '',
                    ]))),
                    DatabaseEntity::Clients,
                    $rate->client_id,
                    "Support rate {$rate->id}",
                ));
            }
        }

        if ($this->hasTable('tickets')) {
            $statuses = $this->keyed('ticket_statuses');
            $priorities = $this->keyed('ticket_priorities');

            foreach ($this->query('tickets')->whereIn('client_id', $this->clientIds())->orderBy('id')->get() as $ticket) {
                $posts = $this->hasTable('ticket_posts')
                    ? $this->query('ticket_posts')->where('ticket_id', $ticket->id)->orderBy('created')->get()
                    : collect();
                $history = $this->hasTable('ticket_history')
                    ? $this->query('ticket_history')->where('ticket_id', $ticket->id)->orderBy('created')->get()
                    : collect();
                $status = $statuses[(int) $ticket->status_id] ?? null;
                $priority = $priorities[(int) $ticket->priority_id] ?? null;
                $body = [
                    "Pancake support ticket #{$ticket->id}: {$ticket->subject}",
                    "Created: " . $this->displayTimestamp($ticket->created),
                    'Status: ' . (is_object($status) ? $status->title : $ticket->status_id)
                        . '; priority: ' . (is_object($priority) ? $priority->title : $ticket->priority_id)
                        . "; resolved: {$ticket->resolved}",
                ];

                foreach ($posts as $post) {
                    $body[] = trim("{$post->user_name} (" . $this->displayTimestamp($post->created) . ")\n{$post->message}");
                }

                foreach ($history as $event) {
                    $event_status = $statuses[(int) $event->status_id] ?? null;
                    $body[] = trim('Status changed to '
                        . (is_object($event_status) ? $event_status->title : $event->status_id)
                        . " by {$event->user_name} (" . $this->displayTimestamp($event->created) . ')');
                }

                $records->push($this->transformer->note(
                    "ticket:{$ticket->id}",
                    implode("\n\n", array_filter($body)),
                    DatabaseEntity::Clients,
                    $ticket->client_id,
                    "Ticket {$ticket->id}",
                ));
            }
        }

        return $records;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function uploadRecords(): iterable
    {
        $records = collect();
        $invoices = $this->query('invoices')->whereIn('id', $this->invoiceIds())->get();
        $by_unique_id = $invoices->keyBy('unique_id');

        foreach ($this->query('files')->whereIn('invoice_unique_id', $by_unique_id->keys()->all())->orderBy('id')->get() as $file) {
            $invoice = $by_unique_id->get((string) $file->invoice_unique_id);

            if (! $invoice) {
                continue;
            }

            $records->push($this->transformer->documentUpload(
                "invoice_file:{$file->id}",
                $this->transformer->documentEntity($invoice),
                $invoice->id,
                $this->resolveFile((string) $file->real_filename),
                (string) $file->orig_filename,
                true,
            ));
        }

        if ($this->hasTable('project_files')) {
            $comments = $this->query('comments')->whereIn('client_id', $this->clientIds())->get()->keyBy('id');

            foreach ($this->query('project_files')->orderBy('id')->get() as $file) {
                $comment = $comments->get((int) $file->comment_id);

                if (! $comment) {
                    continue;
                }

                [$parent_entity, $parent_id] = $this->commentParent($comment);

                if (! in_array($parent_entity, [DatabaseEntity::Projects, DatabaseEntity::Tasks], true)) {
                    continue;
                }

                $records->push($this->transformer->documentUpload(
                    "project_file:{$file->id}",
                    $parent_entity,
                    $parent_id,
                    $this->resolveFile((string) $file->real_filename),
                    (string) $file->orig_filename,
                    (int) $comment->is_private !== 1,
                ));
            }
        }

        foreach ($this->query('project_expenses')->whereIn('project_id', $this->projectIds())->where('receipt', '<>', '')->get() as $expense) {
            $records->push($this->transformer->documentUpload(
                "expense_receipt:{$expense->id}",
                DatabaseEntity::Expenses,
                $expense->id,
                $this->resolveFile((string) $expense->receipt),
                basename((string) $expense->receipt),
                false,
            ));
        }

        if ($this->hasTable('tickets') && $this->hasTable('ticket_posts')) {
            $tickets = $this->query('tickets')->whereIn('client_id', $this->clientIds())->get()->keyBy('id');

            foreach ($this->query('ticket_posts')->where('real_filename', '<>', '')->orderBy('id')->get() as $post) {
                $ticket = $tickets->get((int) $post->ticket_id);

                if (! is_object($ticket)) {
                    continue;
                }

                $records->push($this->transformer->documentUpload(
                    "ticket_post_file:{$post->id}",
                    DatabaseEntity::Clients,
                    $ticket->client_id,
                    $this->resolveFile((string) $post->real_filename),
                    (string) $post->orig_filename,
                    false,
                ));
            }
        }

        return $records;
    }

    /** @return array<int, array<int, object>> */
    private function invoiceRowTaxes(array $row_ids): array
    {
        if ($row_ids === [] || ! $this->hasTable('invoice_rows_taxes')) {
            return [];
        }

        return $this->connection->table($this->table('invoice_rows_taxes') . ' as links')
            ->join($this->table('taxes') . ' as taxes', 'taxes.id', '=', 'links.tax_id')
            ->whereIn('links.invoice_row_id', $row_ids)
            ->select('links.invoice_row_id', 'taxes.*')
            ->orderBy('links.id')
            ->get()
            ->groupBy('invoice_row_id')
            ->map(fn(Collection $rows): array => $rows->all())
            ->all();
    }

    /** @return array<int, object> */
    private function taxesForDirectId(int $tax_id): array
    {
        if ($tax_id <= 0 || ! $this->hasTable('taxes')) {
            return [];
        }

        $tax = $this->query('taxes')->find($tax_id);

        return $tax ? [$tax] : [];
    }

    /** @return array<int, array<int, object>> */
    private function optionalGrouped(string $table, string $key, array $ids): array
    {
        if (! $this->hasTable($table) || $ids === []) {
            return [];
        }

        return $this->query($table)->whereIn($key, $ids)->get()
            ->groupBy($key)
            ->map(fn(Collection $rows): array => $rows->all())
            ->all();
    }

    /** @return array<int, object> */
    private function keyed(string $table): array
    {
        if (! $this->hasTable($table)) {
            return [];
        }

        return $this->query($table)->get()->keyBy('id')->all();
    }

    /** @return array<int, array<int, string>> */
    private function clientCurrencies(array $client_ids): array
    {
        if ($client_ids === []) {
            return [];
        }

        return $this->connection->table($this->table('invoices') . ' as invoices')
            ->join($this->table('currencies') . ' as currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->whereIn('invoices.client_id', $client_ids)
            ->select('invoices.client_id', 'currencies.code')
            ->distinct()
            ->orderBy('invoices.client_id')
            ->orderBy('currencies.code')
            ->get()
            ->groupBy('client_id')
            ->map(fn(Collection $rows): array => $rows->pluck('code')->map(fn(mixed $code): string => strtoupper((string) $code))->all())
            ->all();
    }

    /** @return array<int, array{primary: ?string, codes: array<int, string|null>}> */
    private function clientCurrencyVariants(): array
    {
        if ($this->client_currency_variants !== null) {
            return $this->client_currency_variants;
        }

        $client_ids = $this->clientIds();
        $clients = $this->query('clients')->whereIn('id', $client_ids)->get()->keyBy('id');
        $currencies = $this->clientCurrencies($client_ids);
        $variants = [];

        foreach ($client_ids as $client_id) {
            $client = $clients->get($client_id);

            if (! is_object($client)) {
                continue;
            }

            $default = strtoupper(trim((string) $client->default_currency_code));
            $default = preg_match('/^[A-Z]{3}$/', $default) ? $default : null;
            $codes = array_values(array_unique(array_filter(
                $currencies[$client_id] ?? [],
                fn(string $code): bool => preg_match('/^[A-Z]{3}$/', $code) === 1,
            )));

            if ($default !== null && ! in_array($default, $codes, true)) {
                array_unshift($codes, $default);
            }

            if ($codes === []) {
                $codes = [null];
            }

            $variants[$client_id] = [
                'primary' => $default ?? $codes[0],
                'codes' => $codes,
            ];
        }

        return $this->client_currency_variants = $variants;
    }

    private function clientSourceId(int $client_id, int $currency_id): string|int
    {
        $variant = $this->clientCurrencyVariants()[$client_id] ?? null;

        if ($variant === null || count($variant['codes']) <= 1) {
            return $client_id;
        }

        $currency_code = $this->currencyCodes()[$currency_id] ?? $variant['primary'];

        return $currency_code === $variant['primary']
            || $currency_code === null
            || ! in_array($currency_code, $variant['codes'], true)
            ? $client_id
            : $this->currencyClientSourceId($client_id, $currency_code);
    }

    private function currencyClientSourceId(int $client_id, string $currency_code): string
    {
        return "{$client_id}:currency:" . strtoupper($currency_code);
    }

    /** @return array<int, string> */
    private function currencyCodes(): array
    {
        return $this->currency_codes ??= $this->query('currencies')
            ->pluck('code', 'id')
            ->map(fn(mixed $code): string => strtoupper((string) $code))
            ->all();
    }

    /** @return array{0: DatabaseEntity, 1: string|int} */
    private function commentParent(object $comment): array
    {
        $type = strtolower(trim((string) $comment->item_type));
        $id = (int) $comment->item_id;

        if ($id > 0 && str_contains($type, 'task')) {
            $task = $this->hasTable('project_tasks') && $this->hasTable('projects')
                ? $this->query('project_tasks')->find($id)
                : null;

            if (is_object($task) && in_array((int) $task->project_id, $this->projectIds(), true)) {
                return [DatabaseEntity::Tasks, $id];
            }

            return [DatabaseEntity::Clients, (int) $comment->client_id];
        }

        if ($id > 0 && str_contains($type, 'project')) {
            return [DatabaseEntity::Projects, $id];
        }

        if ($id > 0 && str_contains($type, 'proposal')) {
            return [DatabaseEntity::Quotes, "proposal:{$id}"];
        }

        if ($id > 0 && str_contains($type, 'invoice')) {
            $invoice = $this->query('invoices')->find($id);

            if ($invoice) {
                return [$this->transformer->documentEntity($invoice), $id];
            }
        }

        return [DatabaseEntity::Clients, (int) $comment->client_id];
    }

    /** @return array<int, int> */
    private function clientIds(): array
    {
        if ($this->client_ids !== null) {
            return $this->client_ids;
        }

        $query = $this->query('clients')->orderBy('id');

        if ($this->business_identity_id === 0) {
            $query->where(function ($query): void {
                $query->whereNull('business_identity')->orWhere('business_identity', '<=', 0);
            });
        } elseif ($this->business_identity_id !== null) {
            $query->where('business_identity', $this->business_identity_id);
        }

        return $this->client_ids = $query->pluck('id')->map(fn(mixed $id): int => (int) $id)->all();
    }

    /** @return array<int, int> */
    private function projectIds(): array
    {
        return $this->project_ids ??= $this->query('projects')
            ->whereIn('client_id', $this->clientIds())
            ->orderBy('id')
            ->pluck('id')
            ->map(fn(mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    private function invoiceIds(): array
    {
        return $this->invoice_ids ??= $this->query('invoices')
            ->whereIn('client_id', $this->clientIds())
            ->orderBy('id')
            ->pluck('id')
            ->map(fn(mixed $id): int => (int) $id)
            ->all();
    }

    private function timezone(): string
    {
        if (! $this->hasTable('settings')) {
            return 'UTC';
        }

        $value = $this->query('settings')->where('slug', 'timezone')->value('value');

        if (! is_string($value)) {
            return 'UTC';
        }

        $decoded = json_decode($value, true);
        $value = is_string($decoded) ? $decoded : trim($value, "\"'");

        return in_array($value, timezone_identifiers_list(), true) ? $value : 'UTC';
    }

    private function resolveFile(string $filename): string
    {
        if ($filename === '') {
            return '';
        }

        if ($this->files_root && ! str_starts_with($filename, DIRECTORY_SEPARATOR)) {
            return $this->files_root . DIRECTORY_SEPARATOR . ltrim($filename, DIRECTORY_SEPARATOR);
        }

        return $filename;
    }

    private function displayTimestamp(mixed $timestamp): string
    {
        return is_numeric($timestamp) && (int) $timestamp > 0
            ? date(DATE_ATOM, (int) $timestamp)
            : (string) $timestamp;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function hasTable(string $table): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($this->table($table));
    }

    private function query(string $table): \Illuminate\Database\Query\Builder
    {
        return $this->connection->table($this->table($table));
    }

    private function table(string $table): string
    {
        return $this->prefix . $table;
    }
}
