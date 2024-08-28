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

namespace App\DataMapper;

use Illuminate\Contracts\Database\Eloquent\Castable;
use App\Casts\QuickbooksSettingsCast;

/**
 * QuickbooksSettings.
 */
class QuickbooksSettings implements Castable
{
    public string $accessTokenKey;

    public string $refresh_token;

    public string $realmID;

    public int $accessTokenExpiresAt;

    public int $refreshTokenExpiresAt;
    
    /** 
     * entity client,invoice,quote,purchase_order,vendor,payment
     * sync true/false
     * update_record true/false
     * direction push/pull/birectional
     * */
    public array $settings = [
        'client' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'vendor' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'invoice' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'quote' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'purchase_order' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'product' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
        'payment' => ['sync' => true, 'update_record' => true, 'direction' => 'bidirectional'],
    ];


    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return QuickbooksSettingsCast::class;
    }

}
