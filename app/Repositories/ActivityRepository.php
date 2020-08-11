<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Support\Facades\Log;

/**
 * Class for activity repository.
 */
class ActivityRepository extends BaseRepository
{
    use MakesInvoiceHtml;
    /**
     * Save the Activity
     *
     * @param      stdClass  $fields  The fields
     * @param      Collection  $entity  The entity that you wish to have backed up (typically Invoice, Quote etc etc rather than Payment)
     */
    public function save($fields, $entity, $event_vars)
    {

        $activity = new Activity();

        foreach ($fields as $key => $value) {
            $activity->{$key} = $value;
        }

        if($token_id = $this->getTokenId($event_vars)){
            $fields->token_id = $token_id;
        }

        $fields->ip = $event_vars['ip'];
        $fields->is_system = $event_vars['is_system'];

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

        if (get_class($entity) == Client::class) {
            $entity->load('company');
        } elseif (get_class($entity) == User::class) {
        } else {
            $entity->load('company', 'client');
        }

        $backup->html_backup = $this->generateEntityHtml($entity->getEntityDesigner(), $entity);
        $backup->activity_id = $activity->id;
        $backup->json_backup = '';
        //$backup->json_backup = $entity->toJson();
        $backup->save();
    }

    public function getTokenId(array $event_vars)
    {

        if($event_vars['token'])
        {

            $company_token = CompanyToken::whereRaw("BINARY `token`= ?", [$event_vars['token']])->first();

            if($company_token)
                return $company_token->id;
            
        }

        return false;

    }
}
