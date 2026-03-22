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

    public string $baseURL;

    public string $companyName;

    public bool $requires_reconnect = false;

    public QuickbooksSync $settings;

    public function __construct(array $attributes = [])
    {
        $this->accessTokenKey = $attributes['accessTokenKey'] ?? '';
        $this->refresh_token = $attributes['refresh_token'] ?? '';
        $this->realmID = $attributes['realmID'] ?? '';
        $this->accessTokenExpiresAt = $attributes['accessTokenExpiresAt'] ?? 0;
        $this->refreshTokenExpiresAt = $attributes['refreshTokenExpiresAt'] ?? 0;
        $this->baseURL = $attributes['baseURL'] ?? '';
        $this->companyName = $attributes['companyName'] ?? '';
        $this->requires_reconnect = $attributes['requires_reconnect'] ?? false;
        $this->settings = new QuickbooksSync($attributes['settings'] ?? []);
    }

    public static function castUsing(array $arguments): string
    {
        return QuickbooksSettingsCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'accessTokenKey' => $this->accessTokenKey,
            'refresh_token' => $this->refresh_token,
            'realmID' => $this->realmID,
            'accessTokenExpiresAt' => $this->accessTokenExpiresAt,
            'refreshTokenExpiresAt' => $this->refreshTokenExpiresAt,
            'baseURL' => $this->baseURL,
            'companyName' => $this->companyName,
            'requires_reconnect' => $this->requires_reconnect,
            'settings' => $this->settings->toArray(),
        ];
    }
    
    /**
     * 
     * Patches our settings object with the 
     * selected changes we authorize.
     *
     * @param  array $changes
     * @return self
     */
    public function with(array $changes): self
    {

        $settings = $this->settings->toArray();

        $new_settings = [
            'client' => [
                'direction' => $changes['client']['direction'] ?? $this->settings->client->direction->value,
            ],
            'invoice' => [
                'direction' => $changes['invoice']['direction'] ?? $this->settings->invoice->direction->value,
            ],
            'product' => [
                'direction' => $changes['product']['direction'] ?? $this->settings->product->direction->value,
            ],
            'payment' => [
                'direction' => $changes['payment']['direction'] ?? $this->settings->payment->direction->value,
            ],
            'qb_income_account_id' => $changes['qb_income_account_id'] ?? $this->settings->qb_income_account_id,
            'automatic_taxes' => $changes['automatic_taxes'] ?? $this->settings->automatic_taxes,
        ];

        $final_settings['settings'] = array_merge($settings, $new_settings);
        
        return new self(array_merge($this->toArray(), $final_settings));
    }

    /**
     * Check if this QuickbooksSettings instance represents actual data or is just a default empty object.
     *
     * @return bool True if this has actual QuickBooks connection data, false if it's just defaults
     */
    public function isConfigured(): bool
    {
        // If accessTokenKey is set, we have a connection
        return !empty($this->accessTokenKey);
    }

    /**
     * Check if this QuickbooksSettings instance is empty (default values only).
     *
     * @return bool True if this is an empty/default instance
     */
    public function isEmpty(): bool
    {
        return empty($this->accessTokenKey)
            && empty($this->refresh_token)
            && empty($this->realmID)
            && $this->accessTokenExpiresAt === 0
            && $this->refreshTokenExpiresAt === 0
            && empty($this->baseURL);
    }
}
