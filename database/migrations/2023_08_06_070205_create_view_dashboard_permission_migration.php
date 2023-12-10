<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \App\Models\CompanyUser::query()->where('is_admin', 0)->cursor()->each(function ($cu) {
            $permissions = $cu->permissions;

            if (!$permissions || strlen($permissions) == 0) {
                $permissions = 'view_dashboard';
                $cu->permissions = $permissions;
                $cu->save();
            } else {
                $permissions_array = explode(',', $permissions);

                $permissions_array[] = 'view_dashboard';

                $modified_permissions_string = implode(",", $permissions_array);

                $cu->permissions = $modified_permissions_string;
                $cu->save();
            }
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
