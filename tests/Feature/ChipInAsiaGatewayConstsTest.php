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

namespace Tests\Feature;

use App\Models\CompanyGateway;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Asserts that the CHIP gateway is registered in
 * CompanyGateway::$gateway_consts so the system_logs() relationship
 * returns CHIP-tagged log entries.
 *
 * Per reviewer feedback, the convention (also used by LawPay) is:
 *   1. Add the gateway's seeded 'key' (string) to $gateway_consts.
 *   2. Map it to the matching SystemLog::TYPE_* constant.
 * Without this entry, $companyGateway->system_logs returns nothing
 * for CHIP gateways because $gateway_consts[$key] is null and
 * `where('type_id', null)` matches no rows.
 */
class ChipInAsiaGatewayConstsTest extends TestCase
{
    use DatabaseTransactions;

    public function testCompanyGatewayConstsHasChipInAsiaEntry(): void
    {
        $cg = new CompanyGateway();

        $this->assertArrayHasKey('c7a8e2f1b4d90635a3f8e1c9b2d4a6e0', $cg->gateway_consts);
    }

    public function testCompanyGatewayConstsMapsChipInAsiaToItsSystemLogType(): void
    {
        $cg = new CompanyGateway();

        $this->assertSame(
            SystemLog::TYPE_CHIPINASIA,
            $cg->gateway_consts['c7a8e2f1b4d90635a3f8e1c9b2d4a6e0'] ?? null
        );
    }
}
