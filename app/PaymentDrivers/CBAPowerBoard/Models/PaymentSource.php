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

class PaymentSource
{
    /** @var ?string */
    public ?string $_id;
    /** @var string */
    public string $type;
    /** @var string */
    public string $vault_token;
    /** @var string */
    public string $card_name;
    /** @var string */
    public string $card_number_bin;
    /** @var string */
    public string $card_number_last4;
    /** @var string */
    public string $card_scheme;
    /** @var string|null */
    public ?string $address_line1;
    /** @var string|null */
    public ?string $address_line2;
    /** @var string|null */
    public ?string $address_city;
    /** @var string|null */
    public ?string $address_country;
    /** @var string|null */
    public ?string $address_state;
    /** @var int */
    public int $expire_month;
    /** @var int */
    public int $expire_year;
    /** @var ?string */
    public ?string $status;
    /** @var ?string */
    public ?string $created_at;
    /** @var ?string */
    public ?string $updated_at;
    /** @var ?string */
    public ?string $vault_type;
    /** @var ?string */
    public ?string $gateway_id;

    public function __construct(
        ?string $_id,
        string $type,
        string $vault_token,
        string $card_name,
        string $card_number_bin,
        string $card_number_last4,
        string $card_scheme,
        ?string $address_line1,
        ?string $address_line2,
        ?string $address_city,
        ?string $address_country,
        ?string $address_state,
        int $expire_month,
        int $expire_year,
        ?string $status,
        ?string $created_at,
        ?string $updated_at,
        ?string $vault_type,
        ?string $gateway_id
    ) {
        $this->_id = $_id;
        $this->type = $type;
        $this->vault_token = $vault_token;
        $this->card_name = $card_name;
        $this->card_number_bin = $card_number_bin;
        $this->card_number_last4 = $card_number_last4;
        $this->card_scheme = $card_scheme;
        $this->address_line1 = $address_line1;
        $this->address_line2 = $address_line2;
        $this->address_city = $address_city;
        $this->address_country = $address_country;
        $this->address_state = $address_state;
        $this->expire_month = $expire_month;
        $this->expire_year = $expire_year;
        $this->status = $status;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->vault_type = $vault_type;
        $this->gateway_id = $gateway_id;
    }
}
