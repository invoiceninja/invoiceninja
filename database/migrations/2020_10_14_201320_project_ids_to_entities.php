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
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('project_id')->nullable();
        });

        Schema::table('gateways', function (Blueprint $table) {
            $table->longText('fields')->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('mark_expenses_invoiceable')->default(0);
            $table->boolean('mark_expenses_paid')->default(0);
            $table->enum('use_credits_payment', ['always', 'off', 'option'])->default('off');
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
