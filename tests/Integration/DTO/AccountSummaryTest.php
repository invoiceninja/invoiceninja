<?php
/**
 * Invoice Ninja (https=>//invoiceninja.com).
 *
 * @link https=>//github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https=>//invoiceninja.com)
 *
 * @license https=>//www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\DTO;

use Tests\TestCase;

/**
 * 
 */
class AccountSummaryTest extends TestCase
{
    private $data = [
      [
        "CONTAINER" => "bank",
        "providerAccountId" => 330,
        "accountName" => "Business  Acct",
        "accountStatus" => "ACTIVE",
        "accountNumber" => "1012",
        "aggregationSource" => "USER",
        "isAsset" => true,
        "balance" => [
          "currency" => "AUD",
          "amount" => 44.98,
        ],
        "id" => 19315,
        "includeInNetWorth" => true,
        "providerId" => "3857",
        "providerName" => "Bank",
        "isManual" => false,
        "availableBalance" => [
          "currency" => "AUD",
          "amount" => 34.98,
        ],
        "currentBalance" => [
          "currency" => "AUD",
          "amount" => 344.98,
        ],
        "accountType" => "CHECKING",
        "displayedName" => "after David",
        "createdDate" => "2023-01-10T08=>29=>07Z",
        "classification" => "",
        "lastUpdated" => "2023-08-01T23=>50=>13Z",
        "nickname" => "Busines Acct",
        "bankTransferCode" => [
          [
            "id" => "062",
            "type" => "BSB",
          ],
        ],
        "dataset" => [
          [
            "name" => "BASIC_AGG_DATA",
            "additionalStatus" => "AVAILABLE_DATA_RETRIEVED",
            "updateEligibility" => "ALLOW_UPDATE",
            "lastUpdated" => "2023-08-01T23=>49=>53Z",
            "lastUpdateAttempt" => "2023-08-01T23=>49=>53Z",
            "nextUpdateScheduled" => "2023-08-03T14=>45=>14Z",
          ],
        ],
      ]
    ];

    private $bad_data = [
      [
        "CONTAINER" => "bank",
        "providerAccountId" => 10090,
        "accountName" => "Business Trans Acct",
        // "accountStatus"=> "ACTIVE",
        "accountNumber" => "4402",
        "aggregationSource" => "USER",
        "isAsset" => true,
        "balance" => [
          "currency" => "AUD",
          "amount" => 34.98,
        ],
        "id" => 19315,
        "includeInNetWorth" => true,
        "providerId" => "37",
        "providerName" => "Bank",
        "isManual" => false,
        // "availableBalance"=> [
        //   "currency"=> "AUD",
        //   "amount"=> 7.98,
        // ],
        "currentBalance" => [
          "currency" => "AUD",
          "amount" => 344.98,
        ],
        "accountType" => "CHECKING",
        "displayedName" => "after David",
        "createdDate" => "2023-01-10T08=>29=>07Z",
        "classification" => "SMALL_BUSINESS",
        "lastUpdated" => "2023-08-01T23=>50=>13Z",
        "nickname" => "Busines Acct",
        "bankTransferCode" => [
          [
            "id" => "060",
            "type" => "BSB",
          ],
        ],
        "dataset" => [
          [
            "name" => "BASIC_AGG_DATA",
            "additionalStatus" => "AVAILABLE_DATA_RETRIEVED",
            "updateEligibility" => "ALLOW_UPDATE",
            "lastUpdated" => "2023-08-01T23=>49=>53Z",
            "lastUpdateAttempt" => "2023-08-01T23=>49=>53Z",
            "nextUpdateScheduled" => "2023-08-03T14=>45=>14Z",
          ],
        ],
      ]
    ];



    protected function setUp(): void
    {
        parent::setUp();
    }


    public function testTransformRefactor()
    {
        $dto = $this->transformSummary($this->data[0]);
        $this->assertEquals($dto->id, 19315);
        $this->assertEquals($dto->provider_account_id, 330);
        $this->assertEquals($dto->account_type, $this->data[0]['CONTAINER'] ?? '');
        $this->assertEquals($dto->account_status, $this->data[0]['accountStatus'] ?? '');
        $this->assertEquals($dto->account_number, $this->data[0]['accountNumber'] ?? '');
        $this->assertEquals($dto->provider_account_id, $this->data[0]['providerAccountId'] ?? '');
        $this->assertEquals($dto->provider_id, $this->data[0]['providerId'] ?? '');
        $this->assertEquals($dto->provider_name, $this->data[0]['providerName'] ?? '');
        $this->assertEquals($dto->nickname, $this->data[0]['nickname'] ?? '');
        $this->assertEquals($dto->account_name, $this->data[0]['accountName'] ?? '');
        $this->assertEquals($dto->current_balance, $this->data[0]['currentBalance']['amount'] ?? 0);
        $this->assertEquals($dto->account_currency, $this->data[0]['currentBalance']['currency'] ?? 0);

        $dto_array = (array)$dto;

        $this->assertEquals($dto_array['id'], 19315);
        $this->assertEquals($dto_array['provider_account_id'], 330);
        $this->assertEquals($dto_array['account_type'], $this->data[0]['CONTAINER'] ?? '');
        $this->assertEquals($dto_array['account_status'], $this->data[0]['accountStatus'] ?? '');
        $this->assertEquals($dto_array['account_number'], $this->data[0]['accountNumber'] ?? '');
        $this->assertEquals($dto_array['provider_account_id'], $this->data[0]['providerAccountId'] ?? '');
        $this->assertEquals($dto_array['provider_id'], $this->data[0]['providerId'] ?? '');
        $this->assertEquals($dto_array['provider_name'], $this->data[0]['providerName'] ?? '');
        $this->assertEquals($dto_array['nickname'], $this->data[0]['nickname'] ?? '');
        $this->assertEquals($dto_array['account_name'], $this->data[0]['accountName'] ?? '');
        $this->assertEquals($dto_array['current_balance'], $this->data[0]['currentBalance']['amount'] ?? 0);
        $this->assertEquals($dto_array['account_currency'], $this->data[0]['currentBalance']['currency'] ?? 0);

    }

    private function transformSummary($summary)
    {
        $dto = new \stdClass();
        $dto->id = $summary['id'] ?? 0;
        $dto->account_type = $summary['CONTAINER'] ?? '';

        $dto->account_status = $summary['accountStatus'] ?? '';
        $dto->account_number = $summary['accountNumber'] ?? '';
        $dto->provider_account_id = $summary['providerAccountId'] ?? '';
        $dto->provider_id = $summary['providerId'] ?? '';
        $dto->provider_name = $summary['providerName'] ?? '';
        $dto->nickname = $summary['nickname'] ?? '';
        $dto->account_name = $summary['accountName'] ?? '';
        $dto->current_balance = $summary['currentBalance']['amount'] ?? 0;
        $dto->account_currency = $summary['currentBalance']['currency'] ?? 0;

        return $dto;
    }
}
