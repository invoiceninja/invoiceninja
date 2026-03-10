<?php

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Ninja::isSelfHost()) {
            
            Company::query()->update(['enable_modules' => 0]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
