<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
                
        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        \App\Models\CompanyGateway::withTrashed()->where('gateway_key','b9886f9257f0c6ee7c302f1c74475f6c')
        ->cursor()
        ->each(function ($cg){
            $cg->setConfigField('verifyBankAccount',false);
        });

        $gateway = \App\Models\Gateway::where('key','b9886f9257f0c6ee7c302f1c74475f6c')->first();
                
        if($gateway)
        {
            $fields = json_decode($gateway->fields);
            $fields->verifyBankAccount = false;
            $gateway->fields = json_encode($fields);

            $gateway->save();

            \Illuminate\Support\Facades\Cache::forget('gateways');
        }

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
