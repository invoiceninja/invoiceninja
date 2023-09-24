<?php
/**
 * Payment Ninja (https://paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Payment Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private bool $completed = true;

    public function __construct(private Payment $payment)
    {
    }

    public function run()
    {
        if ($this->payment->number != '') {
            return $this->payment;
        }

        $this->trySaving();

        return $this->payment;
    }

    private function trySaving()
    {
        $x = 1;

        do {
            try {
                $this->payment->number = $this->getNextPaymentNumber($this->payment->client, $this->payment);
                $this->payment->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);
    }
}
