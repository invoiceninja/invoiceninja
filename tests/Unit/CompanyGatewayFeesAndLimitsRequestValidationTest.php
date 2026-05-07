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

namespace Tests\Unit;

use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CompanyGatewayFeesAndLimitsRequestValidationTest extends TestCase
{
    public function test_fees_and_limits_json_string_fails_array_rule(): void
    {
        $validator = Validator::make(
            ['fees_and_limits' => '{"1":{"min_limit":"-1","max_limit":"-1","fee_amount":0,"fee_percent":0}}'],
            ['fees_and_limits' => ['bail', 'sometimes', 'nullable', 'array', new ValidCompanyGatewayFeesAndLimitsRule()]],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fees_and_limits', $validator->errors()->messages());
    }

    public function test_fees_and_limits_array_passes_array_rule_and_custom_rule(): void
    {
        $validator = Validator::make(
            [
                'fees_and_limits' => [
                    '1' => [
                        'min_limit' => '-1',
                        'max_limit' => '-1',
                        'fee_amount' => 0,
                        'fee_percent' => 0,
                        'fee_cap' => 0,
                        'fee_tax_name1' => '',
                        'fee_tax_rate1' => 0,
                        'fee_tax_name2' => '',
                        'fee_tax_rate2' => 0,
                        'fee_tax_name3' => '',
                        'fee_tax_rate3' => 0,
                        'adjust_fee_percent' => false,
                        'is_enabled' => true,
                    ],
                ],
            ],
            ['fees_and_limits' => ['bail', 'sometimes', 'nullable', 'array', new ValidCompanyGatewayFeesAndLimitsRule()]],
        );

        $this->assertFalse($validator->fails());
    }
}
