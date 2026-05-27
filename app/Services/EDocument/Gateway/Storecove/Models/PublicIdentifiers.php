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

class PublicIdentifiers
{
    public ?string $scheme;
    public ?string $id;

    public function __construct(?string $scheme, ?string $id)
    {
        $this->scheme = $scheme;
        $this->id = $id;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setScheme(?string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }
}
