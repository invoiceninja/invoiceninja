<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\EnableBanking\Transformer;

use App\Helpers\Bank\EnableBanking\EnableBanking;

class AccountTransformer
{
    /**
     * Transform EnableBanking account data to standardized format
     * @see https://enablebanking.com/docs/api/reference/#accountresource
{
    "account_id": {
    "iban": "FI0455231152453547"
    },
    "all_account_ids": [
        {
            "identification": "123456",
          "scheme_name": "BBAN"
        }
      ],
      "account_servicer": {
        "bic_fi": "string",
        "clearing_system_member_id": {
            "clearing_system_id": "NZNCC",
          "member_id": 20368
        },
        "name": "string"
      },
      "name": "string",
      "details": "string",
      "usage": "PRIV",
      "cash_account_type": "CACC",
      "product": "string",
      "currency": "string",
      "psu_status": "string",
      "credit_limit": {
        "currency": "EUR",
        "amount": "1.23"
      },
      "legal_age": true,
      "postal_address": {
        "address_type": "Business",
        "department": "Department of resources",
        "sub_department": "Sub Department of resources",
        "street_name": "Vasavagen",
        "building_number": "4",
        "post_code": "00123",
        "town_name": "Helsinki",
        "country_sub_division": "Uusimaa",
        "country": "FI",
        "address_line": [
            "Mr Asko Teirila PO Box 511",
            "39140 AKDENMAA FINLAND"
        ]
      },
      "uid": "07cc67f4-45d6-494b-adac-09b5cbc7e2b5",
      "identification_hash": "WwpbCiJhY2NvdW50IiwKImFjY291bnRfaWQiLAoiaWJhbiIKXQpd.E8GzhnnsFC7K+4e3YMYYKpyM83Zx6toXrjgcvPP/Lqc=",
      "identification_hashes": [
        "WwpbCiJhY2NvdW50IiwKImFjY291bnRfaWQiLAoiaWJhbiIKXQpd.E8GzhnnsFC7K+4e3YMYYKpyM83Zx6toXrjgcvPP/Lqc=",
        "WwpbCiJhc3BzcF9uYW1lIgpdLApbCiJhc3BzcF9jb3VudHJ5IgpdLApbCiJhY2NvdW50IiwKImFjY291bnRfaWQiLAoib3RoZXIiLAoic2NoZW1lX25hbWUiCl0sClsKImFjY291bnQiLAoiYWNjb3VudF9pZCIsCiJvdGhlciIsCiJpZGVudGlmaWNhdGlvbiIKXQpd.AOm/TULGPD4a4GdcWhR9xh0GPlPUZuB2O1S9SYFWEz0="
    ]
}
*/
    public function transform(array $enablebanking_accounts): array
    {
        $enable_banking = new EnableBanking();
        $transformed_accounts = [];
        
        foreach ($enablebanking_accounts as $account) {
            $balances = $enable_banking->getAccountBalances($account['uid']);
            // TODO(FlorientR) : Need to selection specific balance ?
            $balance = array_shift($balances['balances']);

            $transformed_accounts[] = [
                'id' => $account['uid'] ?? '',
                'account_type' => $account['cash_account_type'] ?? 'CACC',
                'account_name' => $account['name'] ?? '',
                'account_status' => 'active', // TODO(FlorientR) I don't see status field in the doc
                'account_number' => $this->maskAccountNumber($account['accout_id']['iban'] ?? ''),
                'provider_account_id' => $account['uid'] ?? '',
                'provider_id' => $account['account_servicer']['bic_fi'] ?? '',
                'provider_name' => $account['account_servicer']['name'] ?? '',
                'provider_history' => 90, // TODO(FlorientR) No history field in the doc
                'nickname' => $account['name'] ?? '',
                'current_balance' => $balance['balance_amount']['amount'] ?? 0,
                'account_currency' => $balance['balance_amount']['currency'] ?? 'EUR',
            ];
        }
        
        return $transformed_accounts;
    }
    
    /**
     * Mask account number for display
     */
    protected function maskAccountNumber(string $account_number): string
    {
        if (empty($account_number)) {
            return '';
        }

        return '**** ' . substr($account_number, -7);
    }
}