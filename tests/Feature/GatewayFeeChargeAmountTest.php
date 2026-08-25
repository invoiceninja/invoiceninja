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

namespace Tests\Feature;

use App\Models\PaymentHash;
use Tests\TestCase;

/**
 * A payment must be recorded for the amount the gateway charged, which is the fee
 * inclusive amount recorded on the PaymentHash.
 *
 * Deriving it from the invoice instead is the BTCPayPaymentDriver:195 defect: the
 * settlement ran before the fee reached the invoice, so the payment was short by the
 * fee. Crypto settlement waits on block confirmations, so that window is long.
 *
 * @see \App\Services\Invoice\ConfirmGatewayFee
 * @see docs/gateway-fee-resolution-plan.md
 */
class GatewayFeeChargeAmountTest extends TestCase
{
    /**
     * The charged amount lives on the hash and includes the fee.
     */
    public function testAmountWithFeeCarriesTheFeeInclusiveTotal(): void
    {
        $hash = new PaymentHash();
        $hash->data = (object) ['amount_with_fee' => 105.0, 'fee_net' => 5.0];
        $hash->fee_total = 5.0;

        $this->assertEquals(105.0, $hash->amount_with_fee());
    }

    /**
     * Structural guard for the whole class of defect.
     *
     * No payment driver may build a payment amount from the invoice row. Under this
     * design the invoice does not carry the fee until confirmation runs, so any driver
     * reading it would record a payment short by the fee.
     */
    public function testNoPaymentDriverDerivesAChargeAmountFromTheInvoice(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('PaymentDrivers'))
        );

        foreach ($files as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            /** e.g. 'amount' => $invoice->amount / $_invoice->balance */
            if (preg_match_all("/['\"]amount['\"]\s*=>\s*\\\$[A-Za-z_]*invoice->(amount|balance)\b/i", $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($source, 0, $match[1]), "\n") + 1;
                    $offenders[] = str_replace(base_path() . '/', '', $file->getPathname()) . ':' . $line;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "a payment amount must come from PaymentHash::amount_with_fee(), not the invoice row:\n  " . implode("\n  ", $offenders)
        );
    }

    /**
     * The gateway fee must never be written to the invoice outside the confirmation
     * service. The old design wrote a pending line at initiation, which is what the
     * cleanup paths then had to undo.
     */
    public function testNothingOutsideTheConfirmationServiceWritesATypeThreeFeeLine(): void
    {
        $offenders = [];

        $allowed = [
            'app/Services/Invoice/InvoiceService.php',      // the drain, removed after one release
            'app/Jobs/Subscription/CleanStaleInvoiceOrder.php',
        ];

        foreach (['app/Services', 'app/PaymentDrivers', 'app/Http', 'app/Livewire'] as $dir) {

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir))
            );

            foreach ($files as $file) {

                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace(base_path() . '/', '', $file->getPathname());

                if (in_array($relative, $allowed, true)) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());

                if (preg_match("/type_id\s*=\s*['\"]3['\"]/", $source)) {
                    $offenders[] = $relative;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "gateway fees are written only on confirmation, as type 4:\n  " . implode("\n  ", $offenders)
        );
    }
}
