<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *1`
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\EInvoice;

use App\Services\EDocument\Standards\Validation\Peppol\InvoiceLevel;
use Closure;
use InvoiceNinja\EInvoice\EInvoice;
use Illuminate\Validation\Validator;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice;
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

            $errors = $r->validateRequest($value['Invoice'], InvoiceLevel::class);

            foreach ($errors as $key => $msg) {

                $this->validator->errors()->add(
                    "e_invoice.{$key}",
                    "{$key} - {$msg}"
                );

            }

            if (isset($value['Invoice']['InvoicePeriod'][0]['Description'])) {
                $parts = explode('|', $value['Invoice']['InvoicePeriod'][0]['Description']);
                $parts_count = count($parts);

                if ($parts_count == 2) {
                    if (!$this->isValidDateSyntax($parts[0])) {

                        $this->validator->errors()->add(
                            "e_invoice.InvoicePeriod.Description.0.StartDate",
                            ctrans('texts.invalid_date_create_syntax')
                        );

                    } elseif (!$this->isValidDateSyntax($parts[1])) {

                        $this->validator->errors()->add(
                            "e_invoice.InvoicePeriod.Description.0.EndDate",
                            ctrans('texts.invalid_date_create_syntax')
                        );

                    }

                } elseif ($parts_count == 1 && strlen($value['Invoice']['InvoicePeriod'][0]['Description']) > 2) {
                    $this->validator->errors()->add(
                        "e_invoice.InvoicePeriod.Description.0.StartDate",
                        ctrans('texts.start_and_end_date_required')
                    );
                }
            }
        }

    }

    private function isValidDateSyntax(string $date_string): bool
    {
        try {
            $date = date_create($date_string);
            return $date !== false && $date instanceof \DateTime;
        } catch (\Exception $e) {
            return false;
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
