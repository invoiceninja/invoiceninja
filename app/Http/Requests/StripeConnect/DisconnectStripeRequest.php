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

namespace App\Http\Requests\StripeConnect;

use App\Http\Requests\Request;
use App\Models\CompanyGateway;

class DisconnectStripeRequest extends Request
{
    /**
     * @var array<int, string>
     */
    private array $stripe_keys = [
        'd14dd26a47cecc30fdd65700bfb67b34',
        'd14dd26a37cecc30fdd65700bfb55b23',
    ];

    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        $company_gateway = $this->companyGateway();

        return $company_gateway instanceof CompanyGateway
            && $user->can('edit', $company_gateway)
            && in_array($company_gateway->gateway_key, $this->stripe_keys, true);
    }

    public function companyGateway(): ?CompanyGateway
    {
        $hashed_id = $this->route('company_gateway_id');

        if (! is_string($hashed_id) || $hashed_id === '') {
            return null;
        }

        $id = $this->decodePrimaryKey($hashed_id);

        if (! is_numeric($id)) {
            return null;
        }

        return CompanyGateway::query()->withTrashed()->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
