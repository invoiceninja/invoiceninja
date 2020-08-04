<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker\Designs\Utilities;

interface DesignInterface
{
    public function html(): ?string;

    public function setup(): void;

    public function elements(array $context): array;

    public function productTable(): array;

    public function buildTableHeader(): array;

    public function buildTableBody(): array;
}
