<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPublicToDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->decimal('amount', 16, 4);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 4);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 4);
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 4);
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
}
