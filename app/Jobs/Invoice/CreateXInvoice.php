<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class CreateXInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle(): void
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $xrechnung =  ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);

        $xrechnung
            ->setDocumentInformation($invoice->number, "380", date_create($invoice->date), $invoice->client->getCurrencyCode())
            ->addDocumentNote($invoice->public_notes)
            ->setDocumentSupplyChainEvent(date_create($invoice->date))
            ->setDocumentSeller($company->name)
            ->setDocumentSellerAddress($company->address1, "", "", $company->postal_code, $company->city, $company->country->country->iso_3166_2)
            ->setDocumentBuyer($client->name, $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->country->iso_3166_2)
            ->setDocumentBuyerReference($client->leitweg_id)
            ->setDocumentBuyerContact($client->primary_contact->first_name." ".$client->primary_contact->last_name, "", $client->primary_contact->phone, "", $client->primary_contact->email)
            ->setDocumentBuyerOrderReferencedDocument($invoice->po_number)
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($invoice->date)->diff(date_create($invoice->due_date))->format("%d"), 'paydate' => $invoice->due_date]));

        if (str_contains($company->vat_number, "/")){
            $xrechnung->addDocumentSellerTaxRegistration("FC", $company->vat_number);
         }
        else {
            $xrechnung->addDocumentSellerTaxRegistration("VA", $company->vat_number);
        }
        // Create line items and calculate taxes
        $taxamount_1 = $taxAmount_2 = $taxamount_3 = $taxnet_1 = $taxnet_2 = $taxnet_3 = 0.0;
        $netprice = 0.0;
        $chargetotalamount = $discount = 0.0;
        $taxable = $this->getTaxable();

        foreach ($invoice->line_items as $index => $item){
            $xrechnung->addNewPosition($index)
                ->setDocumentPositionProductDetails($item->notes)
                ->setDocumentPositionGrossPrice($item->gross_line_total)
                ->setDocumentPositionNetPrice($item->line_total);
            if (isset($item->task_id)){
                $xrechnung->setDocumentPositionQuantity($item->quantity, "HUR");
            }
            else{
                $xrechnung->setDocumentPositionQuantity($item->quantity, "H87");
            }
            $netprice += $this->getItemTaxable($item, $taxable);

            $xrechnung->addDocumentPositionTax('S', 'VAT', 19);

            if ($item->discount > 0){
                if ($invoice->is_amount_discount){
                    $discount += $item->discount;
                }
                else {
                    $discount += $item->line_total * ($item->discount / 100);
                }
            }
        }

        // Calculate global surcharges
        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $chargetotalamount += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $chargetotalamount += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $chargetotalamount += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $chargetotalamount += $this->invoice->custom_surcharge4;
        }

        // Calculate global discounts
        if ($invoice->disount > 0){
            if ($invoice->is_amount_discount){
                $discount += $invoice->discount;
            }
            else {
                $discount += $invoice->amount * ($invoice->discount / 100);
            }
        }


        if ($invoice->isPartial()){
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $netprice, $chargetotalamount, $discount, $taxable, $invoice->total_taxes, null, $invoice->partial);}
        else {
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $netprice, $chargetotalamount, $discount, $taxable, $invoice->total_taxes, null, 0.0);
        }
        if (strlen($invoice->tax_name1) > 1) {
        $xrechnung->addDocumentTax("S", "VAT", $taxnet_1, $taxamount_1, $invoice->tax_rate1);
        }
        if (strlen($invoice->tax_name2) > 1) {
        $xrechnung->addDocumentTax("S", "VAT", $taxnet_2, $taxAmount_2, $invoice->tax_rate2);
        }
        if (strlen($invoice->tax_name3) > 1) {
            $xrechnung->addDocumentTax("S", "VAT", $taxnet_3, $taxamount_3, $invoice->tax_rate3);
        }
        $xrechnung->writeFile(explode(".", $client->invoice_filepath($invoice->invitations->first()))[0] . "-xinvoice.xml");

        // TODO: Inject XML into PDF
        $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, $client->invoice_filepath($invoice->invitations->first()));
        $pdfBuilder->generateDocument();
        $pdfBuilder->saveDocument($client->invoice_filepath($invoice->invitations->first()));
    }
    private function getItemTaxable($item, $invoice_total): float
    {
        $total = $item->quantity * $item->cost;

        if ($this->invoice->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                if ($invoice_total + $this->invoice->discount != 0) {
                    $total -= $invoice_total ? ($total / ($invoice_total + $this->invoice->discount) * $this->invoice->discount) : 0;
                }
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
            }
        }

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $item->discount;
            } else {
                $total -= $total * $item->discount / 100;
            }
        }

        return round($total, 2);
    }

    /**
     * @return float
     */
    private function getTaxable(): float
    {
        $total = 0.0;

        foreach ($this->invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $total += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $total += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $total += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $total += $this->invoice->custom_surcharge4;
        }

        return $total;
    }

    public function taxAmount($taxable, $rate): float
    {
        if ($this->invoice->uses_inclusive_taxes) {
            return round($taxable - ($taxable / (1 + ($rate / 100))), 2);
        } else {
            return round($taxable * ($rate / 100), 2);
        }
    }
}
