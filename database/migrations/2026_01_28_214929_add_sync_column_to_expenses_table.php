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
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('sync')->nullable();
        });


        Schema::table('expense_categories', function (Blueprint $table) {
            $table->text('sync')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
    }
};
