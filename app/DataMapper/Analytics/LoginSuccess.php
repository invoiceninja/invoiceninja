<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Analytics;

use Turbo124\Beacon\ExampleMetric\GenericCounter;

class LoginSuccess extends GenericCounter
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
    public $type = 'counter';

    /**
     * The name of the counter.
     * @var string
     */
    public $name = 'login.success';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     */
    public $datetime;

    /**
     * The increment amount... should always be
     * set to 0.
     *
     * @var int
     */
    public $metric = 0;
}
