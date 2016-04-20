<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Company;
use App\Models\Account;

class EnterprisePlan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
	public function up() {
        
        Schema::create('companies', function($table)
        {           
            $table->increments('id');
            
            $table->enum('plan', array('pro', 'enterprise', 'white_label'))->nullable();
            $table->enum('plan_term', array('month', 'year'))->nullable();
            $table->date('plan_started')->nullable();
            $table->date('plan_paid')->nullable();
            $table->date('plan_expires')->nullable();
            
            $table->unsignedInteger('payment_id')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments');
            
            $table->date('trial_started')->nullable();
            $table->enum('trial_plan', array('pro', 'enterprise'))->nullable();
            
            $table->enum('pending_plan', array('pro', 'enterprise', 'free'))->nullable();
            $table->enum('pending_term', array('month', 'year'))->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::table('accounts', function($table)
        {
            $table->unsignedInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies');
        });
        
    
        $single_account_ids = \DB::table('users')
            ->leftJoin('user_accounts', function ($join) {
                $join->on('user_accounts.user_id1', '=', 'users.id');
                $join->orOn('user_accounts.user_id2', '=', 'users.id');
                $join->orOn('user_accounts.user_id3', '=', 'users.id');
                $join->orOn('user_accounts.user_id4', '=', 'users.id');
                $join->orOn('user_accounts.user_id5', '=', 'users.id');
            })
            ->whereNull('user_accounts.id')
            ->where(function ($query) {
                $query->whereNull('users.public_id');
                $query->orWhere('users.public_id', '=', 0);
            })
            ->lists('users.account_id');
        
        $group_accounts = \DB::select(
            'SELECT u1.account_id as account1, u2.account_id as account2, u3.account_id as account3, u4.account_id as account4, u5.account_id as account5 FROM `user_accounts`
LEFT JOIN users u1 ON (u1.public_id IS NULL OR u1.public_id = 0) AND user_accounts.user_id1 = u1.id
LEFT JOIN users u2 ON (u2.public_id IS NULL OR u2.public_id = 0) AND user_accounts.user_id2 = u2.id
LEFT JOIN users u3 ON (u3.public_id IS NULL OR u3.public_id = 0) AND user_accounts.user_id3 = u3.id
LEFT JOIN users u4 ON (u4.public_id IS NULL OR u4.public_id = 0) AND user_accounts.user_id4 = u4.id
LEFT JOIN users u5 ON (u5.public_id IS NULL OR u5.public_id = 0) AND user_accounts.user_id5 = u5.id');
    
        foreach (Account::find($single_account_ids) as $account) {
            $this->upAccounts($account);
        }
        
        foreach ($group_accounts as $group_account) {
            $this->upAccounts(null, Account::find(get_object_vars($group_account)));
        }
        
        Schema::table('accounts', function($table)
		{
			$table->dropColumn('pro_plan_paid');
            $table->dropColumn('pro_plan_trial');
		});
	}
    
    private function upAccounts($primaryAccount, $otherAccounts = array()) {
        if(!$primaryAccount) {
            $primaryAccount = $otherAccounts->first();
        }
        
        if (empty($primaryAccount)) {
            return;
        }
        
        $company = Company::create();
        if ($primaryAccount->pro_plan_paid && $primaryAccount->pro_plan_paid != '0000-00-00') {
            $company->plan = 'pro';
            $company->plan_term = 'year';
            $company->plan_started = $primaryAccount->pro_plan_paid;
            $company->plan_paid = $primaryAccount->pro_plan_paid;

            if (!Utils::isNinjaProd()) {
                $company->plan = 'white_label';
                $company->plan_term = null;
            } elseif ($company->plan_paid != '2000-01-01'/* NINJA_DATE*/) {
                $expires = DateTime::createFromFormat('Y-m-d', $primaryAccount->pro_plan_paid);
                $expires->modify('+1 year');
                $company->plan_expires = $expires->format('Y-m-d');
            } 
        }

        if ($primaryAccount->pro_plan_trial && $primaryAccount->pro_plan_trial != '0000-00-00') {
            $company->trial_started = $primaryAccount->pro_plan_trial;
            $company->trial_plan = 'pro';
        }

        $company->save();

        $primaryAccount->company_id = $company->id;
        $primaryAccount->save();
        
        if (!empty($otherAccounts)) {
           foreach ($otherAccounts as $account) {
               if ($account && $account->id != $primaryAccount->id) {
                    $account->company_id = $company->id;
                    $account->save();
               }
           }
        }
    }
    
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table('accounts', function($table)
		{
			$table->date('pro_plan_paid')->nullable();
            $table->date('pro_plan_trial')->nullable();
		});
        
        foreach (Company::all() as $company) {
            foreach ($company->accounts as $account) {
                $account->pro_plan_paid = $company->plan_paid;
                $account->pro_plan_trial = $company->trial_started;
                $account->save();
            }
        }
                
        Schema::table('accounts', function($table)
		{
			$table->dropForeign('accounts_company_id_foreign');
            $table->dropColumn('company_id');
        });
        
        Schema::dropIfExists('companies');
	}
}