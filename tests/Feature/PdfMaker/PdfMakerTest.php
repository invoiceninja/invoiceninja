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
                            ['element' => 'th', 'content' => 'Country', 'properties' => [
                                'colspan' => 3,
                            ]],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'td', 'content' => '$company'],
                            ['element' => 'td', 'content' => '$email'],
                            ['element' => 'td', 'content' => '$country', 'elements' => [
                                ['element' => 'a', 'content' => 'Click here for a link', 'properties' => [
                                    'href' => 'https://github.com/invoiceninja/invoiceninja',
                                ]],
                            ]],
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

        $compiled = 'contact@invoiceninja.com';

        $this->assertStringContainsString($compiled, $maker->getCompiledHTML());
    }

    public function testConditionalRenderingOfElements()
    {
        $maker1 = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => [],
                ],
            ],
        ]);

        $maker1
            ->design(Business::class)
            ->build();

        $output1 = $maker1->getCompiledHTML();

        $this->assertStringContainsString('<div id="header">This is $title</div>', $output1);

        $maker2 = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => ['hidden' => "true"],
                ],
            ],
        ]);

        $maker2
            ->design(Business::class)
            ->build();

        $output2 = $maker2->getCompiledHTML();

        $this->assertStringContainsString('<div id="header" hidden="true">This is $title</div>', $output2);

        $this->assertNotSame($output1, $output2);
    }

    public function testOrderingElements()
    {
        $maker = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => [],
                    'elements' => [
                        ['element' => 'h1', 'content' => 'h1-element'],
                        ['element' => 'span', 'content' => 'span-element'],
                    ]
                ],
            ],
        ]);

        $maker
            ->design(Business::class)
            ->build();

        $node = $maker->getSectionNode('header');

        $before = [];

        foreach ($node->childNodes as $child) {
            $before[] = $child->nodeName;
        }

        $this->assertEquals('h1', $before[1]);

        $maker = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => [],
                    'elements' => [
                        ['element' => 'h1', 'content' => 'h1-element', 'order' => 1],
                        ['element' => 'span', 'content' => 'span-element', 'order' => 0],
                    ]
                ],
            ],
        ]);

        $maker
            ->design(Business::class)
            ->build();

        $node = $maker->getSectionNode('header');

        $after = [];

        foreach ($node->childNodes as $child) {
            $after[] = $child->nodeName;
        }

        $this->assertEquals('span', $after[1]);
    }
}
