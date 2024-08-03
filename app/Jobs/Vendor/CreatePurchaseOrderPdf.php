<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Vendor;

use App\Exceptions\FilePermissionsFailure;
use App\Libraries\MultiDB;
use App\Models\Design;
use App\Services\Pdf\PdfService;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\NumberFormatter;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\VendorHtmlEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/** @deprecated 26-10-2023 5.7.30x */
class CreatePurchaseOrderPdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use NumberFormatter;
    use MakesInvoiceHtml;
    use PdfMaker;
    use MakesHash;
    use PageNumbering;

    public $entity;

    public $company;

    public $contact;

    private $disk;

    public $invitation;

    public $entity_string = '';

    public $vendor;

    private string $path = '';

    private string $file_path = '';

    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation, $disk = null)
    {
        $this->invitation = $invitation;
        $this->company = $invitation->company;

        $this->entity = $invitation->purchase_order;
        $this->entity_string = 'purchase_order';

        $this->contact = $invitation->contact;

        $this->vendor = $invitation->contact->vendor;
        $this->vendor->load('company');

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        /** Testing this override to improve PDF generation performance */
        $ps = new PdfService($this->invitation, 'product', [
            'client' => $this->entity->client ?? false,
            'vendor' => $this->entity->vendor ?? false,
            "{$this->entity_string}s" => [$this->entity],
        ]);

        nlog("returning purchase order");

        return $ps->boot()->getPdf();

    }

    public function rawPdf()
    {
        MultiDB::setDb($this->company->db);

        /* Forget the singleton*/
        App::forgetInstance('translator');

        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->vendor->locale());

        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom())->generate($this->invitation, true);
        }

        $entity_design_id = '';

        $this->path = $this->vendor->purchase_order_filepath($this->invitation);
        $entity_design_id = 'purchase_order_design_id';

        $this->file_path = $this->path.$this->entity->numberFormatter().'.pdf';

        $entity_design_id = $this->entity->design_id ? $this->entity->design_id : $this->decodePrimaryKey('Wpmbk5ezJn');

        $design = Design::withTrashed()->find($entity_design_id);

        /* Catch all in case migration doesn't pass back a valid design */
        if (!$design) {
            /** @var \App\Models\Design $design */
            $design = Design::find(2);
        }

        $html = new VendorHtmlEngine($this->invitation);

        if ($design->is_custom) {
            $options = [
            'custom_partials' => json_decode(json_encode($design->design), true)
          ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => null,
                'vendor' => $this->vendor,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $this->entity->company->getSetting('all_pages_header'),
                'all_pages_footer' => $this->entity->company->getSetting('all_pages_footer'),
                'client' => null,
                'vendor' => $this->vendor,
                'entity' => $this->entity,
                'variables' => $variables,
            ],
            'process_markdown' => $this->entity->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        $pdf = null;

        try {
            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, $this->company);

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }
            } else {
                $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, $this->company);

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }
            }
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        if (config('ninja.log_pdf_html')) {
            nlog($maker->getCompiledHTML());
        }

        $maker = null;
        $state = null;

        return $pdf;
    }

    public function failed($e)
    {
    }
}
