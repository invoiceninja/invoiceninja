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

namespace App\DataMapper\Referral;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class ReferralMeta implements Arrayable, JsonSerializable
{
    public int $free = 0;

    public int $pro = 0;

    public int $enterprise = 0;

    public function __construct(mixed $entity = null)
    {
        $entity = is_object($entity) ? get_object_vars($entity) : $entity;

        if (!is_array($entity)) {
            return;
        }

        $this->free = (int) ($entity['free'] ?? 0);
        $this->pro = (int) ($entity['pro'] ?? 0);
        $this->enterprise = (int) ($entity['enterprise'] ?? 0);
    }

    public function updateReferralCounts(int $free, int $pro, int $enterprise): self
    {
        $this->free = $free;
        $this->pro = $pro;
        $this->enterprise = $enterprise;

        return $this;
    }

    /**
     * @return array{free: int, pro: int, enterprise: int}
     */
    public function toArray(): array
    {
        return [
            'free' => $this->free,
            'pro' => $this->pro,
            'enterprise' => $this->enterprise,
        ];
    }

    /**
     * @return array{free: int, pro: int, enterprise: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
