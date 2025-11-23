<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Analytics;

use Turbo124\Beacon\ExampleMetric\GenericStructuredMetric;

class PeppolRegistration extends GenericStructuredMetric
{
    /**
     * The type of Sample.
     *
     * Monotonically incrementing counter
     *
     * 	- counter
     *
     * @var string
     */
    public $type = 'structured_metric';

    /**
     * The name of the counter.
     * @var string
     */
    public $name = 'peppol.registration';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     */
    public $datetime;

    /**
     * HTML content
     *
     * @var string
     */
    public $html = '';
    
    /**
     * JSON data
     *
     * @var array
     */
    public $json = [];
    
    /**
     * Initialize with either HTML or JSON content
     *
     * @param string|null $html HTML content
     * @param array|null $json JSON data
     */
    public function __construct(?string $html = null, ?array $json = null)
    {
        if ($html !== null) {
            $this->html = $html;
        }
        
        if ($json !== null) {
            $this->json = $json;
        }
        
    }
}
