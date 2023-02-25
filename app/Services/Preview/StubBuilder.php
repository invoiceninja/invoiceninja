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

namespace App\Services\Preview;

use App\Factory\GroupSettingFactory;
use App\Jobs\Util\PreviewPdf;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design as DesignModel;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

//@deprecated version
class StubBuilder
{
    use PageNumbering;
    use MakesHash;

    public $entity;

    public $entity_type;
    
    public mixed $recipient;

    public mixed $contact;

    public mixed $invitation;

    public string $recipient_string;

    public string $html;

    public string $dynamic_settings_type;

    public array $settings;

    public function __construct(public Company $company, public User $user)
    {
    }
    
    public function setEntityType($entity_type)
    {
        $this->entity_type = $entity_type;

        return $this;
    }

    public function build(): self
    {
        try {
            DB::connection(config('database.default'))->transaction(function () {
                $this->createRecipient()
                     ->initializeSettings()
                     ->createEntity()
                     ->linkRelations()
                     ->buildHtml();
            });
        } catch (\Throwable $throwable) {
            nlog("DB ERROR " . $throwable->getMessage());
                
            if (DB::connection(config('database.default'))->transactionLevel() > 0) {
                DB::connection(config('database.default'))->rollBack();
            }
        } catch(\Exception $e) {
            nlog($e->getMessage());

            if (DB::connection(config('database.default'))->transactionLevel() > 0) {
                DB::connection(config('database.default'))->rollBack();
            }
        }

        return $this;
    }

    public function getPdf(): mixed
    {
        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($this->html);
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($this->html);

            $numbered_pdf = $this->pageNumbering($pdf, $this->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        return (new PreviewPdf($this->html, $this->company))->handle();
    }

    private function initializeSettings(): self
    {
        $this->dynamic_settings_type = 'company';

        match ($this->dynamic_settings_type) {
            'company' => $this->setCompanySettings(),
            'client' => $this->setClientSettings(),
            'group' => $this->setGroupSettings(),
        };


        return $this;
    }

    private function setCompanySettings(): self
    {
        $this->company->settings = $this->settings;
        $this->company->save();

        return $this;
    }

    private function setClientSettings(): self
    {
        $this->recipient->settings = $this->settings;
        $this->recipient->save();

        return $this;
    }

    private function setGroupSettings(): self
    {
        $g = GroupSettingFactory::create($this->company->id, $this->user->id);
        $g->name = Str::random(10);
        $g->settings = $this->settings;
        $g->save();

        $this->recipient->group_settings_id = $g->id;
        $this->recipient->save();

        return $this;
    }

    public function setSettings($settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function setSettingsType($type): self
    {
        $this->dynamic_settings_type = $type;

        return $this;
    }

    private function buildHtml(): self
    {
        $html = new HtmlEngine($this->invitation);

        $design_string = "{$this->entity_type}_design_id";

        $design = DesignModel::withTrashed()->find($this->decodePrimaryKey($html->settings->{$design_string}));

        $template = new PdfMakerDesign(strtolower($design->name));

        $state = [
            'template' => $template->elements([
                'client' => $this->recipient,
                'entity' => $this->entity,
                'pdf_variables' => (array) $html->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $this->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $this->html = $maker->design($template)
                            ->build()
                            ->getCompiledHTML();

        return $this;
    }

    private function linkRelations(): self
    {
        $this->entity->setRelation('invitations', $this->invitation);
        $this->entity->setRelation($this->recipient_string, $this->recipient);
        $this->entity->setRelation('company', $this->company);
        $this->entity->load("{$this->recipient_string}.company");

        return $this;
    }

    private function createRecipient(): self
    {
        match ($this->entity_type) {
            'invoice' => $this->createClient(),
            'quote' => $this->createClient(),
            'credit' => $this->createClient(),
            'purchase_order' => $this->createVendor(),
        };

        return $this;
    }

    private function createClient(): self
    {
        $this->recipient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->recipient->id,
        ]);

        $this->recipient_string = 'client';

        return $this;
    }

    private function createVendor(): self
    {
        $this->recipient = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->user->company()->id,
        ]);

        $this->contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->recipient->id,
        ]);

        $this->recipient_string = 'vendor';

        return $this;
    }


    private function createEntity(): self
    {
        match ($this->entity_type) {
            'invoice' => $this->createInvoice(),
            'quote' => $this->createQuote(),
            'credit' => $this->createCredit(),
            'purchase_order' => $this->createPurchaseOrder(),
        };

        return $this;
    }

    private function createInvoice()
    {
        $this->entity = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->recipient->id,
            'terms' => $this->company->settings->invoice_terms,
            'footer' => $this->company->settings->invoice_footer,
            'status_id' => Invoice::STATUS_PAID,
        ]);

        $this->invitation = InvoiceInvitation::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'invoice_id' => $this->entity->id,
            'client_contact_id' => $this->contact->id,
        ]);
    }

    private function createQuote()
    {
        $this->entity->save();
    }

    private function createCredit()
    {
        $this->entity->save();
    }

    private function createPurchaseOrder()
    {
        $this->entity->save();
    }
}
