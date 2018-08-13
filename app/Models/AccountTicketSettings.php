<?php

namespace App\Models;

use App\Libraries\Utils;
use Eloquent;
use Illuminate\Support\Facades\Log;

/**
 * Class AccountTicketSettings.
 */
class AccountTicketSettings extends Eloquent
{
    /**
     * @var array
     */
    protected $fillable = [
        'support_email_local_part',
        'from_name',
        'client_upload',
        'postmark_api_token',
        'max_file_size',
        'mime_types',
        'new_ticket_template_id',
        'close_ticket_template_id',
        'update_ticket_template_id',
        'default_priority',
        'ticket_number_start',
        'alert_new_comment',
        'alert_new_comment_email',
        'alert_ticket_assign_agent',
        'alert_ticket_assign_email',
        'alert_ticket_overdue_agent',
        'alert_ticket_overdue_email',
        'show_agent_details',
        'ticket_master_id',
    ];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function ticket_master()
    {
        return $this->hasOne('App\Models\User', 'id', 'ticket_master_id');
    }

    public function max_file_sizes()
    {
        $utils = new Utils();
        return $utils->getMaxFileUploadSizes();
    }

    public static function checkUniqueLocalPart($localPart, Account $account)
    {
        if (config('multi_db_enabled')) {
            $result = LookupAccount::where('support_email_local_part', '=', $localPart)
                                            ->where('account_key', '!=', $account->account_key)->get();
        }
        else {
            $result = AccountTicketSettings::where('support_email_local_part', '=', $localPart)
                                            ->where('account_id', '!=', $account->id)->get();
        }

        if(count($result) == 0)
            return false;
        else
            return true;
    }

}


AccountTicketSettings::updating(function (AccountTicketSettings $accountTicketSettings) {

    $dirty = $accountTicketSettings->getDirty();
    if (array_key_exists('support_email_local_part', $dirty)) {
        LookupAccount::updateSupportLocalPart($accountTicketSettings->account->account_key, $dirty['support_email_local_part']);
    }

});