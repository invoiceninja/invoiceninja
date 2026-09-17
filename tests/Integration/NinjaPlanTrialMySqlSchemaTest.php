<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NinjaPlanTrialMySqlSchemaTest extends TestCase
{
    public function test_trial_checkpoint_contract_exists_on_real_mysql_schemas(): void
    {
        if (! config('ninja.run_trial_mysql_integration_tests')) {
            $this->markTestSkipped(
                'Set RUN_NINJA_TRIAL_MYSQL_TESTS=true and NINJA_TRIAL_TEST_ACCOUNT_CONNECTION to validate real schemas.'
            );
        }

        $accountConnection = (string) config('ninja.trial_test_account_connection');

        if ($accountConnection === '') {
            $this->fail('NINJA_TRIAL_TEST_ACCOUNT_CONNECTION is required.');
        }

        $this->assertSame(
            'mysql',
            config("database.connections.{$accountConnection}.driver")
        );
        $this->assertSame(
            'mysql',
            config('database.connections.db-ninja-01.driver')
        );
        $this->assertTrue(
            Schema::connection($accountConnection)->hasColumns('accounts', [
                'key',
                'plan',
                'plan_started',
                'plan_expires',
                'trial_started',
                'trial_plan',
                'is_trial',
                'billing_context',
            ])
        );
        $this->assertTrue(
            Schema::connection('db-ninja-01')->hasColumns('recurring_invoices', [
                'company_id',
                'client_id',
                'subscription_id',
                'status_id',
                'private_notes',
                'next_send_date',
                'is_deleted',
                'deleted_at',
            ])
        );
        $this->assertTrue(
            Schema::connection('db-ninja-01')->hasColumns('client_gateway_tokens', [
                'company_id',
                'client_id',
                'company_gateway_id',
                'gateway_type_id',
                'token',
                'gateway_customer_reference',
                'is_default',
                'is_deleted',
                'deleted_at',
            ])
        );
    }
}
