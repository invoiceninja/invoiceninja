<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPublicToDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->decimal('amount', 16, 4);
        });

        Schema::table('company_gateways', function (Blueprint $table) {
            $table->enum('token_billing', ['off', 'always','optin','optout'])->default('off');
            $table->string('label', 255)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->text('meta')->nullable();
        });
        
        Schema::table('system_logs', function (Blueprint $table) {
            $table->softDeletes('deleted_at', 6);
        });
        
        Schema::create('payment_hashes', function ($table) {
            $table->increments('id');
            $table->string('hash', 255);
            $table->mediumText('data');
            $table->timestamps(6);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
