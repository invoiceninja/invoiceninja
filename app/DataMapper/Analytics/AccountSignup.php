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

class AccountSignup extends GenericMixedMetric
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
    public $name = 'account.signup';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     */
    public $datetime;

    /**
     * The Class failure name
     * set to 0.
     *
     * @var string
     */
    public $string_metric5 = 'plan';

    /**
     * The exception string
     * set to 0.
     *
     * @var string
     */
    public $string_metric6 = 'term';

    /**
     * The counter
     * set to 1.
     *
     * @var int
     */
    public $int_metric1 = 1;

    /**
     * Company Key
     * @var string
     */
    public $string_metric7 = 'key';

    public function __construct($string_metric5, $string_metric6, $string_metric7)
    {
        $this->string_metric5 = $string_metric5 ?: 'free';
        $this->string_metric6 = $string_metric6 ?: 'year';
        $this->string_metric7 = $string_metric7;
    }
}
