<?php

namespace App\Models;

use App\Libraries\Utils;
use Eloquent;

/**
 * Class AccountTicketSettings.
 */
class AccountTicketSettings extends Eloquent
{
    /**
     * @var array
     */
    protected $fillable = [
        'local_part',
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
        'alert_new_ticket',
        'alert_new_ticket_email',
        'alert_new_comment',
        'alert_new_comment_email',
        'alert_ticket_assign_agent',
        'alert_ticket_assign_email',
        'alert_ticket_transfer_agent',
        'alert_ticket_transfer_email',
        'alert_ticket_overdue_agent',
        'alert_ticket_overdue_email',
        'show_agent_details',
    ];

    public function ticket_master()
    {
        return $this->hasOne('App\Models\User', 'id', 'ticket_master_id');
    }

    public function max_file_sizes()
    {
        $utils = new Utils();
        return $utils->getMaxFileUploadSizes();
    }



}
