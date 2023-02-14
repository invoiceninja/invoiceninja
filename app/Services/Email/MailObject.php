<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\Models\Company;
use Illuminate\Mail\Mailables\Address;

/**
 * MailObject.
 */
class MailObject
{

	public ?string $db = null;

	public array $to = [];

	public ?Address $from = null;

	public array $reply_to = [];

	public array $cc = [];

	public array $bcc = [];

	public ?string $subject = null;

	public ?string $body = null;

	public array $attachments = [];

	public array $attachment_links = [];

	public string $company_key;

	public ?object $settings = null;

	public bool $whitelabel = false;

	public ?string $logo = null;

	public ?string $signature = null;

	public ?string $greeting = null;

	public ?int $client_id = null;

	public ?int $vendor_id = null;

	public ?int $user_id = null;

	public ?int $client_contact_id = null;

	public ?int $vendor_contact_id = null;

	public ?string $email_template_body = null;

	public ?string $email_template_subject = null;

	public ?string $html_template = null;

	public ?string $text_template = 'email.template.text';

	public array $headers = [];

	public ?string $invitation_key = null;
	
	public ?int $entity_id = null;

	public ?string $entity_class = null;

	public array $variables = [];
	
	public ?string $template = null;

	public ?string $template_data = null;

	public bool $override = false;

	public ?Company $company = null;

}