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
namespace App\Http\Requests\Mailer;

use App\Http\Requests\Request;
use App\Http\Requests\Smtp\CheckSmtpRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class CheckMailerRequest extends Request
{
    private const OAUTH_MAILERS = ['gmail', 'microsoft', 'office365'];

    private const CLIENT_API_MAILERS = [
        'client_brevo',
        'client_mailgun',
        'client_postmark',
        'client_ses',
    ];

    private const CLIENT_LOCAL_MAILERS = ['smtp'];

    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mailer' => [
                'required',
                'string',
                Rule::in([...self::OAUTH_MAILERS, ...self::CLIENT_API_MAILERS, ...self::CLIENT_LOCAL_MAILERS]),
            ],
            'from_address' => [
                Rule::requiredIf(fn (): bool => !in_array($this->input('mailer'), self::OAUTH_MAILERS, true)),
                'email',
            ],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brevo_secret' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_brevo'),
                'string',
                'min:3',
            ],
            'mailgun_secret' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_mailgun'),
                'string',
                'min:3',
            ],
            'mailgun_domain' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_mailgun'),
                'string',
                'min:3',
            ],
            'mailgun_endpoint' => [
                'sometimes',
                Rule::in(['api.mailgun.net', 'api.eu.mailgun.net']),
            ],
            'postmark_secret' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_postmark'),
                'string',
                'min:3',
            ],
            'ses_access_key' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_ses'),
                'string',
                'min:3',
            ],
            'ses_secret_key' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_ses'),
                'string',
                'min:3',
            ],
            'ses_region' => [
                Rule::requiredIf(fn (): bool => $this->input('mailer') === 'client_ses'),
                'string',
                'min:3',
            ],
            'ses_topic_arn' => ['sometimes', 'nullable', 'string'],
            'smtp_host' => [Rule::requiredIf($this->input('mailer') === 'smtp'), 'string', 'min:3'],
            'smtp_port' => [Rule::requiredIf($this->input('mailer') === 'smtp'), 'integer'],
            'smtp_username' => [Rule::requiredIf($this->input('mailer') === 'smtp'), 'string', 'min:3'],
            'smtp_password' => [Rule::requiredIf($this->input('mailer') === 'smtp'), 'string', 'min:3'],
            'smtp_encryption' => ['sometimes', 'nullable', Rule::in(['tls', 'ssl'])],
            'smtp_local_domain' => ['sometimes', 'nullable', 'string'],
            'smtp_verify_peer' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->input('mailer') !== 'smtp') {
            return;
        }

        $smtpRequest = CheckSmtpRequest::createFrom($this);
        $smtpRequest->withValidator($validator);
    }
}
