<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *1`
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\EInvoice;

use App\Services\EDocument\Standards\Validation\Peppol\InvoiceLevel;
use Closure;
use InvoiceNinja\EInvoice\EInvoice;
use Illuminate\Validation\Validator;
use InvoiceNinja\EInvoice\Validation\Constraints\RelativeDateExpression;
use InvoiceNinja\EInvoice\Validation\ValidationGroups;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

/**
 * Class ValidScheme.
 */
class ValidInvoiceScheme implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     *
     * @var Validator
     */
    protected $validator;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (isset($value['Invoice'])) {

            $r = new EInvoice();

            $errors = $r->validatePayload($value['Invoice'], InvoiceLevel::class, ['Default', ValidationGroups::INVOICE_SETTINGS]);

            foreach ($errors as $violation) {
                $field_path = str_replace(['[', ']'], ['.', ''], $violation->getPropertyPath());
                $error_key = (str_starts_with($field_path, 'InvoicePeriod') ? 'e_invoice.' : 'e_invoice.Invoice.') . $field_path;
                $message = $violation->getMessage();

                if ($violation->getConstraint() instanceof RelativeDateExpression) {
                    $period_index = explode('.', $field_path)[1];
                    $date_field = $violation->getParameters()['{{ field }}'] ?? 'StartDate';
                    $error_key = "e_invoice.InvoicePeriod.Description.{$period_index}.{$date_field}";
                    $message = ctrans($violation->getCode() === RelativeDateExpression::PAIR_REQUIRED_ERROR
                        ? 'texts.start_and_end_date_required'
                        : 'texts.invalid_date_create_syntax');
                }

                $this->validator->errors()->add(
                    $error_key,
                    $message
                );

            }

        }

    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }


}
