<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Designs;

class Custom
{
    public $includes;

    public $header;

    public $body;

    public $product;

    public $task;

    public $footer;

    public $name;

    public function __construct($design)
    {
        $this->name = $design->name;

        $this->includes = $design->design->includes;

        $this->header = $design->design->header;

        $this->body = $design->design->body;

        $this->product = $design->design->product;

        $this->task = $design->design->task;

        $this->footer = $design->design->footer;
    }
}
