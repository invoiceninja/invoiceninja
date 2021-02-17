<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Mail;

/**
 * NinjaMailerObject.
 */
class NinjaMailerObject
{

    public $mailable;

    public $company;

    public $from_user; //not yet used

    public $to_user;

    public $settings;

    public $transport; //not yet used

    /* Variable for cascading notifications */
    public $entity_string = FALSE;

    public $invitation = FALSE;

    public $template = FALSE;

    public $entity = FALSE;
    
}
