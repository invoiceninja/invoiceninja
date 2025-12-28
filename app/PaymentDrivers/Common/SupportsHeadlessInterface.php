<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Common;

interface SupportsHeadlessInterface
{
    /**
     * @param bool $headless
     */
    public function setHeadless(bool $headless): self;
}
