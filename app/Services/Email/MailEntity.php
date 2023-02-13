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

use App\Libraries\MultiDB;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MailEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Company $company;

    public function __construct(protected $invitation, private ?string $db, private ?string $reminder_template = null, private ?string $template_data = null, private bool $override = false)
    {

        $this->invitation = $invitation;

        $this->db = $db;

        $this->reminder_template = $reminder_template;

        $this->template_data = $template_data;

        $this->override = $override;

        // $this->entity_string = $this->resolveEntityString();

        // $this->entity = $invitation->{$this->entity_string};

        // $this->settings = $invitation->contact->client->getMergedSettings();

        // $this->reminder_template = $reminder_template ?: $this->entity->calculateTemplate($this->entity_string);

        // $this->html_engine = new HtmlEngine($invitation);

        // $this->template_data = $template_data;

    }

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        //construct mailable

        //construct mailer

    }

}
