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

namespace App\Services\EDocument\Imports;

use App\Factory\ExpenseFactory;
use App\Services\AbstractService;
use horstoeko\zugferd\ZugferdDocumentReader;

class ZugferdEDocument extends AbstractService
{
    public ZugferdDocumentReader $document;
    private \App\Models\Expense $expense;

    /**
     * @throws \Exception
     */
    public function __construct(public object $tempdocument)
    {
        $this->document = ZugferdDocumentReader::readAndGuessFromContent($this->tempdocument);

    }

    public function run(): string
    {
        $user = auth()->user();

        $this->expense = ExpenseFactory::create($user->company()->id, $user->id);
        $document = $this->document;

        $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);

        echo "\r\nGeneral document information\r\n";
        echo "----------------------------------------------------------------------\r\n";
        echo "Profile:               {$document->getProfileDefinitionParameter("name")}\r\n";
        echo "Profile:               {$document->getProfileDefinitionParameter("altname")}\r\n";
        echo "Document No:           {$documentno}\r\n";
        echo "Document Type:         {$documenttypecode}\r\n";
        echo "Document Date:         {$documentdate->format("Y-m-d")}\r\n";
        echo "Invoice currency:      {$invoiceCurrency}\r\n";
        echo "Tax currency:          {$taxCurrency}\r\n";

        if ($document->firstDocumentPosition()) {
            echo "\r\nDocument positions\r\n";
            echo "----------------------------------------------------------------------\r\n";
            do {
                $document->getDocumentPositionGenerals($lineid, $linestatuscode, $linestatusreasoncode);
                $document->getDocumentPositionProductDetails($prodname, $proddesc, $prodsellerid, $prodbuyerid, $prodglobalidtype, $prodglobalid);
                $document->getDocumentPositionGrossPrice($grosspriceamount, $grosspricebasisquantity, $grosspricebasisquantityunitcode);
                $document->getDocumentPositionNetPrice($netpriceamount, $netpricebasisquantity, $netpricebasisquantityunitcode);
                $document->getDocumentPositionLineSummation($lineTotalAmount, $totalAllowanceChargeAmount);
                $document->getDocumentPositionQuantity($billedquantity, $billedquantityunitcode, $chargeFreeQuantity, $chargeFreeQuantityunitcode, $packageQuantity, $packageQuantityunitcode);

                echo " - Line Id:                        {$lineid}\r\n";
                echo " - Product Name:                   {$prodname}\r\n";
                echo " - Product Description:            {$proddesc}\r\n";
                echo " - Product Buyer ID:               {$prodbuyerid}\r\n";
                echo " - Product Gross Price:            {$grosspriceamount}\r\n";
                echo " - Product Gross Price Basis Qty.: {$grosspricebasisquantity} {$grosspricebasisquantityunitcode}\r\n";
                echo " - Product Net Price:              {$netpriceamount}\r\n";
                echo " - Product Net Price Basis Qty.:   {$netpricebasisquantity} {$netpricebasisquantityunitcode}\r\n";
                echo " - Quantity:                       {$billedquantity} {$billedquantityunitcode}\r\n";
                echo " - Line amount:                    {$lineTotalAmount}\r\n";

                if ($document->firstDocumentPositionTax()) {
                    echo " - Position Tax(es)\r\n";
                    do {
                        $document->getDocumentPositionTax($categoryCode, $typeCode, $rateApplicablePercent, $calculatedAmount, $exemptionReason, $exemptionReasonCode);
                        echo "   - Tax category code:            {$categoryCode}\r\n";
                        echo "   - Tax type code:                {$typeCode}\r\n";
                        echo "   - Tax percent:                  {$rateApplicablePercent}\r\n";
                        echo "   - Tax amount:                   {$calculatedAmount}\r\n";
                    } while ($document->nextDocumentPositionTax());
                }

                if ($document->firstDocumentPositionAllowanceCharge()) {
                    echo " - Position Allowance(s)/Charge(s)\r\n";
                    do {
                        $document->getDocumentPositionAllowanceCharge($actualAmount, $isCharge, $calculationPercent, $basisAmount, $reason, $taxTypeCode, $taxCategoryCode, $rateApplicablePercent, $sequence, $basisQuantity, $basisQuantityUnitCode, $reasonCode);
                        echo "   - Information\r\n";
                        echo "     - Actual Amount:                {$actualAmount}\r\n";
                        echo "     - Type:                         " . ($isCharge ? "Charge" : "Allowance") . "\r\n";
                        echo "     - Tax category code:            {$taxCategoryCode}\r\n";
                        echo "     - Tax type code:                {$taxTypeCode}\r\n";
                        echo "     - Tax percent:                  {$rateApplicablePercent}\r\n";
                        echo "     - Calculated percent:           {$calculationPercent}\r\n";
                        echo "     - Basis amount:                 {$basisAmount}\r\n";
                        echo "     - Basis qty.:                   {$basisQuantity} {$basisQuantityUnitCode}\r\n";
                    } while ($document->nextDocumentPositionAllowanceCharge());
                }

                echo "\r\n";
            } while ($document->nextDocumentPosition());
        }

        if ($document->firstDocumentAllowanceCharge()) {
            echo "\r\nDocument allowance(s)/charge(s)\r\n";
            echo "----------------------------------------------------------------------\r\n";
            do {
                $document->getDocumentAllowanceCharge($actualAmount, $isCharge, $taxCategoryCode, $taxTypeCode, $rateApplicablePercent, $sequence, $calculationPercent, $basisAmount, $basisQuantity, $basisQuantityUnitCode, $reasonCode, $reason);
                echo "   - Information\r\n";
                echo "     - Actual Amount:                {$actualAmount}\r\n";
                echo "     - Type:                         " . ($isCharge ? "Charge" : "Allowance") . "\r\n";
                echo "     - Tax category code:            {$taxCategoryCode}\r\n";
                echo "     - Tax type code:                {$taxTypeCode}\r\n";
                echo "     - Tax percent:                  {$rateApplicablePercent}\r\n";
                echo "     - Calculated percent:           {$calculationPercent}\r\n";
                echo "     - Basis amount:                 {$basisAmount}\r\n";
                echo "     - Basis qty.:                   {$basisQuantity} {$basisQuantityUnitCode}\r\n";
            } while ($document->nextDocumentAllowanceCharge());
        }

        if ($document->firstDocumentTax()) {
            echo "\r\nDocument tax\r\n";
            echo "----------------------------------------------------------------------\r\n";
            do {
                $document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode);
                echo "   - Information\r\n";
                echo "     - Tax category code:            {$categoryCode}\r\n";
                echo "     - Tax type code:                {$typeCode}\r\n";
                echo "     - Basis amount:                 {$basisAmount}\r\n";
                echo "     - Line total Basis amount:      {$lineTotalBasisAmount}\r\n";
                echo "     - Tax percent:                  {$rateApplicablePercent}\r\n";
                echo "     - Tax amount:                   {$calculatedAmount}\r\n";
            } while ($document->nextDocumentTax());
        }

        $document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount);

        echo "\r\nDocument summation\r\n";
        echo "----------------------------------------------------------------------\r\n";

        echo "  - Line total amount                {$lineTotalAmount}\r\n";
        echo "  - Charge total amount              {$chargeTotalAmount}\r\n";
        echo "  - Allowance total amount           {$allowanceTotalAmount}\r\n";
        echo "  - Tax basis total amount           {$taxBasisTotalAmount}\r\n";
        echo "  - Tax total amount                 {$taxTotalAmount}\r\n";
        echo "  - Grant total amount               {$grandTotalAmount}\r\n";
        echo "  - Due payable amount               {$duePayableAmount}\r\n";
    return "";
    }

}
