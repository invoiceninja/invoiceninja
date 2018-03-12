<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSlackNotifications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('slack_webhook_url')->nullable();

            $table->string('accepted_terms_version')->nullable();
            $table->timestamp('accepted_terms_timestamp')->nullable();
            $table->string('accepted_terms_ip')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('slack_webhook_url');

            $table->dropColumn('accepted_terms_version');
            $table->dropColumn('accepted_terms_timestamp');
            $table->dropColumn('accepted_terms_ip');
        });
    }
}
