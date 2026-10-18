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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

/**
 * Quotes the gateway fee for a payment attempt without touching the invoice.
 *
 * The fee is priced by building the line item the invoice will eventually carry and
 * running the tax engine over a scratch copy of the committed row. Nothing is saved -
 * the fee reaches the invoice only when the payment is confirmed.
 *
 * @see ConfirmGatewayFee
 */
class CalculateGatewayFee extends AbstractService
{
    public function __construct(
        private CompanyGateway $company_gateway,
        private int $gateway_type_id,
        private Invoice $invoice,
        private float $amount
    ) {
    }

    /**
     * @return array{net: float, gross: float} The line item cost, and the amount the
     *                                         invoice total will rise by once confirmed.
     */
    public function run(): array
    {
        $precision = $this->invoice->client->currency()->precision;

        $net = round(
            $this->company_gateway->calcGatewayFee($this->amount, $this->gateway_type_id, $this->invoice->uses_inclusive_taxes),
            $precision
        );

        if (! $net || $net == 0 || ($net > 0 && $net < 0.01)) {
            return ['net' => 0.0, 'gross' => 0.0];
        }

        /** A scratch copy of the committed row. Never saved. */
        $scratch = Invoice::withTrashed()->find($this->invoice->id);

        if (! $scratch) {
            return ['net' => 0.0, 'gross' => 0.0];
        }

        $starting = (float) $scratch->amount;

        $line_items = (array) $scratch->line_items;
        $line_items[] = self::line($this->company_gateway, $this->gateway_type_id, $net, 'quote', $scratch);

        $scratch->line_items = array_values($line_items);

        return [
            'net' => $net,
            'gross' => round($scratch->calc()->getTempEntity()->amount - $starting, $precision),
        ];
    }

    /**
     * Builds the confirmed gateway fee line.
     *
     * Shared with ConfirmGatewayFee so the quoted amount and the line eventually persisted
     * are produced by identical code - the quote is exact by construction.
     */
    public static function line(?CompanyGateway $company_gateway, ?int $gateway_type_id, float $net, string $payment_hash_string, Invoice $invoice): InvoiceItem
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($invoice->company->settings));
        App::setLocale($invoice->client->locale());

        $item = new InvoiceItem();
        $item->type_id = '4';
        $item->quantity = 1;
        $item->cost = $net;
        $item->unit_code = $payment_hash_string;
        $item->product_key = $net > 0 ? ctrans('texts.surcharge') : ctrans('texts.discount');
        $item->notes = $net > 0 ? ctrans('texts.online_payment_surcharge') : ctrans('texts.online_payment_discount');

        /** Matches the existing behaviour: no gateway type, no fee taxes. */
        if ($company_gateway && $gateway_type_id && $fees_and_limits = $company_gateway->getFeesAndLimits($gateway_type_id)) {
            $item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $item->tax_rate3 = $fees_and_limits->fee_tax_rate3;

            /** The discount branch has never carried tax names or the override tax id. */
            if ($net > 0) {
                $item->tax_name1 = $fees_and_limits->fee_tax_name1;
                $item->tax_name2 = $fees_and_limits->fee_tax_name2;
                $item->tax_name3 = $fees_and_limits->fee_tax_name3;
                $item->tax_id = (string) Product::PRODUCT_TYPE_OVERRIDE_TAX;
            }
        }

        return $item;
    }
}
