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

namespace App\Http\Controllers;

use App\Http\Requests\Email\ClientEmailHistoryRequest;
use App\Http\Requests\Email\EntityEmailHistoryRequest;
use App\Models\Client;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;

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
                 ->orderBy('id', 'DESC')
                 ->cursor()
                ->filter(function ($system_log) {
                    return (isset($system_log->log['history']) && isset($system_log->log['history']['events']) && count($system_log->log['history']['events']) >= 1) !== false;
                })->map(function ($system_log) {
                    return $system_log->log['history'];
                })->values()->all();

        return response()->json($data, 200);

    }

    /**
     * May need to expand on this using
     * just the message-id and search for the
     * entity in the invitations
     */
    public function entityHistory(EntityEmailHistoryRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = SystemLog::where('company_id', $user->company()->id)
        ->where('category_id', SystemLog::CATEGORY_MAIL)
        ->whereJsonContains('log->history->entity', $request->entity)
        ->whereJsonContains('log->history->entity_id', $this->encodePrimaryKey($request->entity_id))
        ->orderBy('id', 'DESC')
        ->cursor()
        ->filter(function ($system_log) {
            return ($system_log->log['history'] && isset($system_log->log['history']['events']) && count($system_log->log['history']['events']) >= 1) !== false;
        })->map(function ($system_log) {
            return $system_log->log['history'];
        })->values()->all();

        return response()->json($data, 200);

    }
}
