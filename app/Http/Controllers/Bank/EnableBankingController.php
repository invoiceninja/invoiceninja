<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\EnableBanking\EnableBanking;
use App\Helpers\Bank\EnableBanking\Transformer\AccountTransformer;
use App\Http\Controllers\BaseController;
use App\Http\Requests\EnableBanking\ConfirmEnableBankingBankIntegrationRequest;
use App\Http\Requests\EnableBanking\ConnectEnableBankingBankIntegrationRequest;
use App\Jobs\Bank\ProcessBankTransactionsEnableBanking;
use App\Models\BankIntegration;
use App\Models\Company;
use App\Utils\Ninja;
use Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnableBankingController extends BaseController
{
    /**
     * Handles the initial bank connection flow
     */
    public function connect(ConnectEnableBankingBankIntegrationRequest $request): View|RedirectResponse
    {
        $data = $request->all();
        $context = $request->getTokenContent();

        if (!$context) {
            return $this->failed('token-invalid', ['lang' => 'en']);
        }

        $company = $request->getCompany();
        $context['redirect'] = $data['redirect'];
        $context['lang'] = $lang = substr($company->locale(), 0, 2);

        if ($context['context'] != 'enablebanking' || array_key_exists('requisitionId', $context)) {
            return $this->failed('token-invalid', $context);
        }

        if (!(config('ninja.enablebanking.application_id') && config('ninja.enablebanking.key_path'))) {
            return $this->failed('account-config-invalid', $context, $company);
        }

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $company->account->isEnterprisePaidClient()))) {
            return $this->failed('not-available', $context, $company);
        }

        $enablebanking = new EnableBanking();
        $aspsps = $enablebanking->getAspsps();

        if (empty($aspsps['aspsps'])) {
            return $this->failed('aspsps-not-available', $context, $company);
        }

        // show bank_selection_screen, when aspsp_id is not present
        if (!isset($data['aspsp_name'], $data['aspsp_country'])) {
            return view('bank.enablebanking.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'aspsps' => $aspsps['aspsps'],
                'aspsp_name' => $data['aspsp_name'] ?? null,
                'aspsp_country' => $data['aspsp_country'] ?? null,
                'redirectUrl' => $context['redirect'] . '?action=enablebanking_connect&status=user-aborted'
            ]);
        }

        // Find the selected ASPSP
        $selected_aspsp = array_values(array_filter($aspsps['aspsps'], function ($aspsp) use ($data) {
            return $aspsp['name'] === $data['aspsp_name'] && $aspsp['country'] === $data['aspsp_country'];
        }))[0];

        if (!$selected_aspsp) {
            return $this->failed('aspsp-invalid', $context, $company);
        }

        // Start authorization process
        try {
            $auth_result = $enablebanking->startAuthorization(
                $selected_aspsp['name'],
                $selected_aspsp['country'],
                config('ninja.app_url') . '/enablebanking/confirm',
                $request->token
            );
        } catch (\Exception $e) {
            $debug = "{$e->getMessage()} ({$e->getCode()})";
            nlog("EnableBanking: Could not start authorization with {$selected_aspsp['name']}: {$debug}");

            return $this->failed('auth-failure', $context, $company);
        }

        // save cache
        $context['state'] = $auth_result['state'];
        $context['aspsp_name'] = $selected_aspsp['name'];
        $context['aspsp_country'] = $selected_aspsp['country'];
        Cache::put($request->token, $context, 3600);

        return response()->redirectTo($auth_result['url']);
    }

    /**
     * Handles the OAuth redirect and account setup after bank authentication
     */
    public function confirm(ConfirmEnableBankingBankIntegrationRequest $request): View|RedirectResponse
    {
        $data = $request->all();
        $company = $request->getCompany();
        $lang = substr($company->locale(), 0, 2);

        /** @var array $context */
        $context = $request->getTokenContent();

        if (!array_key_exists('lang', $data) && $context['lang'] != $lang) {
            return redirect()->route('enablebanking.confirm', array_merge(['lang' => $context['lang']], $request->query()));
        }

        if (!$context || $context['context'] != 'enablebanking' || !array_key_exists('state', $context)) {
            return $this->failed('ref-invalid', $context);
        }

        if (!config('ninja.enablebanking.application_id') || !config('ninja.enablebanking.key_path')) {
            return $this->failed('account-config-invalid', $context, $company);
        }

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $company->account->isEnterprisePaidClient()))) {
            return $this->failed('not-available', $context, $company);
        }

        // Check if we have the authorization code
        if (!isset($data['code'])) {
            return $this->failed('auth-code-missing', $context, $company);
        }

        // Create session from authorization code
        $enablebanking = new EnableBanking();

        try {
            $session = $enablebanking->createSession($data['code']);
        } catch (\Exception $e) {
            nlog("EnableBanking: Could not create session: {$e->getMessage()}");
            return $this->failed('session-creation-failed', $context, $company);
        }

        // Check validity of session
        if (empty($session['session_id'])) {
            return $this->failed('session-invalid', $context, $company);
        }

        if (empty($session['accounts'])) {
            return $this->failed('session-no-accounts', $context, $company);
        }

        // connect new accounts
        $bank_integration_ids = [];
        $it = new AccountTransformer();

        $accounts = $it->transform($session['accounts']);

        foreach ($accounts as $account) {
            $bank_integration = false;
            try {
                $bank_integration = $this->findIntegrationBy($account, $company);
                $bank_integration->deleted_at = null;
            } catch (ModelNotFoundException $e) {
                $bank_integration = new BankIntegration();

                $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_ENABLEBANKING;
                $bank_integration->company_id = $company->id;
                $bank_integration->account_id = $company->account_id;
                $bank_integration->user_id = $company->owner()->id;
                $bank_integration->enablebanking_session_id = $session['session_id']; // TODO(FlorientR): Useless ?
                $bank_integration->enablebanking_account_id = $account['id'];
                $bank_integration->enablebanking_session_expired_at = new \DateTime($account['access']['valid_until'] ?? 'now');
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_name = $account['provider_name'];
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->currency = $account['account_currency'];
            } finally {

                if ($bank_integration) {

                    $bank_integration->auto_sync = true;
                    $bank_integration->disabled_upstream = false;
                    $bank_integration->balance = $account['current_balance'];
                    $bank_integration->bank_account_status = $account['account_status'];
                    $bank_integration->from_date = now()->subDays($account['provider_history']);

                    $bank_integration->save();

                    array_push($bank_integration_ids, $bank_integration->id);
                }
            }
        }

        // perform update in background
        $company->account->bank_integrations
            ->where('integration_type', BankIntegration::INTEGRATION_TYPE_ENABLEBANKING)
            ->where('auto_sync', true)
            ->each(function ($bank_integration) {
                ProcessBankTransactionsEnableBanking::dispatch($bank_integration)->delay(now()->addHour());
            });

        // prevent rerun of this method with same ref
        Cache::delete($data['state']);
        $context['redirect'] = str_replace('#/', '', $context['redirect'] ?? ''); // TODO(FlorientR): Why # in the redirect URL ?
        // Successfull Response => Redirect
        return response()->redirectTo($context['redirect'] . '?action=enablebanking_connect&status=success&bank_integrations=' . implode(',', $bank_integration_ids));
    }

    /**
     * Handles failure scenarios for EnableBanking bank integrations
     *
     */
    private function failed(string $reason, array $context, $company = null): View
    {
        $companyData = $company ? [
            'company' => $company,
            'account' => $company->account,
        ] : [];

        $url = $context['redirect'] ?? config('ninja.app_url');

        return view('bank.enablebanking.handler', [
            ...$companyData,
            'lang' => $context['lang'],
            'failed_reason' => explode('&', $reason)[0],
            'redirectUrl' => $url . '?action=enablebanking_connect&status=failed&reason=' . $reason,
        ]);
    }

    /**
     * Find the first available Bank Integration from its EnableBanking account or session.
     *
     * @param array{id: string} $account
     */
    private function findIntegrationBy(
        array   $account,
        Company $company,
    ): BankIntegration {
        return BankIntegration::withTrashed()
            ->where('enablebanking_account_id', $account['id'])
            ->where('company_id', $company->id)
            ->where('is_deleted', 0)
            ->firstOrFail();
    }
}