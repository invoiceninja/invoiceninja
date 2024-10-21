<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\EInvoice;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Http\Requests\Request;
use App\Services\EDocument\Adapters\CII\PaymentMeans;
use Illuminate\Validation\Rule;

class UpdateEInvoiceConfiguration extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin();
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'entity' => 'required|bail|in:invoice,client,company',
            'payment_means' => 'sometimes|bail|array',
            'payment_means.code' => ['required_with:payment_means', 'bail', Rule::in(PaymentMeans::getPaymentMeansCodelist())],
            'payment_means.bic' => ['bail',
                Rule::requiredIf(function () {
                        return in_array($this->input('payment_means.code'), ['58', '59', '49', '42', '30']);
                    }),
            ],
            'payment_means.iban' => ['bail', 'string', 'min:8', 'max:11',
                Rule::requiredIf(function () {
                        return in_array($this->input('payment_means.code'), ['58', '59', '49', '42', '30']);
                    }),
            ],
            'payment_means.account_name' => ['bail', 'string', 'min:15', 'max:34',
                Rule::requiredIf(function () {
                        return in_array($this->input('payment_means.code'), ['58', '59', '49', '42', '30']);
                    }),
            ],
            'payment_means.information' => ['bail', 'sometimes', 'string'],
            'payment_means.card_type' => ['bail', 'string', 'min:4',
                Rule::requiredIf(function () {
                        return in_array($this->input('payment_means.code'), ['48']);
                    }),
            ],
            'payment_means.cardholder_name' => ['bail','string', 'min:4',
                Rule::requiredIf(function () {
                        return in_array($this->input('payment_means.code'), ['48']);
                    }),
            ],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }

    public function getLevel()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return match($this->entity){
            'company' => $user->company(),
            'invoice' => Invoice::class,
            'client' => Client::class,
            default => $user->company(),
        };
    }
}