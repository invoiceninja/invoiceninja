<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubscriptionFormat extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function ($table) {
            $table->enum('format', ['JSON', 'UBL'])->default('JSON');
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('ubl_email_attachment')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function ($table) {
            $table->dropColumn('format');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('ubl_email_attachment');
        });
    }
}
