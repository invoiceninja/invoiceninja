<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

//class Onboarding extends Migration
return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('accounts', 'is_onboarding')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->boolean('is_onboarding')->default(false);
            });
        }

        if (! Schema::hasColumn('accounts', 'onboarding')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->mediumText('onboarding')->nullable();
            });
        }
    }
};
