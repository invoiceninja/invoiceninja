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

class EmailReport
{
    /**
     * Defines the template name
     *
     * @var string
     */
    public string $template = 'email_report';

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
    public const CUSTOM_RANGE = "custom";


    /**
     * The date range the report should include
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

    /******************************* Parameters **********************************/

    /** @var string $report_name */

    public string $report_name = '';

    /**
     * Optional array of report keys for
     * filter the columns of the report
     *
     * @var array $report_keys
     *
     * */
    public array $report_keys = [];

    /** Profit Loss Parameters */
    public bool $is_income_billed = true;

    public bool $is_expense_billed = true;

    public bool $include_tax = true;

    /**
     * Comma separated string of statuses for filtering the Invoice report
     *
     *  all
     *  draft
     *  sent
     *  paid
     *  unpaid
     *  overdue
     *  viewed
     *
     * */
    public string $status = '';

    /**
     * Comma separated list of product.product_keys
     * to filter the report by
     */
    public string $product_key = '';
}
