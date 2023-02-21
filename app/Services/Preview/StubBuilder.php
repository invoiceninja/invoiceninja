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

use App\Models\User;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use App\Jobs\Util\PreviewPdf;
use App\Models\ClientContact;
use App\Models\VendorContact;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Response;

class StubBuilder
{
    use PageNumbering;

    public $entity;

    public $entity_type;
    
    public mixed $recipient;

    public mixed $contact;

    public mixed $invitation;

    public string $recipient_string;

    public string $html;

    public function __construct(public Company $company, public User $user){}
    
    public function setEntityType($entity_type)
    {
        $this->entity_type = $entity_type;

        return $this;
    }

    public function build(): self
    {

        try{
            DB::connection($this->company->db)->beginTransaction();

                $this->createRecipient()
                     ->createEntity()  
                     ->linkRelations()
                     ->buildHtml();     

            DB::connection($this->company->db)->rollBack();
        }
        catch(\Exception $e)
        {
            return $e->getMessage();

            DB::connection($this->company->db)->rollBack();

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

    private function buildHtml(): self
    {

        $html = new HtmlEngine($this->invitation);

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => $this->recipient,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $this->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $this->html = $maker->design($design)
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

        match($this->entity_type) {
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
        match($this->entity_type) {
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
