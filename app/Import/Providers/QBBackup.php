<?php

namespace App\Import\Providers;

use App\Models\Company;
use App\Models\Invoice;
use App\Import\Providers\BaseImport;
use Illuminate\Support\Facades\Cache;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QBBackup extends BaseImport implements ImportInterface
{
    private array $qb_data = [];

    private QuickbooksService $qb;

    public function __construct(array $request, Company $company)
    {
        parent::__construct($request, $company);

        $base64_zip = Cache::get($request['hash'].'-backup');
        $zip_content = base64_decode($base64_zip);

        $temp_file = tempnam(sys_get_temp_dir(), 'zip_');
        file_put_contents($temp_file, $zip_content);

        $zip = new \ZipArchive();
        if ($zip->open($temp_file) === true) {

            $qb_json = $zip->getFromName('backup.json');
            $this->qb_data = json_decode($qb_json, true);

            $zip->close();
        }
        unlink($temp_file);

        $this->qb = new QuickbooksService($this->company);

    }

    public function import(string $entity)
    {
        if (in_array($entity, ['client', 'invoice', 'quote', 'product', 'payment', 'vendor', 'expense'])) {
            $this->{$entity}();
        }
    }

    public function transform(array $data)
    {
    }

    public function client()
    {
        if (isset($this->qb_data['clients'])) {
            $this->qb->client->importToNinja($this->qb_data['clients']);
        }
    }

    public function product()
    {
        if (isset($this->qb_data['products'])) {
            $this->qb->product->syncToNinja($this->qb_data['products']);
        }
    }

    public function invoice()
    {
        if (isset($this->qb_data['invoices'])) {
            $this->qb->invoice->importToNinja($this->qb_data['invoices']);
        }
    }

    public function quote()
    {
        if (isset($this->qb_data['quotes'])) {
            $this->qb->quote->importToNinja($this->qb_data['quotes']);
        }
    }

    public function payment()
    {

        $payments = isset($this->qb_data['payments']) && is_array($this->qb_data['payments']) ? $this->qb_data['payments'] : [];

        foreach ($payments as $payment) {

            $payment_transformer = new PaymentTransformer($this->company);

            $transformed = $payment_transformer->qbToNinja($payment);

            $ninja_payment = $payment_transformer->buildPayment($payment);
            $ninja_payment->service()->applyNumber()->save();


            $invoice = Invoice::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('sync->qb_id', $payment['invoice_id'])
                    ->first();

            if ($invoice) {

                $paymentable = new \App\Models\Paymentable();
                $paymentable->payment_id = $ninja_payment->id;
                $paymentable->paymentable_id = $invoice->id;
                $paymentable->paymentable_type = 'invoices';
                $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                $paymentable->save();

                $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

            }

        }

    }

    public function vendor()
    {

    }

    public function expense()
    {

    }
}
