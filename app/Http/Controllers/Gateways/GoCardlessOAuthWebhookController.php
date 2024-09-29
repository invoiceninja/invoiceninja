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

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoCardless\WebhookRequest;
use App\Models\CompanyGateway;
use App\Repositories\CompanyRepository;
use Illuminate\Support\Arr;

class GoCardlessOAuthWebhookController extends Controller
{
    public function __construct(
        protected CompanyRepository $company_repository,
    ) {
    }

    public function __invoke(WebhookRequest $request)
    {
        foreach ($request->events as $event) {
            nlog($event['action']);

            $e = Arr::dot($event);

            if ($event['action'] === 'disconnected') {

                /** @var \App\Models\CompanyGateway $company_gateway */
                $company_gateway = CompanyGateway::query()
                    ->whereJsonContains('config->account_id', $e['links.organisation'])
                    ->firstOrFail();

                $current = $company_gateway->getConfigField('__current');

                if ($current) {
                    $company_gateway->setConfig($current);
                    $company_gateway->save();
                }

                $this->company_repository->archive($company_gateway);
            }
        }
    }
}
