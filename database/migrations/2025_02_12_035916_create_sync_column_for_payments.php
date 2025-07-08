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
            
        Schema::table('payments', function (Blueprint $table) {
                        
            if (!Schema::hasColumn('payments', 'sync')) {
                $table->text('sync')->nullable();
            }

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
    }
};
