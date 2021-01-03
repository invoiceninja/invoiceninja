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

namespace App\DataMapper;

/**
 * EmailSpooledForSend.
 *
 * Stubbed class used to store the meta data
 * for an email that was unable to be sent
 * for a reason such as:
 *
 *  - Quota exceeded
 *  - SMTP issues
 *  - Upstream connectivity
 */
class EmailSpooledForSend
{
    public $entity_name;

    public $invitation_key = '';

    public $reminder_template = '';

    public $subject = '';

    public $body = '';
}
