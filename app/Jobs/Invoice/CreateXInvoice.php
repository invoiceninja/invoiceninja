<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Country;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;


class CreateXInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Invoice $invoice;

    public function __construct(Invoice $invoice, bool $alterPDF, string $custompdfpath = "")
    {
        $this->invoice = $invoice;
        $this->alterpdf = $alterPDF;
        $this->custompdfpath = $custompdfpath;
    }

    /**
     * Execute the job.
     *
     *
     * @return string
     */
    public function handle(): string
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $profile = "";
        switch ($company->xinvoice_type){
            case "EN16931":
                $profile = ZugferdProfiles::PROFILE_EN16931;
                break;
            case "XInvoice_2_2":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2_2;
                break;
            case "XInvoice_2_1":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2_1;
                break;
            case "XInvoice_2_0":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2;
                break;
            case "XInvoice_1_0":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG;
                break;
            case "XInvoice-Extended":
                $profile = ZugferdProfiles::PROFILE_EXTENDED;
                break;
            case "XInvoice-BasicWL":
                $profile = ZugferdProfiles::PROFILE_BASICWL;
                break;
            case "XInvoice-Basic":
                $profile = ZugferdProfiles::PROFILE_BASIC;
                break;
        }
        $xrechnung =  ZugferdDocumentBuilder::CreateNew($profile);

        $xrechnung
            ->setDocumentInformation($invoice->number, "380", date_create($invoice->date), $invoice->client->getCurrencyCode())
            ->addDocumentNote($invoice->public_notes)
            ->setDocumentSupplyChainEvent(date_create($invoice->date))
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), "", "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2)
            ->setDocumentBuyer($client->name, $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->iso_3166_2)
            ->setDocumentBuyerReference($client->leitweg_id)
            ->setDocumentBuyerContact($client->primary_contact()->first()->first_name." ".$client->primary_contact()->first()->last_name, "", $client->primary_contact()->first()->phone, "", $client->primary_contact()->first()->email)
            ->setDocumentBuyerOrderReferencedDocument($invoice->po_number)
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($invoice->date)->diff(date_create($invoice->due_date))->format("%d"), 'paydate' => $invoice->due_date]));

        if (str_contains($company->getSetting('vat_number'), "/")){
            $xrechnung->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
         }
        else {
            $xrechnung->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }
        // Create line items and calculate taxes
        $taxtype1 = "";
        switch ($company->tax_type1){
            case "ZeroRate":
                $taxtype1 = "Z";
                break;
            case "Tax Exempt":
                $taxtype1 = "E";
                break;
            case "Reversal of tax liabilty":
                $taxtype1 = "AE";
                break;
            case "intra-community delivery":
                $taxtype1 = "K";
                break;
            case "Out of EU":
                $taxtype1 = "G";
                break;
            case "Outside the tax scope":
                $taxtype1 = "O";
                break;
            case "Canary Islands":
                $taxtype1 = "L";
                break;
            case "Ceuta / Melila":
                $taxtype1 = "M";
                break;
            default:
                $taxtype1 = "S";
                break;
        }
        $taxtype2 = "";
        switch ($company->tax_type2){
            case "ZeroRate":
                $taxtype2 = "Z";
                break;
            case "Tax Exempt":
                $taxtype2 = "E";
                break;
            case "Reversal of tax liabilty":
                $taxtype2 = "AE";
                break;
            case "intra-community delivery":
                $taxtype2 = "K";
                break;
            case "Out of EU":
                $taxtype2 = "G";
                break;
            case "Outside the tax scope":
                $taxtype2 = "O";
                break;
            case "Canary Islands":
                $taxtype2 = "L";
                break;
            case "Ceuta / Melila":
                $taxtype2 = "M";
                break;
            default:
                $taxtype2 = "S";
                break;
        }
        $taxtype3 = "";
        switch ($company->tax_type3){
            case "ZeroRate":
                $taxtype3 = "Z";
                break;
            case "Tax Exempt":
                $taxtype3 = "E";
                break;
            case "Reversal of tax liabilty":
                $taxtype3 = "AE";
                break;
            case "intra-community delivery":
                $taxtype3 = "K";
                break;
            case "Out of EU":
                $taxtype3 = "G";
                break;
            case "Outside the tax scope":
                $taxtype3 = "O";
                break;
            case "Canary Islands":
                $taxtype3 = "L";
                break;
            case "Ceuta / Melila":
                $taxtype3 = "M";
                break;
            default:
                $taxtype3 = "S";
                break;
        }
        $taxamount_1 = $taxamount_2 = $taxamount_3 = $taxnet_1 = $taxnet_2 = $taxnet_3 = 0.0;
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
            $discountamount = 0.0;
            if ($item->discount > 0){
                if ($invoice->is_amount_discount){
                    $discountamount = $item->discount;
                    $discount += $item->discount;
                }
                else {
                    $discountamount = $item->line_total * ($item->discount / 100);
                    $discount += $item->line_total * ($item->discount / 100);
                }
            }

            // According to european law, each artical can only have one tax percentage
            if ($item->tax_name1 == "" && $item->tax_name2 == "" && $item->tax_name3 == ""){
                if ($invoice->tax_name1 != null && $invoice->tax_name2 == null && $invoice->tax_name3 == null){
                    $xrechnung->addDocumentPositionTax($taxtype1, 'VAT', $invoice->tax_rate1);
                    $taxnet_1 += $item->line_total - $discountamount;
                    $taxamount_1 += $item->tax_amount;
                }
                elseif ($invoice->tax_name1 == null && $invoice->tax_name2 != null && $invoice->tax_name3 == null){
                    $taxnet_2 += $item->line_total - $discountamount;
                    $taxamount_2 += $item->tax_amount;
                    $xrechnung->addDocumentPositionTax($taxtype2, 'VAT', $invoice->tax_rate2);
                }
                elseif ($invoice->tax_name1 == null && $invoice->tax_name2 == null && $invoice->tax_name3 != null){
                    $taxnet_3 += $item->line_total - $discountamount;
                    $taxamount_3 += $item->tax_amount;
                    $xrechnung->addDocumentPositionTax($taxtype3, 'VAT', $invoice->tax_rate3);
                }
                else{
                    nlog("Can't add correct tax position");
                }
            }
            else {
                if ($item->tax_name1 != "" && $item->tax_name2 == "" && $item->tax_name3 == ""){
                    $taxnet_1 += $item->line_total - $discountamount;
                    $taxamount_1 += $item->tax_amount;
                    $xrechnung->addDocumentPositionTax($taxtype1, 'VAT', $item->tax_rate1);
                }
                elseif ($item->tax_name1 == "" && $item->tax_name2 != "" && $item->tax_name3 == ""){
                    $taxnet_2 += $item->line_total - $discountamount;
                    $taxamount_2 += $item->tax_amount;
                    $xrechnung->addDocumentPositionTax($taxtype2, 'VAT', $item->tax_rate2);
                }
                elseif ($item->tax_name1 == "" && $item->tax_name2 == "" && $item->tax_name3 != ""){
                    $taxnet_3 += $item->line_total - $discountamount;
                    $taxamount_3 += $item->tax_amount;
                    $xrechnung->addDocumentPositionTax($taxtype3, 'VAT', $item->tax_rate3);
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
        if ($taxnet_1 > 0){
        $xrechnung->addDocumentTax($taxtype1, "VAT", $taxnet_1, $taxamount_1, $invoice->tax_rate1);
        }
        if ($taxnet_2 > 0) {
        $xrechnung->addDocumentTax($taxtype2, "VAT", $taxnet_2, $taxamount_2, $invoice->tax_rate2);
        }
        if ($taxnet_3 > 0) {
            $xrechnung->addDocumentTax($taxtype3, "VAT", $taxnet_3, $taxamount_3, $invoice->tax_rate3);
        }
        $disk = config('filesystems.default');
        if(!Storage::exists($client->xinvoice_filepath($invoice->invitations->first()))){
            Storage::makeDirectory($client->xinvoice_filepath($invoice->invitations->first()));
        }
        $xrechnung->writeFile(Storage::disk($disk)->path($client->xinvoice_filepath($invoice->invitations->first()) . $invoice->getFileName("xml")));

        if ($this->alterpdf){
            if ($this->custompdfpath != ""){
                $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, $this->custompdfpath);
                $pdfBuilder->generateDocument();
                $pdfBuilder->saveDocument($this->custompdfpath);
            }
            else {
                $filepath_pdf = $client->invoice_filepath($invoice->invitations->first()).$invoice->getFileName();
                $file = Storage::disk($disk)->exists($filepath_pdf);
                if ($file) {
                    $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, Storage::disk($disk)->path($filepath_pdf));
                    $pdfBuilder->generateDocument();
                    $pdfBuilder->saveDocument(Storage::disk($disk)->path($filepath_pdf));
                }
            }
        }
        return $client->invoice_filepath($invoice->invitations->first()).$invoice->getFileName("xml");
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
