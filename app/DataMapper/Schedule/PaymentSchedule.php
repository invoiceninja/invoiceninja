<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
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
     * @var array<array{id: int, date: string, amount: float, is_amount: bool}>
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
