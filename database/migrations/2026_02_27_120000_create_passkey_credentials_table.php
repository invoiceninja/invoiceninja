<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('passkey_credentials')) {
            return;
        }

        Schema::create('passkey_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->string('name')->nullable();
            $table->text('credential_id');
            $table->longText('credential_public_key');
            $table->unsignedBigInteger('signature_counter')->default(0);
            $table->json('transports')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'credential_id'],'passkey_user_credential_id_index');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_credentials');
    }
};
