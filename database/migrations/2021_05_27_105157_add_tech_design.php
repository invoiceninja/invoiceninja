<?php

use App\Models\Design;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

class AddTechDesign extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Ninja::isHosted()) {
            return Design::create(['id' => 10, 'name' => 'Tech', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'design' => '', 'is_active' => true]);
        }

        if (Design::count() !== 0) {
            return Design::create(['name' => 'Tech', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'design' => '', 'is_active' => true]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
