<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bank_integrations', function (Blueprint $table) {
            $table->string('enablebanking_session_id')->nullable();
            $table->string('enablebanking_account_id')->nullable();
            $table->datetime('enablebanking_session_expired_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bank_integrations', function (Blueprint $table) {
            $table->dropColumn(['enablebanking_session_id', 'enablebanking_account_id', 'enablebanking_session_expired_at']);
        });
    }
};