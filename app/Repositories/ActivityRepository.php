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

/**
 * Class for activity repository.
 */
class ActivityRepository extends BaseRepository
{

	/**
	 * Save the Activity
	 *
	 * @param      stdClass  $fields  The fields
	 * @param      Collection  $entity  The entity that you wish to have backed up (typically Invoice, Quote etc etc rather than Payment)
	 */
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

	/**
	 * Creates a backup.
	 *
	 * @param      Collection $entity    The entity
	 * @param      Collection  $activity  The activity
	 */
	public function createBackup($entity, $activity)
	{
		$backup = new Backup();

		$backup->activity_id = $activity->id;
		$backup->json_backup = $entity->toJson();
		$backup->save();
	}

}