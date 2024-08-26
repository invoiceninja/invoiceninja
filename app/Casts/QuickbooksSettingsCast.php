<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use App\DataMapper\QuickbooksSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class QuickbooksSettingsCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        $data = json_decode($value, true);

        if(!is_array($data))
            return null;

        $qb = new QuickbooksSettings();
        $qb->accessTokenKey =  $data['accessTokenKey'];
        $qb->refresh_token =  $data['refresh_token'];
        $qb->realmID =  $data['realmID'];
        $qb->accessTokenExpiresAt =  $data['accessTokenExpiresAt'];
        $qb->refreshTokenExpiresAt =  $data['refreshTokenExpiresAt'];
        $qb->settings = $data['settings'] ?? [];
        
        return $qb;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [
            $key => json_encode([
                'accessTokenKey' => $value->accessTokenKey,
                'refresh_token' => $value->refresh_token,
                'realmID' => $value->realmID,
                'accessTokenExpiresAt' => $value->accessTokenExpiresAt,
                'refreshTokenExpiresAt' => $value->refreshTokenExpiresAt,
                'settings' => $value->settings,
            ])
        ];
    }
}
