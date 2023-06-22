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

namespace App\DataMapper\Schedule;

class EmailStatement
{
    /**
     * Defines the template name
     *
     * @var string
     */
    public string $template = 'email_statement';

    /**
     * An array of clients hashed_ids
     *
     * Leave blank if this action should apply to all clients
     *
     * @var array
     */
    public array $clients = [];

    /**
     * The consts to be used to define the date_range variable of the statement
     */
    public const LAST7 = "last7_days";
    public const LAST30 = "last30_days";
    public const LAST365 = "last365_days";
    public const THIS_MONTH = "this_month";
    public const LAST_MONTH = "last_month";
    public const THIS_QUARTER = "this_quarter";
    public const LAST_QUARTER = "last_quarter";
    public const THIS_YEAR = "this_year";
    public const LAST_YEAR = "last_year";
    public const ALL_TIME = "all_time";
    public const CUSTOM_RANGE = "custom";


    /**
     * The date range the statement should include
     *
     * @var string
     */
    public string $date_range = 'this_month';

    /**
     * If a custom range is select for the date range then
     * the start_date should be supplied in Y-m-d format
     *
     * @var string
     */
    public string $start_date = '';

    /**
     * If a custom range is select for the date range then
     * the end_date should be supplied in Y-m-d format
     *
     * @var string
     */
    public string $end_date = '';

    /**
     * Flag which allows the payment table
     * to be shown
     *
     * @var boolean
     */
    public bool $show_payments_table = true;

    /**
     * Flag which allows the aging table
     * to be shown
     *
     * @var boolean
     */
    public bool $show_aging_table = true;

    /**
     * String const which defines whether
     * the invoices to be shown are either
     * paid or unpaid
     *
     * @var string
     */
    public string $status = 'paid'; // paid | unpaid
}
