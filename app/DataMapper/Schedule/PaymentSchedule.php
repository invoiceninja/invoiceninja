<?php

namespace App\DataMapper\Schedule;

class PaymentSchedule
{
    /**
     * The template name
     *
     * @var string
     */
    public string $template = 'payment_schedule';

    /**
     *
     * @var array(
     *  'id' => int,
     *  'date' => string,
     *  'amount' => float,
     *  'is_amount' => bool
     * )
     */
    public array $schedule = [];

    /**
     * The invoice id
     *
     * @var string
     */
    public string $invoice_id = '';

    /**
     * Whether to auto bill the invoice
     *
     * @var bool
     */
    public bool $auto_bill = false;
}
