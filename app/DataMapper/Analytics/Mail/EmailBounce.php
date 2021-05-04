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

namespace App\DataMapper\Analytics\Mail;

class EmailBounce
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
    public $name = 'job.bounce.email';

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
    public $string_metric5 = 'tag';

    /**
     * The exception string
     * set to 0.
     *
     * @var string
     */
    public $string_metric6 = 'from';

    /**
     * Company Key
     * @var string
     */
    public $string_metric7 = 'messageid';

    /**
     * The counter
     * set to 1.
     *
     * @var string
     */
    public $int_metric1 = 1;

    public function __construct($string_metric5,$string_metric6,$string_metric7) {
        $this->string_metric5 = $string_metric5;
        $this->string_metric6 = $string_metric6;
        $this->string_metric7 = $string_metric7;
    }
}
