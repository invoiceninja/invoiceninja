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
        if (Schema::hasColumn('backups', 'html_backup')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->dropColumn('html_backup');
            });
        }

        Schema::table('backups', function (Blueprint $table) {
            $table->string('disk')->nullable();
        });

        Schema::table('bank_integrations', function (Blueprint $table) {
            $table->boolean('auto_sync')->default(false);
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
};
