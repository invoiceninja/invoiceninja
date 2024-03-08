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

namespace App\Http\Controllers;

use App\Http\Requests\Statements\CreateStatementRequest;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\Response;

class ClientStatementController extends BaseController
{
    use MakesHash;
    use PdfMaker;

    /** @var \App\Models\Invoice|\App\Models\Payment */
    protected $entity;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CreateStatementRequest $request
     * @return Response
     */
    public function statement(CreateStatementRequest $request)
    {
        $send_email = false;

        if ($request->has('send_email') && $request->send_email == 'true') {
            $send_email = true;
        }

        $pdf = $request->client()->service()->statement(
            $request->only(['start_date', 'end_date', 'show_payments_table', 'show_aging_table', 'status', 'show_credits_table', 'template']),
            $send_email
        );

        if ($send_email) {
            return response()->json(['message' => ctrans('texts.email_queued')], 200);
        }

        if ($pdf) {
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, ctrans('texts.statement').'.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->json(['message' => ctrans('texts.error_title')], 500);
    }
}
