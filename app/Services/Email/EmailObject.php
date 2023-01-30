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

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Mail\Mailables\Address;

/**
 * EmailObject.
 */
class EmailObject
{

	public array $to = [];

	public ?Address $from = null;

	public array $reply_to = [];

	public array $cc = [];

	public array $bcc = [];

	public ?string $subject = null;

	public ?string $body = null;

	public array $attachments = [];

	public string $company_key;

	public ?object $settings = null;

	public bool $whitelabel = false;

	public ?string $logo = null;

	public ?string $signature = null;

	public ?string $greeting = null;

	public ?Client $client = null;

	public ?Vendor $vendor = null;

	public ?User $user = null;

	public ?ClientContact $client_contact = null;

	public ?VendorContact $vendor_contact = null;

	public ?string $email_template_body = null;

	public ?string $email_template_subject = null;

	public ?string $html_template = null;

	public ?string $text_template = 'email.template.text';

	public array $headers = [];

	public ?string $invitation_key = null;
	
	public ?int $entity_id = null;

	public ?string $entity_class = null;

	public array $variables = [];
	
}