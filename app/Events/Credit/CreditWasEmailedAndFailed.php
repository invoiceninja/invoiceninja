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

namespace App\Events\Credit;

use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasEmailedAndFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $credit;

    public $errors;

    public $company;

    public $event_vars;

    public function __construct(Credit $credit, $company, array $errors, array $event_vars)
    {
        $this->credit = $credit;

        $this->company = $company;

        $this->errors = $errors;

        $this->event_vars = $event_vars;
    }
}
