<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_transaction_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
        
            $table->string('name'); //name of rule
            $table->mediumText('rules')->nullable(); //array of rule objects
            $table->boolean('auto_convert')->default(false); //auto convert to match
            $table->boolean('matches_on_all')->default(false); //match on all rules or just one
            $table->string('applies_to')->default('CREDIT'); //CREDIT/DEBIT
        
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('category_id')->nullable();

            $table->boolean('is_deleted')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
