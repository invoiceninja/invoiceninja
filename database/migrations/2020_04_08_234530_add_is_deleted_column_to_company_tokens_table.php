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
        /* add ability of companytokens to be deleted.*/
        Schema::table('company_tokens', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(0);
        });

        /* add ability of external APIs to be triggered after a model has been created nor updated */
        Schema::create('subscriptions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('event_id')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->string('target_url');
            $table->enum('format', ['JSON', 'UBL'])->default('JSON');
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->index(['event_id', 'company_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->renameColumn('google_analytics_url', 'google_analytics_key');
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
