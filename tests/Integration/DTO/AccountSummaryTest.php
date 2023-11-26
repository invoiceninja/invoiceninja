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
 * @test
 */
class AccountSummaryTest extends TestCase
{

    private $data = [
      [
        "CONTAINER"=> "bank",
        "providerAccountId"=> 330,
        "accountName"=> "Business  Acct",
        "accountStatus"=> "ACTIVE",
        "accountNumber"=> "1012",
        "aggregationSource"=> "USER",
        "isAsset"=> true,
        "balance"=> [
          "currency"=> "AUD",
          "amount"=> 44.98,
        ],
        "id"=> 19315,
        "includeInNetWorth"=> true,
        "providerId"=> "3857",
        "providerName"=> "Bank",
        "isManual"=> false,
        "availableBalance"=> [
          "currency"=> "AUD",
          "amount"=> 34.98,
        ],
        "currentBalance"=> [
          "currency"=> "AUD",
          "amount"=> 344.98,
        ],
        "accountType"=> "CHECKING",
        "displayedName"=> "after David",
        "createdDate"=> "2023-01-10T08=>29=>07Z",
        "classification"=> "",
        "lastUpdated"=> "2023-08-01T23=>50=>13Z",
        "nickname"=> "Busines Acct",
        "bankTransferCode"=> [
          [
            "id"=> "062",
            "type"=> "BSB",
          ],
        ],
        "dataset"=> [
          [
            "name"=> "BASIC_AGG_DATA",
            "additionalStatus"=> "AVAILABLE_DATA_RETRIEVED",
            "updateEligibility"=> "ALLOW_UPDATE",
            "lastUpdated"=> "2023-08-01T23=>49=>53Z",
            "lastUpdateAttempt"=> "2023-08-01T23=>49=>53Z",
            "nextUpdateScheduled"=> "2023-08-03T14=>45=>14Z",
          ],
        ],
      ]
    ];

    private $bad_data = [
      [
        "CONTAINER"=> "bank",
        "providerAccountId"=> 10090,
        "accountName"=> "Business Trans Acct",
        // "accountStatus"=> "ACTIVE",
        "accountNumber"=> "4402",
        "aggregationSource"=> "USER",
        "isAsset"=> true,
        "balance"=> [
          "currency"=> "AUD",
          "amount"=> 34.98,
        ],
        "id"=> 19315,
        "includeInNetWorth"=> true,
        "providerId"=> "37",
        "providerName"=> "Bank",
        "isManual"=> false,
        // "availableBalance"=> [
        //   "currency"=> "AUD",
        //   "amount"=> 7.98,
        // ],
        "currentBalance"=> [
          "currency"=> "AUD",
          "amount"=> 344.98,
        ],
        "accountType"=> "CHECKING",
        "displayedName"=> "after David",
        "createdDate"=> "2023-01-10T08=>29=>07Z",
        "classification"=> "SMALL_BUSINESS",
        "lastUpdated"=> "2023-08-01T23=>50=>13Z",
        "nickname"=> "Busines Acct",
        "bankTransferCode"=> [
          [
            "id"=> "060",
            "type"=> "BSB",
          ],
        ],
        "dataset"=> [
          [
            "name"=> "BASIC_AGG_DATA",
            "additionalStatus"=> "AVAILABLE_DATA_RETRIEVED",
            "updateEligibility"=> "ALLOW_UPDATE",
            "lastUpdated"=> "2023-08-01T23=>49=>53Z",
            "lastUpdateAttempt"=> "2023-08-01T23=>49=>53Z",
            "nextUpdateScheduled"=> "2023-08-03T14=>45=>14Z",
          ],
        ],
      ]
    ];



    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testWithBadDataTransformations()
    {
        $dtox = \App\Helpers\Bank\Yodlee\DTO\AccountSummary::from($this->bad_data[0]);
        $this->assertEquals(19315, $dtox->id);
        $this->assertEquals('', $dtox->account_status);
    }

    public function testTransform()
    {
        $dto = \App\Helpers\Bank\Yodlee\DTO\AccountSummary::from($this->data[0]);
        $this->assertEquals($dto->id, 19315);
    }

}
