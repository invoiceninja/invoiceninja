<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

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
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('quote_id')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->date('reminder1_sent')->nullable();
            $table->date('reminder2_sent')->nullable();
            $table->date('reminder3_sent')->nullable();
            $table->date('reminder_last_sent')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('reminder1_sent')->nullable();
            $table->date('reminder2_sent')->nullable();
            $table->date('reminder3_sent')->nullable();
            $table->date('reminder_last_sent')->nullable();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->date('reminder1_sent')->nullable();
            $table->date('reminder2_sent')->nullable();
            $table->date('reminder3_sent')->nullable();
            $table->date('reminder_last_sent')->nullable();
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
};
