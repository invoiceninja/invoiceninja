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

namespace App\Services\PdfMaker\Designs;

class Plain
{
    public $elements;


    public function html(): ?string
    {
        return file_get_contents(
            base_path('resources/views/pdf-designs/plain.html')
        );
    }

    public function elements($elements): array
    {
        return [
            'company-address' => [
                'id' => 'company-address',
                'elements' => [
                    ['element' => 'p', 'content' => '$company.address1'],
                ],
            ],
            'product-table' => [
                'id' => 'product-table',
                'elements' => [
                    ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left bg-gray-200'], 'elements' => $this->tableHeader($elements)],
                    ['element' => 'tbody', 'content' => '', 'elements' => $this->tableBody()],
                ],
            ],
        ];
    }

    public function tableHeader($columns)
    {
        $elements = [];

        foreach ($columns as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['class' => 'px-4 py-2']];
        }

        return $elements;
    }

    public function tableBody()
    {
        return [];
    }
}
