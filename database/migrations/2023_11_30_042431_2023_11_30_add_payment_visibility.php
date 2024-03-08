<?php

use App\Models\Gateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Gateway::whereIn('id', [60, 15])->update(['visible' => 1]);

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
