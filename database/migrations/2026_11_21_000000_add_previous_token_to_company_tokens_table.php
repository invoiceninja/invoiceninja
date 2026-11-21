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
        Schema::table('company_tokens', function (Blueprint $table) {
            $table->string('previous_token')->nullable();
            $table->timestamp('previous_token_expires_at')->nullable();

            $table->index(['previous_token', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
