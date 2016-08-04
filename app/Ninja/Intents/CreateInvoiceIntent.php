<?php namespace App\Ninja\Intents;

class CreateInvoiceIntent extends BaseIntent
{
    public function process()
    {
        $clientRepo = app('App\Ninja\Repositories\ClientRepository');
        $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');
        $client = false;

        foreach ($this->parameters as $param) {
            if ($param->type == 'client') {
                $client = $clientRepo->findPhonetically($param->entity);
            }
        }

        if ($client) {
            $data = [
                'client_id' => $client->id,
                'invoice_items' => [],
            ];

            $invoice = $invoiceRepo->save($data);

            return view('bots.skype.invoice', [
                    'invoice' => $invoice
                ])->render();


            /*
            if ($invoice->amount > 0) {

            } else {
                return view('bots.skype.card', [
                        'title' => 'Testing',
                        'subtitle' => $invoice->invoice_number,
                    ])->render();
            }
            */
        }
    }

}
