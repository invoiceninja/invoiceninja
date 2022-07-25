<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;

/**
 * Class for activity repository.
 */
class ActivityRepository extends BaseRepository
{
    use MakesInvoiceHtml;
    use MakesHash;

    /**
     * Save the Activity.
     *
     * @param stdClass $fields The fields
     * @param Collection $entity The entity that you wish to have backed up (typically Invoice, Quote etc etc rather than Payment)
     * @param $event_vars
     */
    public function save($fields, $entity, $event_vars)
    {
        $activity = new Activity();

        foreach ($fields as $key => $value) {
            $activity->{$key} = $value;
        }

        if ($token_id = $this->getTokenId($event_vars)) {
            $activity->token_id = $token_id;
        }

        $activity->ip = $event_vars['ip'] ?: ' ';
        $activity->is_system = $event_vars['is_system'];

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
        if ($entity instanceof User || $entity->company->is_disabled) {
            return;
        }

        if (get_class($entity) == Invoice::class
            || get_class($entity) == Quote::class
            || get_class($entity) == Credit::class
            || get_class($entity) == RecurringInvoice::class
        ) {
            $backup = new Backup();
            $entity->load('client');
            $contact = $entity->client->primary_contact()->first();
            $backup->html_backup = $this->generateHtml($entity);
            $backup->amount = $entity->amount;
            $backup->activity_id = $activity->id;
            $backup->json_backup = '';
            $backup->save();

            $backup->storeRemotely($this->generateHtml($entity), $entity->client);
        }
    }

    public function getTokenId(array $event_vars)
    {
        if ($event_vars['token']) {
            $company_token = CompanyToken::where('token', $event_vars['token'])->first();

            if ($company_token) {
                return $company_token->id;
            }
        }

        return false;
    }

    private function generateHtml($entity)
    {
        $entity_design_id = '';
        $entity_type = '';

        if ($entity instanceof Invoice) {
            $entity_type = 'invoice';
            $entity_design_id = 'invoice_design_id';
        } elseif ($entity instanceof RecurringInvoice) {
            $entity_type = 'recurring_invoice';
            $entity_design_id = 'invoice_design_id';
        } elseif ($entity instanceof Quote) {
            $entity_type = 'quote';
            $entity_design_id = 'quote_design_id';
        } elseif ($entity instanceof Credit) {
            $entity_type = 'credit';
            $entity_design_id = 'credit_design_id';
        }

        // $entity->load('client.company');

        $entity_design_id = $entity->design_id ? $entity->design_id : $this->decodePrimaryKey($entity->client->getSetting($entity_design_id));

        $design = Design::find($entity_design_id);

        if (! $entity->invitations()->exists() || ! $design) {
            nlog("No invitations for entity {$entity->id} - {$entity->number}");

            return '';
        }

        $entity->load('client.company', 'invitations');

        $html = new HtmlEngine($entity->invitations->first()->load($entity_type, 'contact'));

        if ($design->is_custom) {
            $options = [
                'custom_partials' => json_decode(json_encode($design->design), true),
            ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'client' => $entity->client,
                'entity' => $entity,
                'pdf_variables' => (array) $entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $entity->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity->client->getSetting('all_pages_footer'),
            ],
            'process_markdown' => $entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        return $maker->design($template)
                     ->build()
                     ->getCompiledHTML(true);
    }
}
