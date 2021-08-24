<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Statements\CreateStatementRequest;
use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;

class ClientStatementController extends BaseController
{
    use MakesHash, PdfMaker;

    /** @var \App\Models\Invoice|\App\Models\Payment */
    protected $entity;

    public function __construct()
    {
        parent::__construct();
    }

    public function statement(CreateStatementRequest $request)
    {
        $pdf = $this->createStatement($request);

        if ($pdf) {
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, 'statement.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->json(['message' => 'Something went wrong. Please check logs.']);
    }

    protected function createStatement(CreateStatementRequest $request): ?string
    {
        $invitation = InvoiceInvitation::first();

        if (count($request->getInvoices()) >= 1) {
            $this->entity = $request->getInvoices()->first();
        }

        if (count($request->getPayments()) >= 1) {
            $this->entity = $request->getPayments()->first();
        }

        $entity_design_id = 1;

        $entity_design_id = $this->entity->design_id
            ? $this->entity->design_id
            : $this->decodePrimaryKey($this->entity->client->getSetting($entity_design_id));

        $design = Design::find($entity_design_id);

        if (!$design) {
            $design = Design::find(1);
        }

        $html = new HtmlEngine($invitation);

        $options = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'show_payments_table' => $request->show_payments_table,
            'show_aging_table' => $request->show_aging_table,
        ];

        if ($design->is_custom) {
            $options['custom_partials'] = \json_decode(\json_encode($design->design), true);
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name), $options);
        }

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $this->entity->client,
                'entity' => $this->entity,
                'pdf_variables' => (array)$this->entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
                'invoices' => $request->getInvoices(),
                'payments' => $request->getPayments(),
                'aging' => $request->getAging(),
            ], \App\Services\PdfMaker\Design::STATEMENT),
            'variables' => $variables,
            'options' => [],
            'process_markdown' => $this->entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        $pdf = null;

        try {
            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
            } else {
                $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));
            }
        } catch (\Exception $e) {
            nlog(print_r($e->getMessage(), 1));
        }

        return $pdf;
    }
}
