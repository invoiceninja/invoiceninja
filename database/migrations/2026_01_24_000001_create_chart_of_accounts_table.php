<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            // Company scope (Invoice Ninja is multi-tenant)
            $table->unsignedInteger('company_id')->index();

            // Account details
            $table->string('name');
            $table->string('code')->nullable(); // e.g. 1000, 4000, etc
            $table->string('type'); // asset, liability, equity, income, expense

            // Optional hierarchy
            $table->unsignedBigInteger('parent_id')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
