<?php

namespace Tests\Feature\PdfMaker;

use Tests\TestCase;

class PdfMakerTest extends TestCase
{
    public $state = [
        'template' => [],
        'variables' => [],
    ];

    public function testDesignLoadsCorrectly()
    {
        $maker = new PdfMaker($this->state);

        $maker->design(Business::class);

        $this->assertInstanceOf(Business::class, $maker->design);
    }

    public function testHtmlDesignLoadsCorrectly()
    {
        $maker = new PdfMaker($this->state);

        $maker
            ->design(Business::class)
            ->build();

        $this->assertStringContainsString('<!-- Business -->', $maker->getCompiledHTML());
    }

    public function testGetSectionUtility()
    {
        $maker = new PdfMaker($this->state);

        $maker
            ->design(Business::class)
            ->build();

        $this->assertEquals('table', $maker->getSectionNode('product-table')->nodeName);
    }

    public function testTableAttributesAreInjected()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => [
                        'class' => 'my-awesome-class',
                        'style' => 'margin-top: 10px;',
                        'script' => 'console.log(1)',
                    ],
                ],
                'header' => [
                    'id' => 'header',
                    'properties' => [
                        'class' => 'header-class',
                    ],
                ],
            ],
            'variables' => [],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Business::class)
            ->build();

        $this->assertStringContainsString('my-awesome-class', $maker->getSection('product-table', 'class'));
        $this->assertStringContainsString('margin-top: 10px;', $maker->getSection('product-table', 'style'));
        $this->assertStringContainsString('console.log(1)', $maker->getSection('product-table', 'script'));
    }

    public function testVariablesAreReplaced()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => [
                        'class' => 'my-awesome-class',
                        'style' => 'margin-top: 10px;',
                        'script' => 'console.log(1)',
                    ],
                ],
                'header' => [
                    'id' => 'header',
                    'properties' => [
                        'class' => 'header-class',
                    ],
                ],
            ],
            'variables' => [
                '$title' => 'Invoice Ninja',
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Business::class)
            ->build();

        $this->assertStringContainsString('Invoice Ninja', $maker->getCompiledHTML());
        $this->assertStringContainsString('Invoice Ninja', $maker->getSection('header'));
    }

    public function testElementContentIsGenerated()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => [],
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => 'Company',],
                            ['element' => 'th', 'content' => 'Contact'],
                            ['element' => 'th', 'content' => 'Country'],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'td', 'content' => '$company'],
                            ['element' => 'td', 'content' => '$email'],
                            ['element' => 'td', 'content' => '$country'],
                        ]],
                    ],
                ],
            ],
            'variables' => [
                '$company' => 'Invoice Ninja',
                '$email' => 'contact@invoiceninja.com',
                '$country' => 'UK', 
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Business::class)
            ->build();

        info($maker->getCompiledHTML());

        $this->assertTrue(true);
    }
}
