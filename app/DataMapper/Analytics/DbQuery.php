<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\DataMapper\Analytics;

class DbQuery
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
    public $name = 'db.queries';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     * @var DateTime
     */
    public $datetime;

    /**
     * The Class failure name
     * set to 0.
     *
     * @var string
     */
    public $string_metric5 = 'uri';

    public $string_metric6 = 'uri';

    /**
     * The counter
     * set to 1.
     *
     * @var string
     */
    public $int_metric1 = 1;

    public $double_metric2 = 1;

    public function __construct($string_metric5, $string_metric6, $int_metric1, $double_metric2) {
        $this->int_metric1 = $int_metric1;
        $this->string_metric5 = $string_metric5;
        $this->string_metric6 = $string_metric6;
        $this->double_metric2 = $double_metric2;
    }
}
