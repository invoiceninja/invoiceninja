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
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Mail\Mailables\Address;

/**
 * EmailObject.
 */
class EmailObject
{
    /** @var array[string] $args */
    public array $to = [];

    public ?Address $from = null;

    public array $reply_to = [];

    /** @var array[Address] $args */
    public array $cc = [];

    /** @var array[Address] $args */
    public array $bcc = [];

    public ?string $subject = null;

    public ?string $body = null;

    public string $text_body = '';

    /** @var array{key: value} $args */
    public array $attachments = [];

    public string $company_key;

    public Company $company;

    public ?object $settings = null;

    public bool $whitelabel = false;

    public ?string $logo = null;

    public ?string $signature = null;

    public ?string $greeting = null;

    public ?int $invitation_id = null;

    public InvoiceInvitation | QuoteInvitation | CreditInvitation | PurchaseOrderInvitation | null $invitation;

    public ?int $entity_id = null;

    public Invoice | Quote | Credit | PurchaseOrder | Payment | null $entity;

    public ?int $client_id = null;

    public ?Client $client;

    public ?int $vendor_id = null;

    public ?Vendor $vendor;

    public ?int $user_id = null;

    public ?User $user;

    public ?int $client_contact_id = null;

    public ClientContact | VendorContact | null  $contact;

    public ?int $vendor_contact_id = null;

    public ?string $email_template_body = null;

    public ?string $email_template_subject = null;

    public ?string $html_template = null;

    public ?string $text_template = 'email.template.text';

    /** @var array{key: value} $args */
    public array $headers = [];

    public ?string $entity_class = null;

    /** @var array{key: value} $args */
    public array $variables = [];

    public bool $override = false;

    public ?string $invitation_key = null;

    /** @var array[int] $args */
    public array $documents = [];

    public ?string $template = null; //invoice //quote //reminder1

    public array $links = [];

    public ?string $button = null;

    public ?string $url = null;
}
