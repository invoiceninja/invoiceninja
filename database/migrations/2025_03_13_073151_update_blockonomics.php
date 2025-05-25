<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Gateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gateway = Gateway::find(65);
        if ($gateway) {
            // Update the site_url and remove callbackSecret
            $fields = json_decode($gateway->fields);
            unset($fields->callbackSecret);

            $gateway->fields = json_encode($fields);
            $gateway->site_url = 'https://help.blockonomics.co/support/solutions/articles/33000291849';
            $gateway->save();
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
