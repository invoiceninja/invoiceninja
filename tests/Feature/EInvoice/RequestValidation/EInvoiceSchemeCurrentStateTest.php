<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use App\Http\ValidationRules\EInvoice\ValidClientScheme;
use App\Http\ValidationRules\EInvoice\ValidCompanyScheme;
use App\Http\ValidationRules\EInvoice\ValidCreditScheme;
use App\Http\ValidationRules\EInvoice\ValidInvoiceScheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use Tests\TestCase;

class EInvoiceSchemeCurrentStateTest extends TestCase
{
    public function test_rules_accept_payloads_without_their_expected_document_root(): void
    {
        $this->assertTrue($this->validatorFor(new ValidInvoiceScheme(), [])->passes());
        $this->assertTrue($this->validatorFor(new ValidCreditScheme(), [])->passes());
        $this->assertTrue($this->validatorFor(new ValidClientScheme(), [])->passes());
        $this->assertTrue($this->validatorFor(new ValidCompanyScheme(), [])->passes());
    }

    public function test_invoice_rule_accepts_an_empty_invoice(): void
    {
        $validator = $this->validatorFor(new ValidInvoiceScheme(), [
            'Invoice' => [],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_invoice_rule_accepts_unknown_properties(): void
    {
        $validator = $this->validatorFor(new ValidInvoiceScheme(), [
            'Invoice' => [
                'UnknownProperty' => [
                    'InvalidKnownTypeWouldNotMatter' => new \stdClass(),
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_invoice_rule_only_manually_checks_the_first_invoice_period(): void
    {
        $validator = $this->validatorFor(new ValidInvoiceScheme(), [
            'Invoice' => [
                'InvoicePeriod' => [
                    ['Description' => 'first day of this month|last day of this month'],
                    ['Description' => 'not a start and end expression'],
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_invoice_hydration_error_is_reported_at_a_numeric_error_path(): void
    {
        $validator = $this->validatorFor(new ValidInvoiceScheme(), [
            'Invoice' => [
                'InvoicePeriod' => 'not-an-array',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('e_invoice.0', $validator->errors()->toArray());
    }

    public function test_credit_rule_rejects_a_missing_invoice_reference_id(): void
    {
        $validator = $this->validatorFor(new ValidCreditScheme(), [
            'CreditNote' => [
                'BillingReference' => [
                    ['InvoiceDocumentReference' => []],
                ],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'e_invoice.BillingReference.0.InvoiceDocumentReference.ID',
            $validator->errors()->toArray()
        );
    }

    public function test_credit_rule_accepts_the_real_nested_reference_shape_via_manual_checks(): void
    {
        $validator = $this->validatorFor(new ValidCreditScheme(), [
            'CreditNote' => [
                'BillingReference' => [
                    [
                        'InvoiceDocumentReference' => [
                            'ID' => 'INV-123',
                            'IssueDate' => '2026-07-31',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_credit_rule_ignores_additional_billing_references(): void
    {
        $validator = $this->validatorFor(new ValidCreditScheme(), [
            'CreditNote' => [
                'BillingReference' => [
                    [
                        'InvoiceDocumentReference' => [
                            'ID' => 'INV-123',
                            'IssueDate' => '2026-07-31',
                        ],
                    ],
                    [
                        'InvoiceDocumentReference' => [
                            'ID' => '',
                            'IssueDate' => 'not-a-date',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_client_rule_accepts_empty_and_semantically_incomplete_settings(): void
    {
        $validator = $this->validatorFor(new ValidClientScheme(), [
            'Invoice' => [],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_client_rule_reports_a_recognized_date_hydration_error_at_a_numeric_path(): void
    {
        $validator = $this->validatorFor(new ValidClientScheme(), [
            'Invoice' => [
                'TaxPointDate' => 'not-a-date',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('e_invoice.0', $validator->errors()->toArray());
    }

    public function test_company_rule_accepts_empty_and_semantically_incomplete_settings(): void
    {
        $validator = $this->validatorFor(new ValidCompanyScheme(), [
            'Invoice' => [],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_company_rule_reports_a_recognized_scalar_hydration_error_at_a_numeric_path(): void
    {
        $validator = $this->validatorFor(new ValidCompanyScheme(), [
            'Invoice' => [
                'CopyIndicator' => ['not-a-boolean'],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('e_invoice.0', $validator->errors()->toArray());
    }

    private function validatorFor(ValidationRule $rule, mixed $eInvoice): LaravelValidator
    {
        return Validator::make(
            ['e_invoice' => $eInvoice],
            ['e_invoice' => [$rule]]
        );
    }
}
