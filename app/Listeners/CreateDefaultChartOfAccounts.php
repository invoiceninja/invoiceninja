<?php

namespace App\Listeners;

use App\Models\Company;
use App\Models\ChartOfAccount;

class CreateDefaultChartOfAccounts
{
    public function handle(Company $company)
    {
        // Safety: do nothing if accounts already exist
        if (ChartOfAccount::where('company_id', $company->id)->exists()) {
            return;
        }

        $defaults = [
            // Assets
            ['code' => '1000', 'name' => 'Cash',                'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],

            // Liabilities
            ['code' => '2000', 'name' => 'Accounts Payable',    'type' => 'liability'],
            ['code' => '2100', 'name' => 'Tax Payable',         'type' => 'liability'],

            // Equity
            ['code' => '3000', 'name' => 'Owner Equity',        'type' => 'equity'],

            // Income
            ['code' => '4000', 'name' => 'Sales',               'type' => 'income'],

            // Expenses
            ['code' => '5000', 'name' => 'General Expenses',    'type' => 'expense'],
        ];

        foreach ($defaults as $account) {
            ChartOfAccount::create([
                'company_id' => $company->id,
                'code'       => $account['code'],
                'name'       => $account['name'],
                'type'       => $account['type'],
                'is_active'  => true,
            ]);
        }
    }
}
