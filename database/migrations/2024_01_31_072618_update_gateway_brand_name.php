<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Gateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gateway = array();
        $gateway['name'] = 'LyfeCycle Payments';
        $gateway['provider'] = 'LyfeCycle Payments';
        $gateway['site_url'] = 'https://lyfecyclex.com';
        
        Gateway::where('id',63)->update($gateway);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
