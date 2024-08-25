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

/**
 * QuickbooksSettings.
 */
class QuickbooksSettings
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
     * direction push/pull/birection
     * */
    public array $settings = [];
}
