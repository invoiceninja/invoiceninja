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
namespace App\Services\EDocument\Gateway\Storecove\Models;

class Shipment
{
    public ?string $shipping_marks;
    public ?OriginAddress $origin_address;
    /** @var AllowanceCharges[]|null */
    public ?array $allowance_charges;

    /**
     * @param AllowanceCharges[] $allowance_charges
     */
    public function __construct(
        ?string $shipping_marks,
        ?OriginAddress $origin_address,
        ?array $allowance_charges
    ) {
        $this->shipping_marks = $shipping_marks;
        $this->origin_address = $origin_address;
        $this->allowance_charges = $allowance_charges;
    }

    public function getShippingMarks(): ?string
    {
        return $this->shipping_marks;
    }

    public function getOriginAddress(): ?OriginAddress
    {
        return $this->origin_address;
    }

    /**
     * @return AllowanceCharges[]
     */
    public function getAllowanceCharges(): ?array
    {
        return $this->allowance_charges;
    }

    public function setShippingMarks(?string $shipping_marks): self
    {
        $this->shipping_marks = $shipping_marks;
        return $this;
    }

    public function setOriginAddress(?OriginAddress $origin_address): self
    {
        $this->origin_address = $origin_address;
        return $this;
    }

    /**
     * @param AllowanceCharges[] $allowance_charges
     */
    public function setAllowanceCharges(?array $allowance_charges): self
    {
        $this->allowance_charges = $allowance_charges;
        return $this;
    }
}
