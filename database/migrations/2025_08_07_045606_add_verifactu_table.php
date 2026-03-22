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
        Schema::create('verifactu_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('invoice_id')->index();

            $table->string('nif');
            $table->string('date');
            $table->string('invoice_number');
            $table->string('hash');
            $table->string('previous_hash')->nullable();
            $table->string('status');

            $table->json('response')->nullable();
            $table->text('state')->nullable();
            $table->timestamps();

            // Foreign key constraints removed - data integrity handled at application level

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
