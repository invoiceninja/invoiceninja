<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class DwollaPaymentDriver
 */
class DwollaPaymentDriver extends BasePaymentDriver
{
    /**
     * @return array
     */
    public function gatewayTypes()
    {
        return [GATEWAY_TYPE_DWOLLA];
    }

    /**
     * @return mixed
     */
    protected function gateway()
    {
        $gateway = parent::gateway();

        if ($gateway->getSandbox() && isset($_ENV['DWOLLA_SANDBOX_KEY']) && isset($_ENV['DWOLLA_SANSBOX_SECRET'])) {
            $gateway->setKey($_ENV['DWOLLA_SANDBOX_KEY']);
            $gateway->setSecret($_ENV['DWOLLA_SANSBOX_SECRET']);
        } elseif (isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET'])) {
            $gateway->setKey($_ENV['DWOLLA_KEY']);
            $gateway->setSecret($_ENV['DWOLLA_SECRET']);
        }

        return $gateway;
    }
}
