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
        $aspsps = data_get($enablebanking->getAspsps(), 'aspsps');

        if (empty($aspsps) || !is_array($aspsps)) {
            return $this->failed('aspsps-not-available', $context, $company);
        }

        // show bank_selection_screen, when aspsp_id is not present
        if (!isset($data['aspsp_name'], $data['aspsp_country'])) {
            return view('bank.enablebanking.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'aspsps' => $aspsps,
                'aspsp_name' => $data['aspsp_name'] ?? null,
                'aspsp_country' => $data['aspsp_country'] ?? null,
                'redirectUrl' => $context['redirect'] . '?action=enablebanking_connect&status=user-aborted'
            ]);
        }

        // Find the selected ASPSP
        $selected_aspsp = collect($aspsps)->first(function ($aspsp) use ($data) {
            return data_get($aspsp, 'name') === $data['aspsp_name'] && data_get($aspsp, 'country') === $data['aspsp_country'];
        });

        if (!$selected_aspsp) {
            return $this->failed('aspsp-invalid', $context, $company);
        }

        // Start authorization process
        try {
            $auth_result = $enablebanking->startAuthorization(
                $selected_aspsp['name'],
                $selected_aspsp['country'],
                config('ninja.app_url') . '/enablebanking/confirm',
                $request->token,
                data_get($selected_aspsp, 'maximum_consent_validity') ? (int) data_get($selected_aspsp, 'maximum_consent_validity') : null
            );
        } catch (\Exception $e) {
            $debug = "{$e->getMessage()} ({$e->getCode()})";
            nlog("EnableBanking: Could not start authorization with {$selected_aspsp['name']}: {$debug}");

            return $this->failed('auth-failure', $context, $company, $this->errorCode($e));
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
        $context = $request->getTokenContent();

        // An unknown, expired, replayed or malformed state has no usable context (and therefore
        // no company), so validate it before anything dereferences it.
        if (!$context || ($context['context'] ?? null) !== 'enablebanking' || !array_key_exists('state', $context)) {
            return $this->failed('ref-invalid', ['lang' => $context['lang'] ?? 'en']);
        }

        $company = $request->getCompany();
        $lang = substr($company->locale(), 0, 2);

        if (!array_key_exists('lang', $data) && $context['lang'] != $lang) {
            return redirect()->route('enablebanking.confirm', array_merge(['lang' => $context['lang']], $request->query()));
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
            return $this->failed('session-creation-failed', $context, $company, $this->errorCode($e));
        }

        // Check validity of session
        $session_id = data_get($session, 'session_id');

        if (empty($session_id)) {
            return $this->failed('session-invalid', $context, $company);
        }

        $session_accounts = data_get($session, 'accounts');

        if (empty($session_accounts) || !is_array($session_accounts)) {
            return $this->failed('session-no-accounts', $context, $company);
        }

        // connect new accounts
        $bank_integration_ids = [];
        $it = new AccountTransformer();

        $accounts = $it->transform($session_accounts);

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
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_name = data_get($session, 'aspsp.name')
                    ? trim(data_get($session, 'aspsp.name') . ' ' . data_get($session, 'aspsp.country', ''))
                    : 'EnableBanking';
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->currency = $account['account_currency'];
            } finally {

                if ($bank_integration) {

                    // EnableBanking reuses the Nordigen columns (no extra migration):
                    // - nordigen_account_id     => account uid (changes with every session)
                    // - requisition_id          => session id (checked by ProcessBankTransactionsEnableBanking::updateAccount())
                    // - nordigen_institution_id => identification_hash (stable across sessions, see findIntegrationBy())
                    // Always store the new values, also when reconnecting an existing integration.
                    $bank_integration->nordigen_account_id = $account['provider_account_id'];
                    $bank_integration->requisition_id = $session_id;

                    if (!empty($account['identification_hash'])) {
                        $bank_integration->nordigen_institution_id = $account['identification_hash'];
                    }

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
            ->where('disabled_upstream', false)
            ->each(function ($bank_integration) {
                ProcessBankTransactionsEnableBanking::dispatch($bank_integration)->delay(now()->addHour());
            });

        // prevent rerun of this method with same ref
        Cache::delete($data['state']);

        // Successfull Response => Redirect
        // Keep the redirect untouched (incl. the "#/" hash-router prefix), consistent with the
        // user-aborted and failed redirects and with the Nordigen flow.
        return response()->redirectTo($context['redirect'] . '?action=enablebanking_connect&status=success&bank_integrations=' . implode(',', $bank_integration_ids));
    }

    /**
     * Handles failure scenarios for EnableBanking bank integrations
     *
     */
    private function failed(string $reason, array $context, $company = null, ?string $error_code = null): View
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
            'error_code' => $error_code,
            'redirectUrl' => $url . '?action=enablebanking_connect&status=failed&reason=' . $reason,
        ]);
    }

    /**
     * Extract the EnableBanking API error code (e.g. REDIRECT_URI_NOT_ALLOWED) from an exception
     * raised by the EnableBanking helper, which embeds the raw API response body in its message.
     */
    private function errorCode(\Throwable $e): ?string
    {
        if (preg_match('/"error"\s*:\s*"([A-Z0-9_]+)"/', $e->getMessage(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find the existing Bank Integration for an EnableBanking account.
     *
     * The account uid changes with every session, so an existing integration is matched on the
     * stable identification_hash (stored in nordigen_institution_id). The uid (stored in
     * nordigen_account_id) is only used as a fallback when no hash is available.
     *
     * @param array{provider_account_id: string, identification_hash?: string} $account
     */
    private function findIntegrationBy(
        array   $account,
        Company $company,
    ): BankIntegration {
        $hash = $account['identification_hash'] ?? '';

        return BankIntegration::withTrashed()
            ->where('company_id', $company->id)
            ->where('integration_type', BankIntegration::INTEGRATION_TYPE_ENABLEBANKING)
            ->where('is_deleted', 0)
            ->where(function ($query) use ($account, $hash) {
                if (!empty($hash)) {
                    $query->where('nordigen_institution_id', $hash)
                        ->orWhere('nordigen_account_id', $account['provider_account_id']);

                    return;
                }

                $query->where('nordigen_account_id', $account['provider_account_id']);
            })
            ->orderByDesc('id')
            ->firstOrFail();
    }
}