<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use \Illuminate\Support\Facades\Log;

class AddJsonPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->longtext('permissionsV2');
        });

        $users = User::where('permissions', '!=', 0)->get();

        foreach($users as $user) {
            $user->permissionsV2 = self::returnFormattedPermissions($user->permissions);
            $user->save();
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('permissionsV2');
        });
    }

    /**
     * Transform permissions to V2
     *
     * @return json_array
     */

    public function returnFormattedPermissions($userPermission) {

    $viewPermissionEntities = [];
    $editPermissionEntities = [];
    $createPermissionEntities = [];
    
    $permissionEntities = [
        'proposal',
        'expense',
        'project',
        'vendor',
        'product',
        'task',
        'quote',
        'credit',
        'payment',
        'contact',
        'invoice',
        'client',
        'recurring_invoice',
        'reports',
    ];

    foreach($permissionEntities as $entity) {
        array_push($viewPermissionEntities, 'view_'.$entity);
        array_push($editPermissionEntities, 'edit_'.$entity);
        array_push($createPermissionEntities, 'create_'.$entity);
    }

    $returnPermissions = [];

    if(array_key_exists('create_all', $userPermission))
        $returnPermissions = array_merge($returnPermissions, $createPermissionEntities);

    if(array_key_exists('edit_all', $userPermission))
        $returnPermissions = array_merge($returnPermissions, $editPermissionEntities);

    if(array_key_exists('view_all', $userPermission))
        $returnPermissions = array_merge($returnPermissions, $viewPermissionEntities);

        return json_encode($returnPermissions);

    }
}
