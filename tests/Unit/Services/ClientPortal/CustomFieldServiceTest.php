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

namespace Tests\Unit\Services\ClientPortal;

use App\Services\ClientPortal\CustomFieldService;
use Illuminate\Validation\Rules\In;
use Tests\TestCase;

class CustomFieldServiceTest extends TestCase
{
    private CustomFieldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomFieldService();
    }

    // --- parseCustomFieldDefinition ---

    public function testParsesDateType(): void
    {
        $result = $this->service->parseCustomFieldDefinition('Birth Date|date');

        $this->assertEquals('Birth Date', $result['label']);
        $this->assertEquals('date', $result['type']);
        $this->assertEquals([], $result['options']);
    }

    public function testParsesSingleLineTextType(): void
    {
        $result = $this->service->parseCustomFieldDefinition('Contract No|single_line_text');

        $this->assertEquals('Contract No', $result['label']);
        $this->assertEquals('text', $result['type']);
        $this->assertEquals([], $result['options']);
    }

    public function testParsesSwitchType(): void
    {
        $result = $this->service->parseCustomFieldDefinition('Consent|switch');

        $this->assertEquals('Consent', $result['label']);
        $this->assertEquals('switch', $result['type']);
        $this->assertEquals([], $result['options']);
    }

    public function testParsesDropdownTypeWithTrimmedOptions(): void
    {
        $result = $this->service->parseCustomFieldDefinition('Country|SK, CZ, HU, AT');

        $this->assertEquals('Country', $result['label']);
        $this->assertEquals('dropdown', $result['type']);
        $this->assertEquals(['SK', 'CZ', 'HU', 'AT'], $result['options']);
    }

    public function testParsesNoPipeSeparatorAsTextarea(): void
    {
        $result = $this->service->parseCustomFieldDefinition('Notes');

        $this->assertEquals('Notes', $result['label']);
        $this->assertEquals('textarea', $result['type']);
        $this->assertEquals([], $result['options']);
    }

    public function testParsesEmptyStringAsTextarea(): void
    {
        $result = $this->service->parseCustomFieldDefinition('');

        $this->assertEquals('', $result['label']);
        $this->assertEquals('textarea', $result['type']);
    }

    // --- rulesForField ---

    public function testRulesForRequiredDateFieldContainsDateRule(): void
    {
        $field = ['key' => 'custom_value1', 'required' => true, 'type' => 'date', 'options' => []];

        $rules = $this->service->rulesForField($field);

        $this->assertContains('bail', $rules);
        $this->assertContains('required', $rules);
        $this->assertContains('date', $rules);
    }

    public function testRulesForOptionalDateFieldUsesNullable(): void
    {
        $field = ['key' => 'custom_value1', 'required' => false, 'type' => 'date', 'options' => []];

        $rules = $this->service->rulesForField($field);

        $this->assertContains('sometimes', $rules);
        $this->assertContains('nullable', $rules);
        $this->assertContains('date', $rules);
        $this->assertNotContains('required', $rules);
    }

    public function testRulesForDropdownFieldContainsInRule(): void
    {
        $field = ['key' => 'custom_value2', 'required' => false, 'type' => 'dropdown', 'options' => ['SK', 'CZ', 'HU']];

        $rules = $this->service->rulesForField($field);

        $hasInRule = collect($rules)->contains(fn ($r) => $r instanceof In);
        $this->assertTrue($hasInRule, 'Expected an Illuminate\\Validation\\Rules\\In rule for dropdown fields');
    }

    public function testRulesForSwitchFieldContainsInRule(): void
    {
        $field = ['key' => 'custom_value3', 'required' => true, 'type' => 'switch', 'options' => []];

        $rules = $this->service->rulesForField($field);

        $inRule = collect($rules)->first(fn ($r) => $r instanceof In);
        $this->assertNotNull($inRule, 'Expected an Illuminate\\Validation\\Rules\\In rule for switch fields');
    }

    public function testRulesForTextareaFieldContainsStringAndMaxRules(): void
    {
        $field = ['key' => 'custom_value4', 'required' => false, 'type' => 'textarea', 'options' => []];

        $rules = $this->service->rulesForField($field);

        $this->assertContains('string', $rules);
        $this->assertContains('max:1000', $rules);
    }

    // --- buildRules ---

    public function testBuildRulesReturnsMapKeyedByFieldKey(): void
    {
        $fields = [
            ['key' => 'custom_value1', 'required' => true, 'type' => 'date', 'options' => []],
            ['key' => 'custom_value2', 'required' => false, 'type' => 'dropdown', 'options' => ['A', 'B']],
        ];

        $rules = $this->service->buildRules($fields);

        $this->assertArrayHasKey('custom_value1', $rules);
        $this->assertArrayHasKey('custom_value2', $rules);
        $this->assertContains('date', $rules['custom_value1']);
    }

    public function testBuildRulesReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertEquals([], $this->service->buildRules([]));
    }
}
