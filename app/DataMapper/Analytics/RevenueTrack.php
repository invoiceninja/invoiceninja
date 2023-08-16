<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Analytics;

use Turbo124\Beacon\ExampleMetric\GenericMixedMetric;

class RevenueTrack extends GenericMixedMetric
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
    public $type = 'mixed_metric';

    /**
     * The name of the counter.
     * @var string
     */
    public $name = 'app.revenue';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     */
    public $datetime;

    /**
     * The Client email
     *
     * @var string
     */
    public $string_metric5 = 'email';

    /**
     * The AccountKey email
     *
     * @var string
     */
    public $string_metric6 = 'key';

    /**
     * Product Type
     *
     * @var string
     */
    public $string_metric7 = 'product';

    /**
     * Gateway Reference
     *
     * @var string
     */
    public $string_metric8 = 'gateway_reference';

    public $string_metric9 = 'entity_reference';

    public $string_metric10 = 'gateway_type';

    /**
     * The counter
     * set to 1.
     *
     * @var int
     */
    public $int_metric1 = 1;

    /**
     * Amount Received
     *
     * @var double
     */
    public $double_metric2 = 0;

    public function __construct($string_metric5, $string_metric6, $int_metric1, $double_metric2, $string_metric7, $string_metric8, $string_metric9, $string_metric10)
    {
        $this->int_metric1 = $int_metric1;
        $this->double_metric2 = $double_metric2;
        $this->string_metric5 = $string_metric5;
        $this->string_metric6 = $string_metric6;
        $this->string_metric7 = $string_metric7;
        $this->string_metric8 = $string_metric8;
        $this->string_metric9 = $string_metric9;
        $this->string_metric10 = $string_metric10;
    }
}
