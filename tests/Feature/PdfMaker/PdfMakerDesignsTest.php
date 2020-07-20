<?php

namespace Tests\Feature\PdfMaker;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PdfMakerDesignsTest extends TestCase
{
    public $state = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->state = [
            'variables' => [
                '$css' => asset('css/tailwindcss@1.4.6.css'),
                '$global-margin' => 'm-12',
                '$global-padding' => 'p-12',

                '$company-logo' => 'https://invoiceninja-invoice-templates.netlify.app/assets/images/invoiceninja-logo.png',
                '$company-name' => 'Invoice Ninja',
                '$entity-number-label' => 'Invoice number',
                '$entity-number' => '10000',
                '$entity-date-label' => 'Invoice date',
                '$entity-date' => '3th of June, 2025.',
                '$due-date-label' => 'Due date',
                '$due-date' => '5th of June, 2025.',
                '$balance-due-label' => 'Balance due',
                '$balance-due' => '$800.50',

                '$terms-label' => 'Terms',
                '$terms' => 'Trend and SEO report has been sent via email. This is really long text just to test the width of the elements.',

                '$invoice-issued-to' => 'Invoice issued to:',

                '$entity' => 'Invoice',
            ],
        ];
    }

    public function testBusiness()
    {
        $state = [
            'template' => [
                'company-details' => [
                    'id' => 'company-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Ninja Sample'],
                        ['element' => 'p', 'content' => 'contact@invoiceninja.com'],
                        ['element' => 'p', 'content' => '1-800-555-Ninja'],
                    ],
                ],
                'company-address' => [
                    'id' => 'company-address',
                    'elements' => [
                        ['element' => 'p', 'content' => '123 Ninja Blvd.'],
                        ['element' => 'p', 'content' => 'NinjaLand, 97315'],
                        ['element' => 'p', 'content' => 'United States'],
                    ],
                ],
                'client-details' => [
                    'id' => 'client-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Winterfield Medical Supply                        '],
                        ['element' => 'p', 'content' => '65 Medical Complex Rd., D98'],
                        ['element' => 'p', 'content' => 'Atlanta, GA 22546'],
                        ['element' => 'p', 'content' => 'United States'],
                        ['element' => 'p', 'content' => 'demo@invoiceninja.com'],
                    ],
                ],
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => [
                        ['element' => 'div', 'content' => '', 'elements' => [
                            ['element' => 'p', 'content' => '$entity-number-label'],
                            ['element' => 'p', 'content' => '$entity-date-label'],
                            ['element' => 'p', 'content' => '$due-date-label'],
                            ['element' => 'p', 'content' => '$balance-due-label'],
                        ]],
                        ['element' => 'div', 'content' => '', 'elements' => [
                            ['element' => 'p', 'content' => '$entity-number'],
                            ['element' => 'p', 'content' => '$entity-date'],
                            ['element' => 'p', 'content' => '$due-date'],
                            ['element' => 'p', 'content' => '$balance-due'],
                        ]],
                    ],
                ],
                'product-table' => [
                    'id' => 'product-table',
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left rounded-t-lg'], 'elements' => [
                            ['element' => 'th', 'content' => 'Item', 'properties' => ['class' => 'font-semibold text-white px-4 bg-blue-900 py-5']],
                            ['element' => 'th', 'content' => 'Description', 'properties' => ['class' => 'font-semibold text-white px-4 bg-blue-900 py-5']],
                            ['element' => 'th', 'content' => 'Unit cost', 'properties' => ['class' => 'font-semibold text-white px-4 bg-blue-900 py-5']],
                            ['element' => 'th', 'content' => 'Quantity', 'properties' => ['class' => 'font-semibold text-white px-4 bg-blue-900 py-5']],
                            ['element' => 'th', 'content' => 'Line total', 'properties' => ['class' => 'font-semibold text-white px-4 bg-blue-900 py-5']],
                        ]],
                        ['element' => 'tbody', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Painting service', 'properties' => ['class' => 'border-4 border-white text-orange-700 px-4 py-4 bg-gray-200']],
                                ['element' => 'td', 'content' => '25 hours of painting', 'properties' => ['class' => 'border-4 border-white text-orange-700 px-4 py-4 bg-gray-200']],
                                ['element' => 'td', 'content' => '885.00', 'properties' => ['class' => 'border-4 border-white text-orange-700 px-4 py-4 bg-gray-200']],
                                ['element' => 'td', 'content' => '1', 'properties' => ['class' => 'border-4 border-white text-orange-700 px-4 py-4 bg-gray-200']],
                                ['element' => 'td', 'content' => '$885.00', 'properties' => ['class' => 'border-4 border-white text-orange-700 px-4 py-4 bg-gray-200']],
                            ]],
                        ]],
                        ['element' => 'tfoot', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Trend and SEO report has been sent via email. This is really long text just to test the width of the elements.', 'properties' => ['class' => 'border-l-4 border-white px-4 py-4 bg-gray-200', 'colspan' => '2']],
                                ['element' => 'td', 'content' => 'Subtotal', 'properties' => ['class' => 'px-4 py-4 bg-gray-200 text-right', 'colspan' => '2']],
                                ['element' => 'td', 'content' => '$0', 'properties' => ['class' => 'px-4 py-4 bg-gray-200 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Paid to date', 'properties' => ['class' => 'border-l-4 border-white px-4 bg-gray-200 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$0.00', 'properties' => ['class' => 'px-4 py-4 bg-gray-200 text-right']],
                            ]],
                        ]],
                    ],
                ],
            ],
            'variables' => array_merge([
                '#invoice-issued-to' => 'Invoice issued to',
            ], $this->state['variables']),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Business::class)
            ->build();

        exec('echo "" > storage/logs/laravel.log');

        info($maker->getCompiledHTML());
    }

    public function testClean()
    {
        $state = [
            'template' => [
                'company-details' => [
                    'id' => 'company-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Ninja Sample', 'properties' => ['class' => 'text-blue-500']],
                        ['element' => 'p', 'content' => 'contact@invoiceninja.com'],
                        ['element' => 'p', 'content' => '1-800-555-Ninja'],
                    ],
                ],
                'company-address' => [
                    'id' => 'company-address',
                    'elements' => [
                        ['element' => 'p', 'content' => '123 Ninja Blvd.'],
                        ['element' => 'p', 'content' => 'NinjaLand, 97315'],
                        ['element' => 'p', 'content' => 'United States'],
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
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => '$balance-due-label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                            ['element' => 'th', 'content' => '$balance-due', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                        ]],
                    ],
                ],
                'client-details' => [
                    'id' => 'client-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Winterfield Medical Supply', 'properties' => ['class' => 'font-medium']],
                        ['element' => 'p', 'content' => '65 Medical Complex Rd., D98'],
                        ['element' => 'p', 'content' => 'Atlanta, GA 22546'],
                        ['element' => 'p', 'content' => 'United States'],
                        ['element' => 'p', 'content' => 'demo@invoiceninja.com'],
                    ],
                ],
                'product-table' => [
                    'id' => 'product-table',
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left rounded-t-lg'], 'elements' => [
                            ['element' => 'th', 'content' => 'Item', 'properties' => ['class' => 'font-semibold px-4 py-5']],
                            ['element' => 'th', 'content' => 'Description', 'properties' => ['class' => 'font-semibold px-4 py-5']],
                            ['element' => 'th', 'content' => 'Unit cost', 'properties' => ['class' => 'font-semibold px-4 py-5']],
                            ['element' => 'th', 'content' => 'Quantity', 'properties' => ['class' => 'font-semibold px-4 py-5']],
                            ['element' => 'th', 'content' => 'Line total', 'properties' => ['class' => 'font-semibold px-4 py-5']],
                        ]],
                        ['element' => 'tbody', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Painting service', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '25 hours of painting', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '885.00', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '1', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '$885.00', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Painting service', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '25 hours of painting', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '885.00', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '1', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                                ['element' => 'td', 'content' => '$885.00', 'properties' => ['class' => 'border-t border-b px-4 py-4']],
                            ]],
                        ]],
                        ['element' => 'tfoot', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Trend and SEO report has been sent via email. This is really long text just to test the width of the elements.', 'properties' => ['class' => 'border-l-4 border-white px-4 py-4', 'colspan' => '2']],
                                ['element' => 'td', 'content' => 'Subtotal', 'properties' => ['class' => 'px-4 py-4 text-right', 'colspan' => '2']],
                                ['element' => 'td', 'content' => '$0', 'properties' => ['class' => 'px-4 py-4 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Paid to date', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$0.00', 'properties' => ['class' => 'px-4 py-4 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Discount %20', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$2.00', 'properties' => ['class' => 'px-4 py-4 text-right']],
                            ]],
                        ]],
                    ],
                ],
            ],
            'variables' => array_merge([], $this->state['variables']),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Clean::class)
            ->build();

        exec('echo "" > storage/logs/laravel.log');

        info($maker->getCompiledHTML(true));
    }

    public function testModern()
    {
        $state  = [
            'template' => [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => [
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => '$entity-number-label', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                            ['element' => 'th', 'content' => '$entity-number', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-medium']],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => '$entity-date-label', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                            ['element' => 'th', 'content' => '$entity-date', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => '$due-date-label', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                            ['element' => 'th', 'content' => '$due-date', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => '$balance-due-label', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                            ['element' => 'th', 'content' => '$balance-due', 'properties' => ['class' => 'text-left pr-16 lg:pr-24 font-normal']],
                        ]],
                    ],
                ],
                'client-details' => [
                    'id' => 'client-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Winterfield Medical Supply', 'properties' => ['class' => 'font-medium']],
                        ['element' => 'p', 'content' => '65 Medical Complex Rd., D98'],
                        ['element' => 'p', 'content' => 'Atlanta, GA 22546'],
                        ['element' => 'p', 'content' => 'United States'],
                        ['element' => 'p', 'content' => 'demo@invoiceninja.com'],
                    ],
                ],
                'product-table' => [
                    'id' => 'product-table',
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left text-white bg-gray-900'], 'elements' => [
                            ['element' => 'th', 'content' => 'Item', 'properties' => ['class' => 'px-4 py-2']],
                            ['element' => 'th', 'content' => 'Description', 'properties' => ['class' => 'px-4 py-2']],
                            ['element' => 'th', 'content' => 'Unit cost', 'properties' => ['class' => 'px-4 py-2']],
                            ['element' => 'th', 'content' => 'Quantity', 'properties' => ['class' => 'px-4 py-2']],
                            ['element' => 'th', 'content' => 'Line total', 'properties' => ['class' => 'px-4 py-2']],
                        ]],
                        ['element' => 'tbody', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Painting service', 'properties' => ['class' => 'font-bold border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '25 hours of painting', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '885.00', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '1', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '$885.00', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Painting service', 'properties' => ['class' => 'font-bold border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '25 hours of painting', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '885.00', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '1', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                                ['element' => 'td', 'content' => '$885.00', 'properties' => ['class' => 'border-t border-b border-gray-900 px-4 py-4']],
                            ]],
                        ]],
                        ['element' => 'tfoot', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Trend and SEO report has been sent via email. This is really long text just to test the width of the elements.', 'properties' => ['class' => 'border-l-4 border-white px-4 py-4', 'colspan' => '2']],
                                ['element' => 'td', 'content' => 'Subtotal', 'properties' => ['class' => 'px-4 py-4 text-right', 'colspan' => '2']],
                                ['element' => 'td', 'content' => '$0', 'properties' => ['class' => 'px-4 py-2 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Paid to date', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$0.00', 'properties' => ['class' => 'px-4 py-2 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Discount %20', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$2.00', 'properties' => ['class' => 'px-4 py-2 text-right']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'properties' => ['class' => 'mt-8 px-4 py-2 bg-gray-900 text-white'], 'elements' => [
                                ['element' => 'td', 'content' => 'Balance due', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                                ['element' => 'td', 'content' => '$2.00', 'properties' => ['class' => 'px-4 py-2 text-right']],
                            ]],
                        ]],
                    ],
                ],
                'company-details' => [
                    'id' => 'company-details',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Ninja Sample'],
                        ['element' => 'p', 'content' => 'contact@invoiceninja.com'],
                        ['element' => 'p', 'content' => '1-800-555-Ninja'],
                    ],
                ],
                'company-address' => [
                    'id' => 'company-address',
                    'elements' => [
                        ['element' => 'p', 'content' => '123 Ninja Blvd.'],
                        ['element' => 'p', 'content' => 'NinjaLand, 97315'],
                        ['element' => 'p', 'content' => 'United States'],
                    ],
                ],
            ],
            'variables' => array_merge([], $this->state['variables']),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Modern::class)
            ->build();

        exec('echo "" > storage/logs/laravel.log');

        info($maker->getCompiledHTML(true));
    }
}
