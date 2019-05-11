<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Activity;
use App\Models\Backup;

class ActivityRepository extends BaseRepository
{

	public function save($fields, $entity)
	{
		$activity = new Activity();

		$activity->is_system = app()->runningInConsole();
        $activity->ip = request()->getClientIp();

        foreach($fields as $key => $value) {

        	$activity->{$key} = $value;
        }

		$activity->save();

		$this->createBackup($entity, $activity);
	}

	public function createBackup($entity, $activity)
	{
		$backup = new Backup();

		$backup->activity_id = $activity->id;
		$backup->json_backup = $entity->toJson();
		$backup->save();
	}

}