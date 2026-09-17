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

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class SendEmailRequest extends Request
{
    use MakesHash;

    private const ENTITY_CLASSES = [
        'invoice' => Invoice::class,
        'quote' => Quote::class,
        'credit' => Credit::class,
        'recurring_invoice' => RecurringInvoice::class,
        'purchase_order' => PurchaseOrder::class,
        'purchaseOrder' => PurchaseOrder::class,
        'payment' => Payment::class,
    ];

    private string $entity_plural = 'invoices';

    public array $templates = [
        'email_template_invoice',
        'email_template_quote',
        'email_template_credit',
        'email_template_payment',
        'email_template_payment_partial',
        'email_template_statement',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'email_template_reminder_endless',
        'email_template_custom1',
        'email_template_custom2',
        'email_template_custom3',
        'email_template_purchase_order',
        'email_quote_template_reminder1',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; //required so that we can move the authorization check deeper after we have hydrated the entity
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'template' => 'bail|required|string|in:' . implode(',', $this->templates),
            'entity' => ['bail', 'required', Rule::in(self::ENTITY_CLASSES)],
            'entity_id' => ['bail', 'required', Rule::exists($this->entity_plural, 'id')->where('company_id', $user->company()->id)],
            'cc_email.*' => 'bail|sometimes|email',
        ];

    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $settings = $user->company()->settings;

        if (empty($input['template'])) {
            $input['template'] = '';
        }

        if (is_string($input['template']) && ! property_exists($settings, $input['template'])) {
            unset($input['template']);
        }

        if (array_key_exists('entity_id', $input)) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        if (isset($input['entity'])) {
            $entity_class = self::ENTITY_CLASSES[$input['entity']] ?? $input['entity'];

            if (in_array($entity_class, self::ENTITY_CLASSES, true)) {
                $input['entity'] = $entity_class;
                $this->entity_plural = (new $entity_class())->getTable();
            }
        }

        /** just in case an array is passed back from the fronted, gracefully handle it. */
        if (isset($input['cc_email']) && is_array($input['cc_email'])) {
            $input['cc_email'] = implode(',', $input['cc_email']);
        }

        if (isset($input['cc_email'])) {
            //** Accept comma or space separated list of emails and deduplicate */
            $input['cc_email'] = collect(array_merge(explode(",", $input['cc_email']), explode(" ", $input['cc_email'])))
                                ->map(function ($email) {
                                    return strtolower(trim($email));
                                })->filter(function ($email) {
                                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                                })
                                ->unique()
                                ->values()
                                ->slice(0, 4)->toArray();
        }

        if (\App\Utils\Ninja::isHosted() && !$user->account->isPaid()) {
            unset($input['subject']);
            unset($input['body']);
            unset($input['cc_email']);
        }

        $this->replace($input);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if (Ninja::isHosted() && !$user->email_verified_at) {
                $validator->errors()->add('error', ctrans('texts.verify_email'));
            }

            if (Ninja::isHosted() && !$user->account->account_sms_verified) {
                $validator->errors()->add('error', ctrans('texts.authorization_sms_failure'));
            }

            if (Ninja::isHosted() && $user->account->emailQuotaExceeded()) {
                $validator->errors()->add('error', ctrans('texts.email_quota_exceeded_subject'));
            }

            if ($user->hasExactPermission('disable_emails')) {
                $validator->errors()->add('error', ctrans('texts.disable_emails_error'));
            }

            $input = $this->all();

            if (isset($input['entity']) && array_key_exists('entity_id', $input) && in_array($input['entity'], self::ENTITY_CLASSES, true)) {
                $entity_obj = $input['entity']::whereId($input['entity_id'])->withTrashed()->company()->first();

                if (!$entity_obj || !$user->can('edit', $entity_obj)) {
                    $validator->errors()->add('error', ctrans('texts.not_authorized'));
                }
            }
        });
    }

    public function messages()
    {
        return [
            'template.in' => 'Template :input is not a valid template.',
            'entity.in' => 'Entity :input is not a valid entity.',
        ];
    }
}
