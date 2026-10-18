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

namespace App\Services\EDocument\Standards\France;

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\Utils\BcMath;
use LogicException;

final class FranceReportEntryInverter
{
    public function invert(FRReportEntryData $entry): FRReportEntryData
    {
        return match (true) {
            ! is_null($entry->b2cTransaction) => FRReportEntryData::fromB2CTransaction(
                $this->invertB2CTransaction($entry->b2cTransaction),
            ),
            ! is_null($entry->b2cPayment) => FRReportEntryData::fromB2CPayment(
                $this->invertB2CPayment($entry->b2cPayment),
            ),
            ! is_null($entry->b2biPayment) => FRReportEntryData::fromB2BIPayment(
                $this->invertB2BIPayment($entry->b2biPayment),
            ),
            ! is_null($entry->b2biInvoice) => FRReportEntryData::fromB2BIInvoice(
                $this->invertB2BIInvoice($entry->b2biInvoice),
            ),
            default => throw new LogicException('France report entry contains no invertible payload.'),
        };
    }

    private function invertB2CTransaction(B2CTransactionData $transaction): B2CTransactionData
    {
        return new B2CTransactionData(
            date: $transaction->date,
            category: $transaction->category,
            currency: $transaction->currency,
            amountExcludingVat: $this->negative($transaction->amountExcludingVat),
            amountIncludingVat: $this->negative($transaction->amountIncludingVat),
            transactionsCount: null,
            vatPaymentOption: $transaction->vatPaymentOption,
            taxSubtotals: array_map(
                fn (TaxSubtotalData $subtotal): TaxSubtotalData => $this->invertTaxSubtotal($subtotal),
                $transaction->taxSubtotals,
            ),
        );
    }

    private function invertB2CPayment(B2CPaymentData $payment): B2CPaymentData
    {
        return new B2CPaymentData(
            date: $payment->date,
            taxSubtotal: array_map(
                fn (TaxSubtotalData $subtotal): TaxSubtotalData => $this->invertTaxSubtotal($subtotal),
                $payment->taxSubtotal,
            ),
        );
    }

    private function invertB2BIPayment(B2BIPaymentData $payment): B2BIPaymentData
    {
        return new B2BIPaymentData(
            invoiceNumber: $payment->invoiceNumber,
            paymentDate: $payment->paymentDate,
            issueDate: $payment->issueDate,
            paymentMeansCode: $payment->paymentMeansCode,
            taxSubtotals: array_map(
                fn (TaxSubtotalData $subtotal): TaxSubtotalData => $this->invertTaxSubtotal($subtotal),
                $payment->taxSubtotals,
            ),
        );
    }

    private function invertB2BIInvoice(B2BIInvoiceData $invoice): B2BIInvoiceData
    {
        return new B2BIInvoiceData(
            invoiceNumber: $invoice->invoiceNumber,
            issueDate: $invoice->issueDate,
            documentCurrency: $invoice->documentCurrency,
            amountIncludingVat: $this->negative($invoice->amountIncludingVat),
            dueDate: $invoice->dueDate,
            accountingSupplierParty: $invoice->accountingSupplierParty,
            accountingCustomerParty: $invoice->accountingCustomerParty,
            taxSubtotals: array_map(
                fn (TaxSubtotalData $subtotal): TaxSubtotalData => $this->invertTaxSubtotal($subtotal),
                $invoice->taxSubtotals,
            ),
            invoiceLines: array_map(
                fn (array $line): array => [
                    ...$line,
                    'amountExcludingVat' => $this->negative($line['amountExcludingVat']),
                ],
                $invoice->invoiceLines,
            ),
        );
    }

    private function invertTaxSubtotal(TaxSubtotalData $subtotal): TaxSubtotalData
    {
        return new TaxSubtotalData(
            percentage: $subtotal->percentage,
            taxCategory: $subtotal->taxCategory,
            category: $subtotal->category,
            taxableAmount: is_null($subtotal->taxableAmount) ? null : $this->negative($subtotal->taxableAmount),
            taxAmount: is_null($subtotal->taxAmount) ? null : $this->negative($subtotal->taxAmount),
            currency: $subtotal->currency,
            country: $subtotal->country,
            amountIncludingTax: is_null($subtotal->amountIncludingTax) ? null : $this->negative($subtotal->amountIncludingTax),
            amount: is_null($subtotal->amount) ? null : $this->negative($subtotal->amount),
            exemptionReason: $subtotal->exemptionReason,
            exemptionReasonCode: $subtotal->exemptionReasonCode,
        );
    }

    private function negative(int|float|string $amount): string
    {
        return BcMath::sub('0', $amount, 2);
    }
}
