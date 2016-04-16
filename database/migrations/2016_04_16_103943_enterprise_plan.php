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
	public function up()
	{
		Schema::create('companies', function($table)
        {           
            $table->increments('id');
            
            $table->enum('plan', array('pro', 'enterprise'))->nullable();
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
            
            // Used when a user has started changing a plan but hasn't finished paying yet
            $table->enum('temp_pending_plan', array('pro', 'enterprise'))->nullable();
            $table->enum('temp_pending_term', array('month', 'year'))->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::table('accounts', function($table)
        {
            $table->unsignedInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies');
        });
        
        foreach (Account::all() as $account) {
            $company = Company::create();
            if ($account->pro_plan_paid && $account->pro_plan_paid != '0000-00-00') {
                $company->plan = 'pro';
                $company->plan_term = 'year';
                $company->plan_started = $account->pro_plan_paid;
                $company->plan_paid = $account->pro_plan_paid;
                
                if ($company->plan_paid != NINJA_DATE) {
                    $expires = DateTime::createFromFormat('Y-m-d', $account->pro_plan_paid);
                    $expires->modify('+1 year');
                    $company->plan_expires = $expires->format('Y-m-d');
                }
            }
            
            if ($account->pro_plan_trial && $account->pro_plan_trial != '0000-00-00') {
                $company->trial_started = $account->pro_plan_trial;
                $company->trial_plan = 'pro';
            }
                
            $company->save();
            
            $account->company_id = $company->id;
            $account->save();
        }
        
        /*Schema::table('accounts', function($table)
		{
			$table->dropColumn('pro_plan_paid');
            $table->dropColumn('pro_plan_trial');
		});*/
	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        /*Schema::table('accounts', function($table)
		{
			$table->date('pro_plan_paid')->nullable();
            $table->date('pro_plan_trial')->nullable();
		});*/
        
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
        
        Schema::drop('companies');
	}
}