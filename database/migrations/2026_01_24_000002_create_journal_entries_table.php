<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // Company scope (must match companies.id type)
            $table->unsignedInteger('company_id')->index();

            // Chart of Account
            $table->unsignedBigInteger('chart_of_account_id')->index();

            // Polymorphic source (invoice, payment, expense, etc)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Amounts (only one of these should be non-zero per row)
            $table->decimal('debit', 20, 6)->default(0);
            $table->decimal('credit', 20, 6)->default(0);

            // Metadata
            $table->string('description')->nullable();
            $table->date('entry_date')->index();

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('chart_of_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('journal_entries');
    }
};
