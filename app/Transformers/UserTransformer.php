<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;

class UserTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
        //'company_user'
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'companies',
        'company_users',
        'company_user',
    ];

    public function transform(User $user)
    {
        return [
            'id' => $this->encodePrimaryKey($user->id),
            'first_name' => $user->first_name ?: '',
            'last_name' => $user->last_name ?: '',
            'email' => $user->email ?: '',
            'last_login' => Carbon::parse($user->last_login)->timestamp,
            'created_at' => (int) $user->created_at,
            'updated_at' => (int) $user->updated_at,
            'archived_at' => (int) $user->deleted_at,
            'is_deleted' => (bool) $user->is_deleted,
            'phone' => $user->phone ?: '',
            'email_verified_at' => $user->getEmailVerifiedAt(),
            'signature' => $user->signature ?: '',
            'custom_value1' => $user->custom_value1 ?: '',
            'custom_value2' => $user->custom_value2 ?: '',
            'custom_value3' => $user->custom_value3 ?: '',
            'custom_value4' => $user->custom_value4 ?: '',
            'oauth_provider_id' => (string) $user->oauth_provider_id,
            'last_confirmed_email_address' => (string) $user->last_confirmed_email_address ?: '',
            'google_2fa_secret' => (bool) $user->google_2fa_secret,
            'has_password' => (bool) empty($user->password) ? false : true,
            'oauth_user_token' => empty($user->oauth_user_token) ? '' : '***',
        ];
    }

    public function includeCompanies(User $user)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeCollection($user->companies, $transformer, Company::class);
    }

    public function includeToken(User $user)
    {
        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeItem($user->token, $transformer, CompanyToken::class);
    }

    public function includeCompanyTokens(User $user)
    {
        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeCollection($user->tokens, $transformer, CompanyToken::class);
    }

    public function includeCompanyUsers(User $user)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeCollection($user->company_users, $transformer, CompanyUser::class);
    }

    public function includeCompanyUser(User $user)
    {
        if (! $user->company_id && request()->header('X-API-TOKEN')) {
            $company_token = CompanyToken::where('token', request()->header('X-API-TOKEN'))->first();
            $user->company_id = $company_token->company_id;
        }

        $transformer = new CompanyUserTransformer($this->serializer);

        $cu = $user->company_users()->whereCompanyId($user->company_id)->first();

        return $this->includeItem($cu, $transformer, CompanyUser::class);
    }
}
