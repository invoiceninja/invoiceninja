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

use App\Models\Quote;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Http\Requests\Email\ClientEmailHistoryRequest;
use App\Http\Requests\Email\EntityEmailHistoryRequest;

class EmailHistoryController extends BaseController
{
    use MakesHash;

    public function __construct()
    {
    }

    public function clientHistory(ClientEmailHistoryRequest $request, Client $client)
    {
        $data = SystemLog::where('client_id', $client->id)
                 ->where('category_id', SystemLog::CATEGORY_MAIL)
                 ->orderBy('id','DESC')
                 ->map(function ($system_log){
                    if($system_log->log['history'] ?? false) {
                        return json_decode($system_log->log['history'], true);
                    }
                 });

        return response()->json($data, 200);

    }

    public function entityHistory(EntityEmailHistoryRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = SystemLog::where('company_id', $user->company()->id)
                ->where('category_id', SystemLog::CATEGORY_MAIL)
                ->whereJsonContains('log->history->entity_id', $this->encodePrimaryKey($request->entity_id))
                ->orderBy('id', 'DESC')
                ->map(function ($system_log) {
                    if($system_log->log['history'] ?? false) {
                        return json_decode($system_log->log['history'], true);
                    }
                });

        return response()->json($data, 200);

    }
}