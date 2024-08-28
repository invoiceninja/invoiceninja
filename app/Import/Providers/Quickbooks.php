<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Providers;

use App\Models\Invoice;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Import\ImportException;
use Illuminate\Support\Facades\Cache;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Import\Transformer\Quickbooks\ClientTransformer;
use App\Import\Transformer\Quickbooks\InvoiceTransformer;
use App\Import\Transformer\Quickbooks\PaymentTransformer;
use App\Import\Transformer\Quickbooks\ProductTransformer;

class Quickbooks extends BaseImport
{
    public array $entity_count = [];

    public function import(string $entity)
    {
        if (
            in_array($entity, [
                'client',
                'invoice',
                'product',
                'payment',
                // 'vendor',
                // 'expense',
            ])
        ) {
            $this->{$entity}();
        }

        //collate any errors

        // $this->finalizeImport();
    }

    public function client()
    {
        $entity_type = 'client';
        $data = $this->getData($entity_type);
        if (empty($data)) {
            $this->entity_count['clients'] = 0;

            return;
        }

        $this->request_name = StoreClientRequest::class;
        $this->repository_name = ClientRepository::class;
        $this->factory_name = ClientFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new ClientTransformer($this->company);
        $client_count = $this->ingest($data, $entity_type);
        $this->entity_count['clients'] = $client_count;
    }

    public function product()
    {
        $entity_type = 'product';
        $data = $this->getData($entity_type);
        if (empty($data)) {
            $this->entity_count['products'] = 0;

            return;
        }

        $this->request_name = StoreProductRequest::class;
        $this->repository_name = ProductRepository::class;
        $this->factory_name = ProductFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new ProductTransformer($this->company);
        $count = $this->ingest($data, $entity_type);
        $this->entity_count['products'] = $count;
    }

    public function getData($type)
    {

        // get the data from cache? file? or api ?
        return json_decode(base64_decode(Cache::get("{$this->hash}-{$type}")), true);
    }

    public function payment()
    {
        $entity_type = 'payment';
        $data = $this->getData($entity_type);
        if (empty($data)) {
            $this->entity_count['payments'] = 0;

            return;
        }

        $this->request_name = StorePaymentRequest::class;
        $this->repository_name = PaymentRepository::class;
        $this->factory_name = PaymentFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new PaymentTransformer($this->company);
        $count = $this->ingest($data, $entity_type);
        $this->entity_count['payments'] = $count;
    }

    public function invoice()
    {
        //make sure we update and create products
        $initial_update_products_value = $this->company->update_products;
        $this->company->update_products = true;

        $this->company->save();

        $entity_type = 'invoice';
        $data = $this->getData($entity_type);

        if (empty($data)) {
            $this->entity_count['invoices'] = 0;

            return;
        }

        $this->request_name = StoreInvoiceRequest::class;
        $this->repository_name = InvoiceRepository::class;
        $this->factory_name = InvoiceFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new InvoiceTransformer($this->company);
        $invoice_count = $this->ingestInvoices($data, '');
        $this->entity_count['invoices'] = $invoice_count;
        $this->company->update_products = $initial_update_products_value;
        $this->company->save();
    }

    public function ingestInvoices($invoices, $invoice_number_key)
    {
        $count = 0;
        $invoice_transformer = $this->transformer;
        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;
        $invoice_repository = new InvoiceRepository();
        $invoice_repository->import_mode = true;

        foreach ($invoices as $raw_invoice) {
            if(!is_array($raw_invoice)) {
                continue;
            }

            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);
                $invoice_data['user_id'] = $this->company->owner()->id;
                $invoice_data['line_items'] = (array) $invoice_data['line_items'];
                $invoice_data['line_items'] = $this->cleanItems(
                    $invoice_data['line_items'] ?? []
                );

                if (
                    empty($invoice_data['client_id']) &&
                    ! empty($invoice_data['client'])
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
                    if(!Invoice::where('number', $invoice_data['number'])->first()) {
                        $invoice = InvoiceFactory::create(
                            $this->company->id,
                            $this->company->owner()->id
                        );
                        $invoice->mergeFillable(['partial','amount','balance','line_items']);
                        if (! empty($invoice_data['status_id'])) {
                            $invoice->status_id = $invoice_data['status_id'];
                        }

                        $saveable_invoice_data = $invoice_data;
                        if(array_key_exists('payments', $saveable_invoice_data)) {
                            unset($saveable_invoice_data['payments']);
                        }

                        $invoice->fill($saveable_invoice_data);
                        $invoice->save();
                        $count++;

                    }
                    // $this->actionInvoiceStatus(
                    //     $invoice,
                    //     $invoice_data,
                    //     $invoice_repository
                    // );
                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error ';
                    nlog($ex->getMessage());
                    nlog($raw_invoice);
                }

                $this->error_array['invoice'][] = [
                    'invoice' => $raw_invoice,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }

}
