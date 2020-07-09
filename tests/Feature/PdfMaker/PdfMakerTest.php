<?php

namespace Tests\Feature\PdfMaker;

use Tests\TestCase;

class PdfMakerTest extends TestCase
{
    public function testDesignLoadsCorrectly()
    {
        $maker = new PdfMaker();

        $maker->design(Business::class);

        $this->assertInstanceOf(Business::class, $maker->design);
    }

    public function testHtmlDesignLoadsCorrectly()
    {
        $maker = new PdfMaker();

        $maker
            ->design(Business::class)
            ->build();

        $this->assertStringContainsString('<!-- Business -->', $maker->html);
    }

    public function testGetSectionUtility()
    {
        $maker = new PdfMaker();

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
                ['id' => 'header', 'variable' => '$title', 'value' => 'Invoice Ninja'],
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Business::class)
            ->build();

        $this->assertStringContainsString('Invoice Ninja', $maker->getCompiledHTML());
        $this->assertStringContainsString('Invoice Ninja', $maker->getSection('header'));
    }
}
