namespace App\PaymentDrivers;

use App\Models\Client;
use App\Models\Invoice;
use App\PaymentDrivers\BaseDriver;

class InfinitePayCheckout extends BaseDriver
{
    public $refundable = false;
    public $requiresToken = false;
    public $tokenBilling = false;

    protected $handle;

    public function setCompanyGateway($company_gateway)
    {
        parent::setCompanyGateway($company_gateway);
        $this->handle = $company_gateway->getConfigField('handle');
    }

    public function fields()
    {
        return [
            'handle' => [
                'label' => 'InfinitePay Handle',
                'type' => 'text',
                'help' => 'Seu identificador público na InfinitePay (sem o símbolo $)',
            ],
        ];
    }

    public function paymentView($context)
    {
        $invoice = $this->invoices()->first();
        $client = $this->client;

        $items = [
            [
                'name' => 'Fatura #' . $invoice->number,
                'price' => (int) ($invoice->amount * 100),
                'quantity' => 1
            ]
        ];

        $redirect_url = route('infinitepay.redirect', ['invoice' => $invoice->hashed_id]);

        $checkout_url = $this->buildCheckoutUrl($items, $invoice->id, $redirect_url, $client);

        return redirect()->away($checkout_url);
    }

    protected function buildCheckoutUrl($items, $order_nsu, $redirect_url, Client $client)
    {
        $base = "https://checkout.infinitepay.io/{$this->handle}";
        $query = http_build_query([
            'items' => json_encode($items),
            'order_nsu' => $order_nsu,
            'redirect_url' => $redirect_url,
            'customer_name' => $client->present()->name(),
            'customer_email' => $client->present()->email(),
            'customer_cellphone' => $client->phone,
            'address_cep' => $client->postal_code,
            'address_number' => $client->address1,
            'address_complement' => $client->address2,
        ]);

        return "{$base}?{$query}";
    }
}