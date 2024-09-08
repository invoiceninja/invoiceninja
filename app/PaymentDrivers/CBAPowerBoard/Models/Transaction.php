<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\CBAPowerBoard\Models;

class Transactions
{
    /** @var string */
    public string $created_at;
    /** @var int */
    public int $amount;
    /** @var string */
    public string $currency;
    /** @var string */
    public string $_id;
    /** @var string */
    public ?string $error_code;
    /** @var ?string */
    public ?string $error_message;
    /** @var ?string */
    public ?string $gateway_specific_description;
    /** @var ?string */
    public ?string $gateway_specific_code;
    /** @var string */
    public string $_source_ip_address;
    /** @var string */
    public string $status;
    /** @var string */
    public string $type;

    public function __construct(
        string $created_at,
        int $amount,
        string $currency,
        string $_id,
        ?string $error_code,
        ?string $error_message,
        ?string $gateway_specific_description,
        ?string $gateway_specific_code,
        string $_source_ip_address,
        string $status,
        string $type
    ) {
        $this->created_at = $created_at;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->_id = $_id;
        $this->error_code = $error_code;
        $this->error_message = $error_message;
        $this->gateway_specific_description = $gateway_specific_description;
        $this->gateway_specific_code = $gateway_specific_code;
        $this->_source_ip_address = $_source_ip_address;
        $this->status = $status;
        $this->type = $type;
    }
}
