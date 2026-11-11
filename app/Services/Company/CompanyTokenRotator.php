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

namespace App\Services\Company;

use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CompanyTokenRotator
{
    private const ROTATION_INTERVAL_DAYS = 30;

    public function rotateDueTokensForUser(User $user): int
    {
        return (int) CompanyToken::query()
            ->where('user_id', $user->id)
            ->where('account_id', $user->account_id)
            ->where('is_system', true)
            ->where('created_at', '<=', $this->threshold())
            ->orderBy('id')
            ->get()
            ->sum(fn (CompanyToken $company_token) => $this->rotateIfDue($company_token) ? 1 : 0);
    }

    public function rotateIfDue(CompanyToken $company_token): bool
    {
        if (! $this->isDue($company_token)) {
            return false;
        }

        $updated = CompanyToken::query()
            ->whereKey($company_token->id)
            ->where('is_system', true)
            ->where('token', $company_token->token)
            ->where('created_at', $company_token->getRawOriginal('created_at'))
            ->update([
                'token' => Str::random(64),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $updated === 1;
    }

    public function isDue(CompanyToken $company_token): bool
    {
        return (bool) $company_token->is_system
            && (int) $company_token->created_at <= $this->threshold()->timestamp;
    }

    private function threshold(): Carbon
    {
        return now()->subDays(self::ROTATION_INTERVAL_DAYS);
    }
}
