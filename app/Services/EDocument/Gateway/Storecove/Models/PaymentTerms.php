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

use Symfony\Component\Serializer\Attribute\SerializedName;

class PaymentTerms
{
    #[SerializedName('cbc:Note')]
    public ?string $note;

    public function __construct(?string $note)
    {
        $this->note = $note;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }
}
