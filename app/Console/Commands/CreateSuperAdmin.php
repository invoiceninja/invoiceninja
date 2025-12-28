<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use App\Jobs\Account\CreateAccount;
use App\Jobs\Company\CreateCompany;
use App\Jobs\User\CreateUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update super admin account from environment variables';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->error('SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD must be set in .env file');
            return 1;
        }

        // Check if user already exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update existing user
            $this->info("Updating existing user: {$email}");
            
            // Update password
            $user->password = Hash::make($password);
            $user->save();

            // Get or create account
            $account = $user->account;
            if (!$account) {
                $this->error('User exists but has no account. This should not happen.');
                return 1;
            }

            // Set super admin flag
            $account->is_super_admin = true;
            $account->save();

            // Make sure user is owner/admin of their companies
            foreach ($account->companies as $company) {
                $companyUser = $company->company_users()->where('user_id', $user->id)->first();
                if ($companyUser) {
                    $companyUser->is_owner = true;
                    $companyUser->is_admin = true;
                    $companyUser->save();
                } else {
                    $company->users()->attach($user->id, [
                        'account_id' => $account->id,
                        'is_owner' => true,
                        'is_admin' => true,
                        'is_locked' => false,
                        'permissions' => '',
                    ]);
                }
            }

            $this->info("✓ Super admin account updated successfully!");
            $this->info("  Email: {$email}");
            $this->info("  Account ID: {$account->id}");
            $this->info("  is_super_admin: " . ($account->is_super_admin ? 'true' : 'false'));

        } else {
            // Create new user and account
            $this->info("Creating new super admin account: {$email}");

            $accountData = [
                'email' => $email,
                'password' => $password,
                'first_name' => 'Super',
                'last_name' => 'Admin',
            ];

            // Use localhost IP for CLI command
            $clientIp = request()->ip() ?: '127.0.0.1';
            $account = (new CreateAccount($accountData, $clientIp))->handle();
            
            if (!$account) {
                $this->error('Failed to create account');
                return 1;
            }

            // Set super admin flag
            $account->is_super_admin = true;
            $account->save();

            $user = $account->users()->first();
            if ($user) {
                $user->password = Hash::make($password);
                $user->save();
            }

            $this->info("✓ Super admin account created successfully!");
            $this->info("  Email: {$email}");
            $this->info("  Account ID: {$account->id}");
        }

        // Ensure no other accounts are super admin
        Account::where('id', '!=', $account->id)
            ->where('is_super_admin', true)
            ->update(['is_super_admin' => false]);

        $this->info("✓ Ensured only this account has super admin privileges");

        return 0;
    }
}

