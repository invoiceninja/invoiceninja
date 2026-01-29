<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reconciliation_periods', function (Blueprint $table) {
            $table->id();

            // Company scope
            $table->unsignedInteger('company_id')->index();

            // Period range
            $table->date('from_date');
            $table->date('to_date');

            // Lock timestamp (null = not locked)
            $table->timestamp('locked_at')->nullable();

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
        Schema::dropIfExists('reconciliation_periods');
    }
};
