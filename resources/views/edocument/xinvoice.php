<html>
<head>
    <style>
        @page {
            size: 21cm 29cm;
            margin-left: 2.5cm;
        }
        body {
            font-size: 9pt;
        }
        h1 {
            font-size: 19px;
        }
        table {
            margin: 0;
            padding: 0;
            table-layout: fixed;
        }
        tr {
            margin: 0;
            padding: 0;
        }
        th, td {
            vertical-align: top;
        }
        th {
            margin-left: 0;
            margin-right: 0;
            padding-left: 0;
            padding-right: 0;
            font-size: 8pt;
        }
        td {
            font-size: 8pt;
        }
        table.postable {
            width: 100%;
            min-width: 100%;
            max-width: 100%;
            margin-top: 5px;
        }
        table.postable th {
            padding-bottom: 10px;
        }
        table.postable td.posno,
        table.postable th.posno {
            width: 10%;
            min-width: 10%;
            max-width: 10%;
            text-align: left;
        }
        table.postable td.posdesc,
        table.postable th.posdesc {
            width: 25%;
            min-width: 25%;
            max-width: 25%;
            text-align: left;
        }
        table.postable td.posqty,
        table.postable th.posqty {
            width: 20%;
            min-width: 20%;
            max-width: 20%;
            text-align: right;
        }
        table.postable td.posunitprice,
        table.postable th.posunitprice {
            width: 20%;
            min-width: 20%;
            max-width: 20%;
            text-align: right;
        }
        table.postable td.poslineamount,
        table.postable th.poslineamount {
            width: 20%;
            min-width: 20%;
            max-width: 20%;
            text-align: right;
        }
        table.postable td.poslinevat,
        table.postable th.poslinevat {
            width: 5%;
            min-width: 5%;
            max-width: 5%;
            text-align: right;
        }
        table.postable th.posno {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable th.posdesc {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable th.posqty {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable th.posunitprice {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable th.poslineamount {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable th.poslinevat {
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable td.totalname {
            width: 20%;
            min-width: 20%;
            max-width: 20%;
            text-align: left;
            border-bottom: 1px solid #dcdcdc;
        }
        table.postable td.totalvalue {
            width: 20%;
            min-width: 20%;
            max-width: 20%;
            text-align: right;
            border-bottom: 1px solid #dcdcdc;
        }
        .space {
            padding-top: 10px;
        }
        .space2 {
            padding-top: 20px;
        }
        .space3 {
            padding-top: 30px;
        }
        .bold {
            font-weight: bold;
        }
        .italic {
            font-style: italic;
        }
        .red {
            color: #ff0000;
        }
        .green {
            color: #00fff0
        }
        .mt-15 {
            margin-top: 15px;
        }
        .mt-20 {
            margin-top: 20px;
        }
        .mt-25 {
            margin-top: 25px;
        }
        .mt-30 {
            margin-top: 30px;
        }
        .pt-15 {
            padding-top: 15px;
        }
        .pt-20 {
            padding-top: 20px;
        }
        .pt-25 {
            padding-top: 25px;
        }
        .pt-30 {
            padding-top: 30px;
        }
        .fs-10 {
            font-size: 10pt;
        }
        .fs-11 {
            font-size: 11pt;
        }
        .fs-12 {
            font-size: 12pt;
        }
        .fs-13 {
            font-size: 13pt;
        }
        .fs-14 {
            font-size: 14pt;
        }
        .pb-0 {
            padding-bottom: 0px;
        }
    </style>
</head>
<body>
<?php
$document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);
$document->getDocumentBuyer($buyername, $buyerids, $buyerdescription);
$document->getDocumentBuyerAddress($buyeraddressline1, $buyeraddressline2, $buyeraddressline3, $buyerpostcode, $buyercity, $buyercounty, $buyersubdivision);
?>
<p>
    <?php echo $buyername; ?><br>
    <?php if ($buyeraddressline1) { ?><?php echo $buyeraddressline1; ?><br><?php } ?>
    <?php if ($buyeraddressline2) { ?><?php echo $buyeraddressline2; ?><br><?php } ?>
    <?php if ($buyeraddressline3) { ?><?php echo $buyeraddressline3; ?><br><?php } ?>
    <?php echo $buyercounty . " " . $buyerpostcode . " " . $buyercity; ?><br>
</p>
<h1 style="margin: 0; padding: 0; margin-top: 50px">
    Invoice <?php echo $documentno; ?>
</h1>
<p style="margin: 0; padding: 0">
    Invoice Date <?php echo $documentdate->format("d.m.Y"); ?>
</p>
<p style="margin-top: 50px" class="bold">
    Sehr geehrter Kunde,
</p>
<p>
    wir erlauben uns Ihnen folgende Position in Rechnung zu stellen.
</p>
<table class="postable">
    <thead>
    <tr>
        <th class="posno">Pos.</th>
        <th class="posdesc">Beschreibung</th>
        <th class="posqty">Stk.</th>
        <th class="posunitprice">Preis</th>
        <th class="poslineamount">Menge</th>
        <th class="poslinevat">MwSt %</th>
    </tr>
    </thead>
    <tbody>
    <?php
    if ($document->firstDocumentPosition()) {
        $isfirstposition = true;
        do {
            $document->getDocumentPositionGenerals($lineid, $linestatuscode, $linestatusreasoncode);
            $document->getDocumentPositionProductDetails($prodname, $proddesc, $prodsellerid, $prodbuyerid, $prodglobalidtype, $prodglobalid);
            $document->getDocumentPositionGrossPrice($grosspriceamount, $grosspricebasisquantity, $grosspricebasisquantityunitcode);
            $document->getDocumentPositionNetPrice($netpriceamount, $netpricebasisquantity, $netpricebasisquantityunitcode);
            $document->getDocumentPositionLineSummation($lineTotalAmount, $totalAllowanceChargeAmount);
            $document->getDocumentPositionQuantity($billedquantity, $billedquantityunitcode, $chargeFreeQuantity, $chargeFreeQuantityunitcode, $packageQuantity, $packageQuantityunitcode);
            ?>
            <?php if ($document->firstDocumentPositionNote()) { ?>
                <tr>
                    <td class="<?php echo $isfirstposition ? ' space' : '' ?>">&nbsp;</td>
                    <td colspan="5" class="<?php echo $isfirstposition ? ' space' : '' ?>">
                        <?php $document->getDocumentPositionNote($posnoteContent, $posnoteContentCode, $posnoteSubjectCode); ?>
                        <?php echo $posnoteContent; ?>
                        <?php $isfirstposition = false; ?>
                    </td>
                </tr>
            <?php } while ($document->nextDocumentPositionNote()); ?>
            <tr>
                <td class="posno<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo $lineid; ?></td>
                <td class="posdesc<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo $prodname; ?></td>
                <td class="posqty<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo $billedquantity; ?> <?php echo $billedquantityunitcode ?></td>
                <td class="posunitprice<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo number_format($netpriceamount, 2); ?> <?php echo $invoiceCurrency; ?></td>
                <td class="poslineamount<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo number_format($lineTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
                <?php if ($document->firstDocumentPositionTax()) { ?>
                    <?php $document->getDocumentPositionTax($categoryCode, $typeCode, $rateApplicablePercent, $calculatedAmount, $exemptionReason, $exemptionReasonCode); ?>
                    <td class="poslinevat<?php echo $isfirstposition ? ' space' : '' ?>"><?php echo number_format($rateApplicablePercent, 2); ?> %</td>
                <?php } else { ?>
                    <td class="poslinevat<?php echo $isfirstposition ? ' space' : '' ?>">&nbsp;</td>
                <?php } ?>
            </tr>
            <?php if ($document->firstDocumentPositionGrossPriceAllowanceCharge()) { ?>
                <?php do { ?>
                    <?php $document->getDocumentPositionGrossPrice($grossAmount, $grossBasisQuantity, $grossBasisQuantityUnitCode); ?>
                    <?php $document->getDocumentPositionGrossPriceAllowanceCharge($actualAmount, $isCharge, $calculationPercent, $basisAmount, $reason, $taxTypeCode, $taxCategoryCode, $rateApplicablePercent, $sequence, $basisQuantity, $basisQuantityUnitCode, $reasonCode); ?>
                    <tr>
                        <td class="posno">&nbsp;</td>
                        <td class="posdesc bold italic"><?php echo ($isCharge ? "Charge" : "Allowance") ?></td>
                        <td class="posqty">&nbsp;</td>
                        <td class="posunitprice italic"><?php echo number_format($actualAmount, 2); ?> (<?php echo number_format($grossAmount, 2); ?>) <?php echo $invoiceCurrency; ?></td>
                    </tr>
                <?php } while ($document->nextDocumentPositionGrossPriceAllowanceCharge()); ?>
            <?php } ?>
            <?php $isfirstposition = false; ?>
        <?php } while ($document->nextDocumentPosition()); ?>
    <?php } ?>

    <!--
        Allowance/Charge
    -->

    <?php if ($document->firstDocumentAllowanceCharge()) { ?>
        <tr>
            <td colspan="6">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="3">&nbsp;</td>
            <td colspan="3" class="bold fs-11 space">Allowance/Charge</td>
        </tr>
        <?php $isFirstDocumentAllowanceCharge = true; ?>
        <?php do { ?>
            <?php $document->getDocumentAllowanceCharge($actualAmount, $isCharge, $taxCategoryCode, $taxTypeCode, $rateApplicablePercent, $sequence, $calculationPercent, $basisAmount, $basisQuantity, $basisQuantityUnitCode, $reasonCode, $reason); ?>
            <tr>
                <td class="<?php echo $isFirstDocumentAllowanceCharge ? 'space' : ''; ?>" colspan="3">&nbsp;</td>
                <td class="<?php echo $isFirstDocumentAllowanceCharge ? 'space' : ''; ?> totalname"><?php echo $reason ? $reason : ($isCharge ? "Charge" : "Allowance"); ?></td>
                <td class="<?php echo $isFirstDocumentAllowanceCharge ? 'space' : ''; ?> totalvalue"><?php echo number_format($basisAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
                <td class="<?php echo $isFirstDocumentAllowanceCharge ? 'space' : ''; ?> totalvalue bold"><?php echo number_format($actualAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
            </tr>
            <?php $isFirstDocumentAllowanceCharge = false; ?>
        <?php } while ($document->nextDocumentAllowanceCharge()); ?>
    <?php } ?>

    <!--
        Summmation
    -->

    <?php $document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount); ?>
    <tr>
        <td colspan="6">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
        <td colspan="3" class="bold fs-11 space">Summe</td>
    </tr>
    <tr>
        <td class="space" colspan="3">&nbsp;</td>
        <td class="space totalname" colspan="2">Nettobetrag</td>
        <td class="space totalvalue"><?php echo number_format($lineTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
    </tr>
    <?php if($chargeTotalAmount != 0) { ?>
        <tr>
            <td class="" colspan="3">&nbsp;</td>
            <td class="totalname" colspan="2">Summe Aufschläge</td>
            <td class="totalvalue"><?php echo number_format($chargeTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
        </tr>
    <?php } ?>
    <?php if($allowanceTotalAmount != 0) { ?>
        <tr>
            <td class="" colspan="3">&nbsp;</td>
            <td class="totalname" colspan="2">Summe Rabatte</td>
            <td class="totalvalue"><?php echo number_format($allowanceTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
        </tr>
    <?php } ?>
    <tr>
        <td class="" colspan="3">&nbsp;</td>
        <td class="totalname" colspan="2">MwSt.</td>
        <td class="totalvalue"><?php echo number_format($taxTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
    </tr>
    <tr>
        <td class="" colspan="3">&nbsp;</td>
        <td class="totalname bold" colspan="2">Bruttosumme</td>
        <td class="totalvalue bold"><?php echo number_format($grandTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
    </tr>
    <tr>
        <td class="" colspan="3">&nbsp;</td>
        <td class="totalname bold" colspan="2">Bereits gezahlt</td>
        <td class="totalvalue bold"><?php echo number_format($totalPrepaidAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
    </tr>
    <tr>
        <td class="" colspan="3">&nbsp;</td>
        <td class="totalname bold" colspan="2">Zu Zahlen</td>
        <td class="totalvalue bold"><?php echo number_format($duePayableAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
    </tr>

    <!--
        VAT Summation
    -->

    <?php if($document->firstDocumentTax()) { ?>
        <tr>
            <td colspan="6">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="3">&nbsp;</td>
            <td colspan="3" class="bold fs-11">VAT Breakdown</td>
        </tr>
        <?php $isfirsttax = true ?>
        <?php $sumbasisamount = 0.0 ?>
        <?php do { ?>
            <?php $document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode); ?>
            <tr>
                <td class="<?php echo $isfirsttax ? 'space' : '' ?>" colspan="3">&nbsp;</td>
                <td class="totalname<?php echo $isfirsttax ? ' space' : '' ?>"><?php echo number_format($rateApplicablePercent, 2); ?>%</td>
                <td class="totalvalue<?php echo $isfirsttax ? ' space' : '' ?>"><?php echo number_format($basisAmount,2) ?> <?php echo $invoiceCurrency; ?></td>
                <td class="totalvalue bold<?php echo $isfirsttax ? ' space' : '' ?>"><?php echo number_format($calculatedAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
            </tr>
            <?php $sumbasisamount = $sumbasisamount + $basisAmount ?>
            <?php $isfirsttax = false ?>
        <?php } while ($document->nextDocumentTax()); ?>
        <tr>
            <td class="" colspan="3">&nbsp;</td>
            <td class="totalname">Summe</td>
            <td class="totalvalue"><?php echo number_format($sumbasisamount, 2); ?> <?php echo $invoiceCurrency; ?></td>
            <td class="totalvalue bold"><?php echo number_format($taxTotalAmount, 2); ?> <?php echo $invoiceCurrency; ?></td>
        </tr>
    <?php } ?>

    <!--
        Paymentterms
    -->

    <?php if ($document->firstDocumentPaymentTerms()) { ?>
        <?php $isfirstpaymentterm = true ?>
        <?php do { ?>
            <tr>
                <?php $document->getDocumentPaymentTerm($description, $dueDate, $directDebitMandateID); ?>
                <td colspan="6" class="<?php echo $isfirstpaymentterm ? 'space3' : '' ?>">
                    <?php echo $description; ?>
                </td>
            </tr>
            <?php $isfirstpaymentterm = false ?>
        <?php } while ($document->nextDocumentPaymentTerms()); ?>
    <?php } ?>
    <tr><td colspan="6" class=""><bold>Hinweise:</bold></td></tr>
    <?php $document->getDocumentNotes($documentNotes); ?>
    <?php foreach ($documentNotes as $documentNote) { ?>
        <tr><td colspan="6" class=""><?php echo trim(nl2br($documentNote['content'])); ?></td></tr>
    <?php } ?>
    </tbody>
</table>

</body>
</html>
