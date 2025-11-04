namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Invoice;

class VerifyInfinitePayPayments extends Command
{
    protected $signature = 'infinitepay:verify-payments';
    protected $description = 'Verifica pagamentos confirmados na InfinitePay e baixa faturas automaticamente';

    public function handle()
    {
        $invoices = Invoice::where('status_id', Invoice::STATUS_SENT)
            ->whereNotNull('custom_value1') // transaction_id
            ->whereNotNull('custom_value2') // slug
            ->get();

        foreach ($invoices as $invoice) {
            $gateway = $invoice->company->company_gateways()->first();
            $handle = $gateway->getConfigField('handle');

            $response = Http::get("https://api.infinitepay.io/invoices/public/checkout/payment_check/{$handle}", [
                'transaction_nsu' => $invoice->custom_value1,
                'external_order_nsu' => $invoice->id,
                'slug' => $invoice->custom_value2,
            ]);

            if ($response->ok() && $response->json('paid')) {
                $invoice->status_id = Invoice::STATUS_PAID;
                $invoice->save();
                $this->info("Fatura {$invoice->number} marcada como paga.");
            }
        }
    }
}