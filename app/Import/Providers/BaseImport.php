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

namespace App\Import\Providers;

use App\Models\User;
use App\Utils\Ninja;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Throwable;
use League\Csv\Reader;
use App\Models\Company;
use App\Models\Invoice;
use League\Csv\Statement;
use App\Factory\TaskFactory;
use App\Factory\QuoteFactory;
use App\Factory\ClientFactory;
use App\Factory\PurchaseOrderFactory;
use Illuminate\Support\Carbon;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Import\ImportException;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Repositories\TaskRepository;
use App\Utils\Traits\CleanLineItems;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\QuoteRepository;
use Illuminate\Support\Facades\Cache;
use App\Repositories\ClientRepository;
use App\Mail\Import\CsvImportCompleted;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Factory\RecurringInvoiceFactory;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Repositories\RecurringInvoiceRepository;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use Illuminate\Support\Str;
use Sentry\State\Scope;

class BaseImport
{
    use CleanLineItems;

    private const SYSTEM_ERROR_ROW_SAMPLE_LIMIT = 5;

    public Company $company;

    public array $request;

    public array $error_array = [];

    public $request_name;

    public $repository_name;

    public $factory_name;

    public $repository;

    public $transformer;

    public ?array $column_map = [];

    public ?string $hash;

    public ?string $import_type;

    public ?bool $skip_header;

    public array $entity_count = [];

    public bool $store_import_for_research = false;

    protected ?Throwable $first_system_exception = null;

    protected ?string $system_error_reference = null;

    /** @var array<string, array{count: int, rows: array<int>, exception_types: array<string, int>}> */
    protected array $system_errors = [];

    protected bool $system_errors_reported = false;

    public function __construct(array $request, Company $company)
    {
        $this->company = $company;
        $this->request = $request;
        $this->hash = $request['hash'];
        $this->import_type = $request['import_type'];
        $this->skip_header = $request['skip_header'] ?? null;
        $this->column_map = ! empty($request['column_map'])
            ? array_combine(
                array_keys($request['column_map']),
                array_column($request['column_map'], 'mapping')
            )
            : null;

        auth()->login($this->company->owner(), false);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->setCompany($this->company);
    }

    public function getCsvData($entity_type)
    {

        /** @var string $base64_encoded_csv */
        $base64_encoded_csv = Cache::get($this->hash . '-' . $entity_type);

        if (empty($base64_encoded_csv)) {
            return null;
        }

        nlog("found {$entity_type}");

        $csv = base64_decode($base64_encoded_csv);
        // $csv = mb_convert_encoding($csv, 'UTF-8', 'UTF-8');

        $csv = Reader::fromString($csv);
        $csvdelimiter = self::detectDelimiter($csv);

        $csv->setDelimiter($csvdelimiter);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (
                is_array($headers)
               && count($headers) > 0
               && count($data) > 4
               && $this->import_type === 'csv'
            ) {
                $first_cell = $headers[0];
                if (strstr($first_cell, config('ninja.app_name'))) {
                    $data = array_slice($data, 3, null, true);
                }
            }
        }

        return $data;
    }

    public function detectDelimiter($csvfile)
    {
        $delimiters = [',', '.', ';', '|'];
        $bestDelimiter = ',';
        $count = 0;

        // 10-01-2024 - A better way to resolve the csv file delimiter.
        $csvfile = substr($csvfile, 0, strpos($csvfile, "\n"));

        foreach ($delimiters as $delimiter) {

            if (substr_count(strstr($csvfile, "\n", true), $delimiter) >= $count) {
                $count = substr_count($csvfile, $delimiter);
                $bestDelimiter = $delimiter;
            }

        }

        /** @phpstan-ignore-next-line **/
        return $bestDelimiter ?? ',';
    }

    public function mapCSVHeaderToKeys($csvData)
    {
        $keys = array_shift($csvData);

        return array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $csvData);
    }

    private function groupTasks($csvData, $key)
    {

        $first_item = is_array($csvData) ? reset($csvData) : false;

        if (! $key || !is_array($csvData) || count($csvData) == 0 || !is_array($first_item) || empty($first_item['task.number'])) {
            return $csvData;
        }

        // Group by tasks.
        $grouped = [];

        foreach ($csvData as $source_row => $item) {
            if (empty($item[$key])) {
                $this->error_array['task'][] = [
                    'task' => $item,
                    'error' => 'No task number',
                ];
            } else {
                $grouped[$item[$key]][$source_row] = $item;
            }
        }

        return $grouped;


    }

    public function groupClients($csvData, $key)
    {
        $first_item = is_array($csvData) ? reset($csvData) : false;

        if (!($key && is_array($first_item) && isset($first_item[$key]))) {
            // Transform the flat array to match the expected grouped structure
            // Each row becomes its own group to maintain consistency
            $grouped = [];
            foreach ($csvData as $index => $item) {
                $grouped[$index] = [$index => $item];
            }
            return $grouped;
        }

        $grouped = [];

        // Group by client name / id.
        $grouped = [];

        foreach ($csvData as $source_row => $contact_item) {
            if (empty($contact_item[$key])) {
                $this->error_array['client'][] = [
                    'client' => $contact_item,
                    'error' => 'No client identifier',
                ];
            } else {
                $grouped[$contact_item[$key]][$source_row] = $contact_item;
            }
        }

        return $grouped;

    }

    private function groupInvoices($csvData, $key)
    {
        if (! $key) {
            return $csvData;
        }

        $first_item = is_array($csvData) ? reset($csvData) : false;

        if (is_array($csvData) && (!is_array($first_item) || !isset($first_item[$key]))) {
            return $csvData;
        }

        // Group by invoice.
        $grouped = [];

        foreach ($csvData as $source_row => $line_item) {
            // if (empty($line_item[$key])) {
            //     $this->error_array['invoice'][] = [
            //         'invoice' => $line_item,
            //         'error' => 'No invoice number',
            //     ];
            // } else {
            $grouped[$line_item[$key]][$source_row] = $line_item;
            // }
        }

        return $grouped;
    }

    public function getErrors(): array
    {
        $errors = $this->error_array;

        foreach ($this->system_errors as $entity_type => $system_error) {
            $entity_label = str_replace('_', ' ', $entity_type);
            $record_label = $system_error['count'] === 1 ? 'record' : 'records';

            $errors[$entity_type][] = [
                $entity_type => [
                    'failed_records' => $system_error['count'],
                    'sample_rows' => $system_error['rows'],
                    'reference' => $this->system_error_reference,
                ],
                'code' => 'system_error',
                'reference' => $this->system_error_reference,
                'error' => sprintf(
                    '%d %s %s could not be imported because of a system error. Contact support with reference %s.',
                    $system_error['count'],
                    $entity_label,
                    $record_label,
                    $this->system_error_reference
                ),
            ];
        }

        return $errors;
    }

    protected function handleImportFailure(
        Throwable $exception,
        string $entity_type,
        mixed $record,
        int|string|null $source_row = null
    ): void {
        $this->rollBackActiveTransaction();

        if ($exception instanceof ImportException) {
            $this->error_array[$entity_type][] = [
                $entity_type => $record,
                'error' => $exception->getMessage(),
            ];

            return;
        }

        $this->system_error_reference ??= 'IMP-' . Str::upper(Str::random(10));
        $this->first_system_exception ??= $exception;
        $this->system_errors[$entity_type] ??= [
            'count' => 0,
            'rows' => [],
            'exception_types' => [],
        ];

        $this->system_errors[$entity_type]['count']++;

        $exception_type = $exception::class;
        $this->system_errors[$entity_type]['exception_types'][$exception_type]
            = ($this->system_errors[$entity_type]['exception_types'][$exception_type] ?? 0) + 1;

        foreach ($this->getSourceRows($record, $source_row) as $row_number) {
            if (count($this->system_errors[$entity_type]['rows']) >= self::SYSTEM_ERROR_ROW_SAMPLE_LIMIT) {
                break;
            }

            if (!in_array($row_number, $this->system_errors[$entity_type]['rows'], true)) {
                $this->system_errors[$entity_type]['rows'][] = $row_number;
            }
        }

        $this->store_import_for_research = true;
    }

    protected function reportSystemImportErrors(): void
    {
        if ($this->system_errors_reported || !$this->first_system_exception) {
            return;
        }

        $this->system_errors_reported = true;

        $context = [
            'reference' => $this->system_error_reference,
            'hash' => $this->hash,
            'import_type' => $this->import_type,
            'company_id' => $this->company->id,
            'company_db' => $this->company->db,
            'failures' => array_sum(array_column($this->system_errors, 'count')),
            'entities' => $this->system_errors,
            'column_map' => $this->sanitizedColumnMap(),
        ];

        nlog(sprintf(
            'Import system error [%s] failures=%d entities=%s',
            $this->system_error_reference,
            $context['failures'],
            implode(',', array_keys($this->system_errors))
        ));

        try {
            $this->captureSystemImportException($this->first_system_exception, $context);
        } catch (Throwable $reporting_exception) {
            nlog(sprintf(
                'Unable to report import system error [%s]: %s',
                $this->system_error_reference,
                $reporting_exception::class
            ));
        }
    }

    protected function captureSystemImportException(Throwable $exception, array $context): void
    {
        if (!app()->bound('sentry')) {
            report($exception);

            return;
        }

        \Sentry\withScope(function (Scope $scope) use ($exception, $context): void {
            $scope->setTag('feature', 'csv_import');
            $scope->setTag('import_type', (string) $this->import_type);
            $scope->setTag('import_reference', (string) $this->system_error_reference);
            $scope->setTag('exception_type', class_basename($exception));
            $scope->setContext('import', $context);

            \Sentry\captureException($exception);
        });
    }

    private function rollBackActiveTransaction(): void
    {
        $connection = \DB::connection(config('database.default'));

        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }

    /** @return array<int> */
    private function getSourceRows(mixed $record, int|string|null $source_row): array
    {
        if ($this->import_type !== 'csv') {
            return [];
        }

        if (is_array($record) && is_array(reset($record))) {
            $rows = array_filter(array_keys($record), 'is_int');

            return array_values(array_map(static fn(int $row): int => $row + 1, $rows));
        }

        return is_int($source_row) ? [$source_row + 1] : [];
    }

    /** @return array<string, array<int, string>> */
    private function sanitizedColumnMap(): array
    {
        $column_map = [];

        foreach ($this->column_map ?? [] as $entity_type => $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $column_map[$entity_type] = array_values(array_filter(
                $mapping,
                static fn($destination): bool => is_string($destination) && $destination !== ''
            ));
        }

        return $column_map;
    }


    private function runValidation($data)
    {
        $_syn_request_class = new $this->request_name();
        $_syn_request_class->setContainer(app());
        $_syn_request_class->initialize($data);
        $_syn_request_class->prepareForValidation();

        $validator = Validator::make($_syn_request_class->all(), $_syn_request_class->rules());

        $_syn_request_class->setValidator($validator);

        return $validator;
    }

    public function ingest($data, $entity_type)
    {
        $count = 0;

        $is_free_hosted_client = $this->company->account->isFreeHostedClient();
        $hosted_client_count = $this->company->account->hosted_client_count;

        if ($this->factory_name == 'App\Factory\ClientFactory' && $is_free_hosted_client && (count($data) > $hosted_client_count)) {
            $this->error_array[$entity_type][] = [
                $entity_type => 'client',
                'error' => 'Error, you are attempting to import more clients than your plan allows',
            ];

            return $count;
        }

        foreach ($data as $key => $record) {

            $record_for_context = $record;

            if (is_array($record) && is_array(reset($record))) {
                $record = array_values($record);
            }

            unset($record['']);

            if (!is_array($record)) {
                continue;
            }

            try {
                $entity = $this->transformer->transform($record);

                if (!$entity) {
                    continue;
                }

                $validator = $this->runValidation($entity);

                if ($validator->fails()) {
                    $this->error_array[$entity_type][] = [
                        $entity_type => $record,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $entity = $this->repository->save(
                        array_diff_key($entity, ['user_id' => false]),
                        $this->factory_name::create(
                            $this->company->id,
                            $this->getUserIDForRecord($entity)
                        )
                    );
                    $entity->saveQuietly();
                    $count++;
                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, $entity_type, $record_for_context, $key);
            }
        }

        return $count;
    }

    public function ingestProducts($data, $entity_type)
    {
        $count = 0;

        foreach ($data as $key => $record) {

            if (!is_array($record)) {
                continue;
            }

            try {
                $entity = $this->transformer->transform($record);
                $validator = $this->request_name::runFormRequest($entity);

                if ($validator->fails()) {
                    $this->error_array[$entity_type][] = [
                        $entity_type => $record,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    if ($this->transformer->hasProduct($entity['product_key'])) {
                        $product = $this->transformer->getProduct($entity['product_key']);
                    } else {
                        $product = $this->factory_name::create($this->company->id, $this->getUserIDForRecord($entity));
                    }

                    $entity = $this->repository->save(
                        array_diff_key($entity, ['user_id' => false]),
                        $product
                    );

                    $entity->saveQuietly();
                    $count++;
                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, $entity_type, $record, $key);
            }
        }

        return $count;
    }

    public function ingestRecurringInvoices($invoices, $invoice_number_key)
    {
        $count = 0;

        $invoice_transformer = $this->transformer;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $invoice_repository = new RecurringInvoiceRepository();
        $invoice_repository->import_mode = true;

        $invoices = $this->groupInvoices($invoices, $invoice_number_key);

        foreach ($invoices as $record_key => $raw_invoice) {

            if (!is_array($raw_invoice)) {
                continue;
            }

            $record_for_context = $raw_invoice;
            $raw_invoice = is_array(reset($raw_invoice)) ? array_values($raw_invoice) : $raw_invoice;

            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);
                $invoice_data['user_id'] = $this->company->owner()->id;

                $invoice_data['line_items'] = $this->cleanItems(
                    $invoice_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($invoice_data['client_id'])
                   && ! empty($invoice_data['client'])
                ) {
                    $client_data = $invoice_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $invoice_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $invoice_data['client_id'] = $client->id;
                    unset($invoice_data['client']);
                }

                $validator = $this->request_name::runFormRequest($invoice_data);

                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'invoice' => $invoice_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $invoice = RecurringInvoiceFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($invoice_data)
                    );
                    if (! empty($invoice_data['status_id'])) {
                        $invoice->status_id = $invoice_data['status_id'];
                    }
                    $invoice_repository->save($invoice_data, $invoice);

                    $count++;
                    // If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
                    // If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.


                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, 'recurring_invoice', $record_for_context, $record_key);
            }
        }

        return $count;
    }

    public function ingestTasks($tasks, $task_number_key)
    {
        $count = 0;

        $task_transformer = $this->transformer;

        $task_repository = new TaskRepository();

        $tasks = $this->groupTasks($tasks, $task_number_key);

        foreach ($tasks as $record_key => $raw_task) {
            $task_data = [];

            if (!is_array($raw_task)) {
                continue;
            }

            $record_for_context = $raw_task;
            $raw_task = is_array(reset($raw_task)) ? array_values($raw_task) : $raw_task;

            try {
                $task_data = $task_transformer->transform($raw_task);
                $task_data['user_id'] = $this->company->owner()->id;

                $validator = $this->request_name::runFormRequest($task_data);

                if ($validator->fails()) {
                    $this->error_array['task'][] = [
                        'invoice' => $task_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $task = TaskFactory::create(
                        $this->company->id,
                        $this->company->owner()->id
                    );

                    $task_repository->save($task_data, $task);

                    $count++;

                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, 'task', $record_for_context, $record_key);
            }
        }

        return $count;
    }



    public function ingestInvoices($invoices, $invoice_number_key)
    {
        $count = 0;

        $invoice_transformer = $this->transformer;

        /** @var PaymentRepository $payment_repository */
        $payment_repository = app()->make(PaymentRepository::class);
        $payment_repository->import_mode = true;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $invoice_repository = new InvoiceRepository();
        $invoice_repository->import_mode = true;

        $invoices = $this->groupInvoices($invoices, $invoice_number_key);

        foreach ($invoices as $record_key => $raw_invoice) {

            if (!is_array($raw_invoice)) {
                continue;
            }

            $record_for_context = $raw_invoice;
            $raw_invoice = is_array(reset($raw_invoice)) ? array_values($raw_invoice) : $raw_invoice;

            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);
                $invoice_data['user_id'] = $this->company->owner()->id;

                $invoice_data['line_items'] = $this->cleanItems(
                    $invoice_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($invoice_data['client_id'])
                   && ! empty($invoice_data['client'])
                ) {
                    $client_data = $invoice_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $invoice_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $invoice_data['client_id'] = $client->id;
                    unset($invoice_data['client']);
                }

                $validator = $this->request_name::runFormRequest($invoice_data);

                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'invoice' => $invoice_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $invoice = InvoiceFactory::create(
                        $this->company->id,
                        $this->company->owner()->id
                    );
                    if (! empty($invoice_data['status_id'])) {
                        $invoice->status_id = $invoice_data['status_id'];
                    }

                    nlog($invoice_data);
                    $saveable_invoice_data = $invoice_data;

                    if (array_key_exists('payments', $saveable_invoice_data)) {
                        unset($saveable_invoice_data['payments']);
                    }

                    $invoice_repository->save($saveable_invoice_data, $invoice);

                    $count++;
                    // If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
                    // If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.
                    if (
                        $this->import_type !== 'csv'
                        || empty($this->column_map['payment'])
                    ) {
                        // Check for payment columns
                        if (! empty($invoice_data['payments'])) {
                            foreach (
                                $invoice_data['payments'] as $payment_data
                            ) {

                                if ($invoice->status_id == \App\Models\Invoice::STATUS_DRAFT) {
                                    continue;
                                }

                                if ($payment_data['amount'] == 0 && $invoice->status_id == \App\Models\Invoice::STATUS_PAID) {
                                    $payment_data['amount'] = $invoice->amount;
                                }

                                $payment_data['user_id'] = $invoice->user_id;
                                $payment_data['client_id']
                                    = $invoice->client_id;
                                $payment_data['invoices'] = [
                                    [
                                        'invoice_id' => $invoice->id,
                                        'amount' => min($invoice->amount, $payment_data['amount']) ?? null,
                                    ],
                                ];

                                /* Make sure we don't apply any payments to invoices with a Zero Amount*/
                                // if ($invoice->amount > 0 && $payment_data['amount'] > 0) {
                                if ($invoice->amount > 0) {

                                    $payment = $payment_repository->save(
                                        $payment_data,
                                        PaymentFactory::create(
                                            $this->company->id,
                                            $invoice->user_id,
                                            $invoice->client_id
                                        )
                                    );

                                }
                            }
                        }
                    }

                    $this->actionInvoiceStatus(
                        $invoice,
                        $invoice_data,
                        $invoice_repository
                    );
                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, 'invoice', $record_for_context, $record_key);
            }
        }

        return $count;
    }

    private function actionInvoiceStatus(
        $invoice,
        $invoice_data,
        $invoice_repository
    ) {
        if (! empty($invoice_data['archived'])) {
            $invoice_repository->archive($invoice);
            $invoice->fresh();
        }

        if (! empty($invoice_data['viewed'])) {
            $invoice = $invoice
                ->service()
                ->markViewed()
                ->save();
        }

        if ($invoice->status_id == Invoice::STATUS_DRAFT) {
            return $invoice;
        }

        $invoice = $invoice
            ->service()
            ->markSent()
            ->setReminder()
            ->fillDefaults()
            ->save();

        if ($invoice->status_id <= Invoice::STATUS_SENT && $invoice->amount > 0) {
            if ($invoice->balance <= 0) {
                $invoice->status_id = Invoice::STATUS_PAID;
                $invoice->save();
            } elseif ($invoice->balance != $invoice->amount) {
                $invoice->status_id = Invoice::STATUS_PARTIAL;
                $invoice->save();
            }
        }

        return $invoice;
    }

    private function actionQuoteStatus(
        $quote,
        $quote_data,
        $quote_repository
    ) {
        if (! empty($quote_data['archived'])) {
            $quote_repository->archive($quote);
            $quote->fresh();
        }

        if (! empty($quote_data['viewed'])) {
            $quote = $quote
                ->service()
                ->markViewed()
                ->save();
        }

        if ($quote->status_id === Quote::STATUS_DRAFT) {
        } elseif ($quote->status_id === Quote::STATUS_SENT) {
            $quote = $quote
                ->service()
                ->markSent()
                ->save();
        }

        return $quote;
    }

    public function ingestQuotes($quotes, $quote_number_key)
    {
        $count = 0;

        $quote_transformer = $this->transformer;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $quote_repository = new QuoteRepository();
        $quote_repository->import_mode = true;

        $quotes = $this->groupInvoices($quotes, $quote_number_key);

        foreach ($quotes as $record_key => $raw_quote) {

            if (!is_array($raw_quote)) {
                continue;
            }

            $record_for_context = $raw_quote;
            $raw_quote = is_array(reset($raw_quote)) ? array_values($raw_quote) : $raw_quote;

            try {
                $quote_data = $quote_transformer->transform($raw_quote);
                $quote_data['line_items'] = $this->cleanItems(
                    $quote_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($quote_data['client_id'])
                   && ! empty($quote_data['client'])
                ) {
                    $client_data = $quote_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $quote_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $quote_data['client_id'] = $client->id;
                    unset($quote_data['client']);
                }

                $validator = Validator::make(
                    $quote_data,
                    (new StoreQuoteRequest())->rules()
                );
                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'quote' => $quote_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $quote = QuoteFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($quote_data)
                    );
                    if (! empty($quote_data['status_id'])) {
                        $quote->status_id = $quote_data['status_id'];
                    }

                    if (array_key_exists('payments', $quote_data)) {
                        unset($quote_data['payments']);
                    }

                    $quote_repository->save($quote_data, $quote);

                    $count++;

                    $this->actionQuoteStatus(
                        $quote,
                        $quote_data,
                        $quote_repository
                    );
                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, 'quote', $record_for_context, $record_key);
            }
        }

        return $count;
    }

    private function actionPurchaseOrderStatus(
        $purchase_order,
        $purchase_order_data,
        $purchase_order_repository
    ) {
        if ($purchase_order->status_id === PurchaseOrder::STATUS_DRAFT) {
        } elseif ($purchase_order->status_id === PurchaseOrder::STATUS_SENT) {
            $purchase_order = $purchase_order
                ->service()
                ->markSent()
                ->save();
        }

        return $purchase_order;
    }

    public function ingestPurchaseOrders($purchase_orders, $purchase_order_number_key)
    {
        $count = 0;

        $purchase_order_transformer = $this->transformer;

        $purchase_order_repository = new PurchaseOrderRepository();
        $purchase_order_repository->import_mode = true;

        $purchase_orders = $this->groupInvoices($purchase_orders, $purchase_order_number_key);

        foreach ($purchase_orders as $record_key => $raw_purchase_order) {

            if (!is_array($raw_purchase_order)) {
                continue;
            }

            $record_for_context = $raw_purchase_order;
            $raw_purchase_order = is_array(reset($raw_purchase_order)) ? array_values($raw_purchase_order) : $raw_purchase_order;

            try {
                $purchase_order_data = $purchase_order_transformer->transform($raw_purchase_order);
                $purchase_order_data['line_items'] = $this->cleanItems(
                    $purchase_order_data['line_items'] ?? []
                );

                $validator = Validator::make(
                    $purchase_order_data,
                    (new StorePurchaseOrderRequest())->rules()
                );
                if ($validator->fails()) {
                    $this->error_array['purchase_order'][] = [
                        'purchase_order' => $purchase_order_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $purchase_order = PurchaseOrderFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($purchase_order_data)
                    );
                    if (! empty($purchase_order_data['status_id'])) {
                        $purchase_order->status_id = $purchase_order_data['status_id'];
                    }

                    $purchase_order_repository->save($purchase_order_data, $purchase_order);

                    $count++;

                    $this->actionPurchaseOrderStatus(
                        $purchase_order,
                        $purchase_order_data,
                        $purchase_order_repository
                    );
                }
            } catch (Throwable $ex) {
                $this->handleImportFailure($ex, 'purchase_order', $record_for_context, $record_key);
            }
        }

        return $count;
    }

    protected function getUserIDForRecord($record)
    {
        if (! empty($record['user_id'])) {
            return $this->findUser($record['user_id']);
        } else {
            return $this->company->owner()->id;
        }
    }

    protected function findUser($user_hash)
    {
        $user = false;

        if (is_numeric($user_hash)) {

            $user = User::query()
                        ->where('account_id', $this->company->account->id)
                        ->where('id', $user_hash)
                        ->first();

        }

        if ($user) {
            return $user->id;
        }

        $user = User::whereRaw("account_id = ? AND CONCAT_WS(' ', first_name, last_name) like ?", [$this->company->account_id, '%' . $user_hash . '%'])
            ->first();

        if ($user) {
            return $user->id;
        } else {
            return $this->company->owner()->id;
        }
    }

    public function finalizeImport(): void
    {
        $this->reportSystemImportErrors();

        $data = [
            'errors'  => $this->getErrors(),
            'company' => $this->company,
            'entity_count' => $this->entity_count,
        ];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new CsvImportCompleted($this->company, $data);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();

        NinjaMailerJob::dispatch($nmo, true);

        /** Debug for import failures */
        if (Ninja::isHosted() && $this->store_import_for_research) {

            $content = [
                'company_key - ' . $this->company->company_key,
                'class_name - ' . class_basename($this),
                'hash - ' => $this->hash,
            ];

            $potential_imports = [
                'client',
                'product',
                'invoice',
                'payment',
                'vendor',
                'purchase_order',
                'expense',
                'quote',
                'bank_transaction',
                'task',
                'recurring_invoice',
            ];

            foreach ($potential_imports as $import) {

                if (Cache::has($this->hash . '-' . $import)) {
                    Cache::put($this->hash . '-' . $import, Cache::get($this->hash . '-' . $import), 60 * 60 * 24 * 2);
                }
            }

            $this->company->notification(new GenericNinjaAdminNotification($content))->ninja();

        }
    }

    public function preTransform(array $data, $entity_type)
    {
        $keys = array_shift($data);
        ksort($keys);

        return array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $data);
    }

    public function preTransformCsv(array $data, string $entity_type): array|false
    {
        if (empty($this->column_map[$entity_type])) {
            return false;
        }

        $first_row = reset($data);

        if (!is_array($first_row)) {
            return [];
        }

        $expected_column_count = count($first_row);

        if ($this->skip_header) {
            unset($data[array_key_first($data)]);
        }

        $column_map = $this->column_map[$entity_type];
        ksort($column_map);

        foreach ($column_map as $source_column => $destination) {
            if ($destination === '') {
                continue;
            }

            if (!is_int($source_column) || $source_column < 0 || $source_column >= $expected_column_count) {
                $this->error_array[$entity_type][] = [
                    $entity_type => ['source_column' => $source_column],
                    'code' => 'invalid_column_mapping',
                    'error' => sprintf(
                        'The selected source column %s is outside the CSV width of %d columns.',
                        (string) $source_column,
                        $expected_column_count
                    ),
                ];

                return [];
            }
        }

        $transformed = [];

        foreach ($data as $source_row => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row = array_values($row);
            $actual_column_count = count($row);
            $row_number = is_int($source_row) ? $source_row + 1 : null;

            if ($actual_column_count > $expected_column_count) {
                $this->error_array[$entity_type][] = [
                    $entity_type => [
                        'row' => $row_number,
                        'expected_columns' => $expected_column_count,
                        'actual_columns' => $actual_column_count,
                    ],
                    'code' => 'invalid_column_count',
                    'error' => sprintf(
                        'CSV row %s has %d columns; expected %d. Check for an unescaped delimiter.',
                        $row_number ?? 'unknown',
                        $actual_column_count,
                        $expected_column_count
                    ),
                ];

                continue;
            }

            if ($actual_column_count < $expected_column_count) {
                $row = array_pad($row, $expected_column_count, '');
            }

            $mapped_record = [];

            foreach ($column_map as $source_column => $destination) {
                if ($destination === '') {
                    continue;
                }

                $mapped_record[$destination] = $row[$source_column];
            }

            $transformed[$source_row] = $mapped_record;
        }

        return $transformed;
    }

    private function convertData(array $data): array
    {

        // List of encodings to check against
        $encodings = [
            'UTF-8',
            'ISO-8859-1',  // Latin-1
            'ISO-8859-2',  // Latin-2
            'WINDOWS-1252', // CP1252
            'SHIFT-JIS',
            'EUC-JP',
            'GB2312',
            'GBK',
            'BIG5',
            'ISO-2022-JP',
            'KOI8-R',
            'KOI8-U',
            'WINDOWS-1251', // CP1251
            'UTF-16',
            'UTF-32',
            'ASCII',
        ];

        foreach ($data as $key => $value) {
            // Only process strings
            if (is_string($value)) {
                // Detect the encoding of the string
                $detectedEncoding = mb_detect_encoding($value, $encodings, true);

                // If encoding is detected and it's not UTF-8, convert it to UTF-8
                if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
                    $array[$key] = mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
                }
            }
        }

        return $data;
    }

}
