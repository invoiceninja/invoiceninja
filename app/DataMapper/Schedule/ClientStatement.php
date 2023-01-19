<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Schedule;

use App\Models\Client;
use stdClass;

class ClientStatement
{

    /**
     * Defines the template name
     * 
     * @var string
     */
    public string $template = 'client_statement';

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
    public const THIS_MONTH = 'this_month';
    public const THIS_QUARTER = 'this_quarter';
    public const THIS_YEAR = 'this_year';
    public const PREVIOUS_MONTH = 'previous_month';
    public const PREVIOUS_QUARTER = 'previous_quarter';
    public const PREVIOUS_YEAR = 'previous_year';
    public const CUSTOM_RANGE = "custom_range";

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