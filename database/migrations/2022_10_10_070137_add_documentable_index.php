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
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['documentable_id', 'documentable_type', 'deleted_at']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['invoice_id', 'deleted_at']);
        });

        Schema::table('company_tokens', function (Blueprint $table) {
            $table->dropIndex('company_tokens_token_index');
            $table->index(['token','deleted_at']);
        });

        Schema::table('invoice_invitations', function (Blueprint $table) {
            $table->dropIndex('invoice_invitations_key_index');
            $table->index(['key','deleted_at']);
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
