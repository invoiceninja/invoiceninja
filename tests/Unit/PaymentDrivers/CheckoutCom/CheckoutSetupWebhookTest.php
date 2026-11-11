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

namespace Tests\Unit\PaymentDrivers\CheckoutCom;

use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutCom\CheckoutSetupWebhook;
use App\PaymentDrivers\CheckoutCom\Webhook;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use PHPUnit\Framework\TestCase;

class CheckoutSetupWebhookTest extends TestCase
{
    public function testFindAuthenticationWorkflowReturnsMatch(): void
    {
        $job = new CheckoutSetupWebhook('company_key', 1);

        $found = $job->findAuthenticationWorkflow([
            'data' => [
                ['name' => 'Other_Workflow'],
                ['name' => 'Invoice_Ninja_3DS_Workflow', 'id' => 'wf_123'],
            ],
        ]);

        $this->assertSame('wf_123', $found['id'] ?? null);
    }

    public function testFindAuthenticationWorkflowReturnsNullWhenMissing(): void
    {
        $job = new CheckoutSetupWebhook('company_key', 1);

        $this->assertNull($job->findAuthenticationWorkflow([
            'data' => [
                ['name' => 'Other_Workflow'],
            ],
        ]));
    }

    public function testFindAuthenticationWorkflowHandlesNullPayloads(): void
    {
        $job = new CheckoutSetupWebhook('company_key', 1);

        $this->assertNull($job->findAuthenticationWorkflow([]));
        $this->assertNull($job->findAuthenticationWorkflow(['data' => null]));
        $this->assertNull($job->findAuthenticationWorkflow(['data' => [null, ['name' => null]]]));
    }

    public function testGetWorkFlowsReturnsEmptyDataWhenGatewayIsNull(): void
    {
        $driver = new CheckoutComPaymentDriver(new CompanyGateway());
        $driver->gateway = null;

        $this->assertSame(['data' => []], (new Webhook($driver))->getWorkFlows());
    }
}
