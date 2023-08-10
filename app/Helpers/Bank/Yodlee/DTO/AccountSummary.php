<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Yodlee\DTO;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Illuminate\Support\Collection;

/**
 * {#2983
    "account": [
      {#2979
        "CONTAINER": "bank",
        "providerAccountId": 10058190,
        "accountName": "Business Trans Acct",
        "accountStatus": "ACTIVE",
        "accountNumber": "1011 4402",
        "aggregationSource": "USER",
        "isAsset": true,
        "balance": {#2978
          "currency": "AUD",
          "amount": 308544.98,
        },
        "id": 10139315,
        "includeInNetWorth": true,
        "providerId": "3857",
        "providerName": "Commonwealth Bank",
        "isManual": false,
        "availableBalance": {#2966
          "currency": "AUD",
          "amount": 309044.98,
        },
        "currentBalance": {#2982
          "currency": "AUD",
          "amount": 308544.98,
        },
        "accountType": "CHECKING",
        "displayedName": "after David",
        "createdDate": "2023-01-10T08:29:07Z",
        "classification": "SMALL_BUSINESS",
        "lastUpdated": "2023-08-01T23:50:13Z",
        "nickname": "Business Trans Acct",
        "bankTransferCode": [
          {#2976
            "id": "062020",
            "type": "BSB",
          },
        ],
        "dataset": [
          {#2971
            "name": "BASIC_AGG_DATA",
            "additionalStatus": "AVAILABLE_DATA_RETRIEVED",
            "updateEligibility": "ALLOW_UPDATE",
            "lastUpdated": "2023-08-01T23:49:53Z",
            "lastUpdateAttempt": "2023-08-01T23:49:53Z",
            "nextUpdateScheduled": "2023-08-03T14:45:14Z",
          },
        ],
      },
    ],
  }
 */
class AccountSummary extends Data
{
        public ?int $id;

        #[MapInputName('CONTAINER')]
        public ?string $account_type; 
        
        #[MapInputName('accountName')]
        public ?string $account_name; 

        #[MapInputName('accountStatus')]
        public ?string $account_status; 

        #[MapInputName('accountNumber')]
        public ?string $account_number;

        #[MapInputName('providerAccountId')]
        public int $provider_account_id; 

        #[MapInputName('providerId')]
        public ?string $provider_id;

        #[MapInputName('providerName')]
        public ?string $provider_name; 

        public ?string $nickname; 
        
        public ?float $current_balance;         
        public ?string $account_currency;

        public static function prepareForPipeline(Collection $properties) : Collection
        {
            
            $properties->put('current_balance', (array)$properties['currentBalance']['amount'] ?? '');
            $properties->put('account_currency', (array)$properties['currentBalance']['currency'] ?? '');

            return $properties;
        }
}