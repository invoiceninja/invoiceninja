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

namespace Tests\Unit\Import;

use App\DataMapper\CompanySettings;
use App\Import\Transformer\BaseTransformer;
use App\Models\Company;
use Tests\TestCase;

/**
 * @test
 *
 * Proves BaseTransformer::getCustomFieldValue() resolves the company custom
 * field definition for a given key and coerces the incoming value to the type
 * declared by that definition.
 *
 * Company custom field definitions are stored as "Label|type", e.g.
 *   client1 => "Birthday|date"
 *   client2 => "Active|switch"
 *   client3 => "Reference|single_line_text"
 */
class GetCustomFieldValueTest extends TestCase
{
    private BaseTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = CompanySettings::defaults(); // country_id 840 (US)

        $company = new Company();
        $company->settings = $settings;
        $company->custom_fields = (object) [
            'client1' => 'Birthday|date',
            'client2' => 'Active|switch',
            'client3' => 'Reference|single_line_text',
            'client4' => 'Legacy',          // malformed: no type segment
            'invoice1' => 'Due|date',
        ];

        $this->transformer = new BaseTransformer($company);
    }

    /**
     * A "date" field runs the raw value through parseDate and returns Y-m-d.
     */
    public function testDateFieldIsParsedToIsoDate(): void
    {
        $this->assertSame(
            '2024-01-15',
            $this->transformer->getCustomFieldValue('client1', '01/15/2024')
        );
    }

    /**
     * The date type applies regardless of which entity the key belongs to.
     */
    public function testDateFieldOnNonClientKeyIsAlsoParsed(): void
    {
        $this->assertSame(
            '2024-01-15',
            $this->transformer->getCustomFieldValue('invoice1', '2024-01-15')
        );
    }

    /**
     * A "switch" field is coerced to a strict boolean.
     */
    public function testSwitchFieldTruthyValuesBecomeTrue(): void
    {
        foreach (['yes', 'true', '1', 'y', 'on', 'YES'] as $truthy) {
            $this->assertTrue(
                $this->transformer->getCustomFieldValue('client2', $truthy),
                "Expected '{$truthy}' to coerce to true"
            );
        }
    }

    /**
     * A "switch" field coerces falsy values to false.
     */
    public function testSwitchFieldFalsyValuesBecomeFalse(): void
    {
        foreach (['no', 'false', '0', 'n', 'off', ''] as $falsy) {
            $this->assertFalse(
                $this->transformer->getCustomFieldValue('client2', $falsy),
                "Expected '{$falsy}' to coerce to false"
            );
        }
    }

    /**
     * A non date/switch type returns the value unchanged.
     */
    public function testTextFieldReturnsValueUnchanged(): void
    {
        $this->assertSame(
            'ACME-123',
            $this->transformer->getCustomFieldValue('client3', 'ACME-123')
        );
    }

    /**
     * A key that is not defined on the company passes the value through unchanged.
     */
    public function testUndefinedKeyReturnsValueUnchanged(): void
    {
        $this->assertSame('anything', $this->transformer->getCustomFieldValue('client9', 'anything'));
    }

    /**
     * A definition without a "|type" segment (type cannot be determined) passes
     * the value through unchanged.
     */
    public function testMalformedDefinitionReturnsValueUnchanged(): void
    {
        $this->assertSame('anything', $this->transformer->getCustomFieldValue('client4', 'anything'));
    }
}
