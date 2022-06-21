<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('recurring_invoice_id')->nullable();
        });

        if (! Schema::hasColumn('companies', 'show_task_end_date')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->boolean('show_task_end_date')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
