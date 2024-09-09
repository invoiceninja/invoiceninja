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

class Gateway
{
    /** @var string */
    public string $_id;
    /** @var string */
    public string $name;
    /** @var string */
    public string $type;
    /** @var string */
    public string $mode;
    /** @var string */
    public string $created_at;
    /** @var string */
    public string $updated_at;
    /** @var bool */
    public bool $archived;
    /** @var bool */
    public bool $default;
    /** @var string */
    public string $verification_status;

    public function __construct(
        string $_id,
        string $name,
        string $type,
        string $mode,
        string $created_at,
        string $updated_at,
        bool $archived,
        bool $default,
        string $verification_status
    ) {
        $this->_id = $_id;
        $this->name = $name;
        $this->type = $type;
        $this->mode = $mode;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->archived = $archived;
        $this->default = $default;
        $this->verification_status = $verification_status;
    }
}
