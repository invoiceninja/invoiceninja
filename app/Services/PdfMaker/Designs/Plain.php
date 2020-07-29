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
    public function html(): ?string
    {
        return file_get_contents(
            base_path('resources/views/pdf-designs//plain.html')
        );
    }

    public static function elements(): array
    {
        return [
            'company-address' => [
                'id' => 'company-address',
                'elements' => [
                    ['element' => 'p', 'content' => '$company.address1'],
                    ['element' => 'p', 'content' => '$company.address2'],
                    ['element' => 'p', 'content' => '$company.city_state_postal'],
                    ['element' => 'p', 'content' => '$company.postal_city_state'],
                    ['element' => 'p', 'content' => '$company.country'],
                    ['element' => 'p', 'content' => '$company1'],
                    ['element' => 'p', 'content' => '$company2'],
                    ['element' => 'p', 'content' => '$company3'],
                    ['element' => 'p', 'content' => '$company4'],
                ],
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => [
                    ['element' => 'tr', 'content' => '', 'elements' => [
                        ['element' => 'th', 'content' => '$entity-number-label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                        ['element' => 'th', 'content' => '$entity-number', 'properties' => ['class' => 'text-left pr-4 font-medium']],
                    ]],
                    ['element' => 'tr', 'content' => '', 'elements' => [
                        ['element' => 'th', 'content' => '$entity-date-label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                        ['element' => 'th', 'content' => '$entity-date', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                    ]],
                    ['element' => 'tr', 'content' => '', 'elements' => [
                        ['element' => 'th', 'content' => '$due-date-label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                        ['element' => 'th', 'content' => '$due-date', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                    ]],
                    ['element' => 'tr', 'content' => '', 'properties' => ['class' => 'bg-gray-200'], 'elements' => [
                        ['element' => 'th', 'content' => '$balance-due-label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                        ['element' => 'th', 'content' => '$balance-due', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                    ]],
                ],
            ],
            'client-details' => [
                'id' => 'client-details',
                'properties' => ['hidden' => 'true'],
                'elements' => [
                    ['element' => 'p', 'content' => '$client.name', 'properties' => ['class' => 'font-medium']],
                    ['element' => 'p', 'content' => '$client.id_number'],
                    ['element' => 'p', 'content' => '$client.vat_number'],
                    ['element' => 'p', 'content' => '$client.address1'],
                    ['element' => 'p', 'content' => '$client.address2'],
                    ['element' => 'p', 'content' => '$client.city_state_postal'],
                    ['element' => 'p', 'content' => '$client.postal_city_state'],
                    ['element' => 'p', 'content' => '$client.country'],
                    ['element' => 'p', 'content' => '$client.email'],
                    ['element' => 'p', 'content' => '$client.custom1'],
                    ['element' => 'p', 'content' => '$client.custom2'],
                    ['element' => 'p', 'content' => '$client.custom3'],
                    ['element' => 'p', 'content' => '$client.custom4'],
                    ['element' => 'p', 'content' => '$contact.custom1'],
                    ['element' => 'p', 'content' => '$contact.custom2'],
                    ['element' => 'p', 'content' => '$contact.custom3'],
                    ['element' => 'p', 'content' => '$contact.custom4'],
                ],
            ],
        ];
    }
}