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

        Schema::table('company_gateways', function (Blueprint $table) {
            $table->string('token_billing')->default('off');
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
