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

namespace App\Services\EDocument\Gateway;

use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Standards\Settings\PropertyResolver;

/**
 * Class MutatorUtil
 *
 * Utility class for e-document mutations.
 */
class MutatorUtil
{
    /**
     * MutatorUtil constructor.
     */
    public function __construct(public MutatorInterface $mutator) {}

    /**
     * setPaymentMeans
     *
     * Sets the payment means - if it exists
     *
     * @param  bool $required
     * @return self
     */
    public function setPaymentMeans(bool $required = false): self
    {
        $peppol = $this->mutator->getPeppol();

        if (isset($peppol->PaymentMeans)) {
            return $this;
        } elseif ($paymentMeans = $this->getSetting('Invoice.PaymentMeans')) {
            $peppol->PaymentMeans = is_array($paymentMeans) ? $paymentMeans : [$paymentMeans];
            $this->mutator->setPeppol($peppol);
            return $this;
        }

        return $this->checkRequired($required, "Payment Means");

    }


    /**
     * getClientSetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getClientSetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->mutator->getClientSettings(), $property_path);
    }

    /**
     * getCompanySetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getCompanySetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->mutator->getCompanySettings(), $property_path);
    }

    /**
     * getSetting
     *
     * Attempts to harvest and return a preconfigured prop from company / client / invoice settings
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {

        if ($prop_value = PropertyResolver::resolve($this->mutator->getPeppol(), $property_path)) {
            return $prop_value;
        } elseif ($prop_value = PropertyResolver::resolve($this->mutator->getClientSettings(), $property_path)) {
            return $prop_value;
        } elseif ($prop_value = PropertyResolver::resolve($this->mutator->getCompanySettings(), $property_path)) {
            return $prop_value;
        }
        return null;

    }

    /**
     * Check Required
     *
     * Throws if a required field is missing.
     *
     * @param  bool $required
     * @param  string $section
     * @return self
     */
    public function checkRequired(bool $required, string $section): self
    {
        return $required ? throw new PeppolValidationException("e-invoice generation halted:: {$section} required", $section, 400) : $this;
    }

}
