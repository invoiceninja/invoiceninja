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
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Utils\Ninja;
use Illuminate\Http\Request;

class ApplePayDomainController extends Controller
{
    private array $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    public function showAppleMerchantId(Request $request)
    {
        /* Self Host */

        if (Ninja::isSelfHost()) {
            $cgs = CompanyGateway::query()
                                 ->whereIn('gateway_key', $this->stripe_keys)
                                 ->where('is_deleted', false)
                                 ->get();

            foreach ($cgs as $cg) {
                if ($cg->getConfigField('appleDomainVerification')) {
                    return response($cg->getConfigField('appleDomainVerification'), 200);
                }
            }

            return response('', 400);
        }

        /* Hosted */

        $domain_name = $request->getHost();

        if (strpos($domain_name, config('ninja.app_domain')) !== false) {
            $subdomain = explode('.', $domain_name)[0];

            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                return $this->resolveAppleMerchantId($company);
            }
        }

        $query = [
            'portal_domain' => $request->getSchemeAndHttpHost(),
            'portal_mode' => 'domain',
        ];

        if ($company = MultiDB::findAndSetDbByDomain($query)) {
            return $this->resolveAppleMerchantId($company);
        }

        return response('', 400);
    }

    private function resolveAppleMerchantId($company)
    {
        $cgs = $company->company_gateways()
                       ->whereIn('gateway_key', $this->stripe_keys)
                       ->where('is_deleted', false)
                       ->get();

        foreach ($cgs as $cg) {
            if ($cg->getConfigField('appleDomainVerification')) {
                return response($cg->getConfigField('appleDomainVerification'), 200);
            }
        }

        return response('', 400);
    }
}
