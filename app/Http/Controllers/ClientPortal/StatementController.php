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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Statements\ShowStatementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    /**
     * Show the statement in the client portal.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        return render('statement.index');
    }

    /**
     * Show the raw stream of the PDF.
     *
     * @param ShowStatementRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|JsonResponse|\Illuminate\Http\Response|StreamedResponse
     */
    public function raw(ShowStatementRequest $request)
    {
        $pdf = $request->client()->service()->statement(
            $request->only(['start_date', 'end_date', 'show_payments_table', 'show_aging_table', 'show_credits_table', 'status'])
        );

        if ($pdf && $request->query('download')) {
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, 'statement.pdf', ['Content-Type' => 'application/pdf']);
        }

        if ($pdf) {
            return response($pdf, 200)->withHeaders([
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->json(['message' => 'Something went wrong. Please check logs.']);
    }
}
