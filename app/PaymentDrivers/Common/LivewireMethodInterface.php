<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Common;

interface LivewireMethodInterface
{
    /**
     * Payment page for the gateway method.
     *
     * @param array $data
     */
    public function livewirePaymentView(array $data): string;

    /**
     * Payment data for the gateway method.
     *
     * @param array $data
     * @return array
     */
    public function paymentData(array $data): array;
}
