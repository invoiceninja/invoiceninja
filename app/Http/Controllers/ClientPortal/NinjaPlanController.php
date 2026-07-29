<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Services\Ninja\NinjaPlanTrialCardService;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class NinjaPlanController extends Controller
{
    use MakesHash;

    public function index(string $contact_key, string $account_or_company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($account_or_company_key);
        $company = Company::query()->where('company_key', $account_or_company_key)->first();

        if (! $company) {
            MultiDB::findAndSetDbByAccountKey($account_or_company_key);

            /** @var \App\Models\Account $account **/
            $account = Account::query()->where('key', $account_or_company_key)->first();
        } else {
            $account = $company->account;
        }

        if (MultiDB::findAndSetDbByContactKey($contact_key) && $client_contact = ClientContact::where('contact_key', $contact_key)->first()) {
            nlog('Ninja Plan Controller - Found and set Client Contact');

            request()->session()->invalidate();
            request()->session()->regenerateToken();
            Auth::guard('contact')->loginUsingId($client_contact->id, true);

            return $this->plan();
        }

        return redirect()->route('client.catchall');
    }

    public function trial()
    {
        $data['gateway'] = $this->trialGateway();
        $data['client'] = Auth::guard('contact')->user()->client;

        return $this->render('plan.trial', $data);
    }

    public function trialSetup(Request $request, NinjaPlanTrialCardService $service): JsonResponse
    {
        $profile = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'integer', Rule::exists('countries', 'id')],
        ]);

        try {
            $result = $service->createSetupAttempt(
                auth()->guard('contact')->user(),
                $this->trialGateway(),
                $profile
            );

            return response()->json([
                'attempt_id' => $result['attempt']->id,
                'client_secret' => $result['client_secret'],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }
    }

    public function trialAuthorization(Request $request, NinjaPlanTrialCardService $service): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => ['required', 'uuid'],
            'setup_intent_id' => ['required', 'string', 'starts_with:seti_'],
        ]);

        try {
            $result = $service->createAuthorization(
                $validated['attempt_id'],
                $validated['setup_intent_id'],
                auth()->guard('contact')->user(),
                $this->trialGateway()
            );

            return response()->json([
                'attempt_id' => $result['attempt']->id,
                'client_secret' => $result['client_secret'],
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }
    }

    public function trial_confirmation(Request $request, NinjaPlanTrialCardService $service): JsonResponse
    {
        $attemptId = (string) $request->input('attempt_id');
        $paymentIntentId = (string) $request->input('payment_intent_id');

        if (! Str::isUuid($attemptId) || ! str_starts_with($paymentIntentId, 'pi_')) {
            return response()->json([
                'message' => ctrans('texts.trial_card_verification_failed'),
            ], 422);
        }

        try {
            $verification = $service->verifyAndReleaseAuthorization(
                $attemptId,
                $paymentIntentId,
                auth()->guard('contact')->user(),
                $this->trialGateway()
            );
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_release_failed'),
            ], 502);
        }

        $attempt = $verification['attempt'];
        $method = $verification['payment_method'];

        if ($attempt->state === \App\Models\NinjaPlanTrialAttempt::STATE_COMPLETED) {
            $request->session()->flash('ninja_plan_trial_confirmed', true);

            return response()->json([
                'redirect_url' => route('client.trial.confirmed'),
            ]);
        }

        try {
            $service->provision($attempt, $method);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }

        $request->session()->flash('ninja_plan_trial_confirmed', true);

        return response()->json([
            'redirect_url' => route('client.trial.confirmed'),
        ]);
    }

    public function trialConfirmed(): RedirectResponse|View
    {
        if (! request()->session()->pull('ninja_plan_trial_confirmed', false)) {
            return redirect()->route('client.plan');
        }

        return $this->render('plan.trial_confirmed');
    }

    private function trialGateway(): CompanyGateway
    {
        return CompanyGateway::on('db-ninja-01')->findOrFail(
            config('ninja.ninja_default_company_gateway_id')
        );
    }

    public function plan()
    {
        // return $this->trial();
        //harvest the current plan
        $data = [];
        $data['late_invoice'] = false;

        if (MultiDB::findAndSetDbByAccountKey(Auth::guard('contact')->user()->client->custom_value2)) {
            $account = Account::query()->where('key', Auth::guard('contact')->user()->client->custom_value2)->first();

            if ($account) {
                //offer the option to have a free trial
                if (!$account->plan && !$account->is_trial) {
                    return $this->trial();
                }

                if (Carbon::parse($account->plan_expires)->lt(now())) {
                    //expired get the most recent invoice for payment

                    $late_invoice = Invoice::on('db-ninja-01')
                                           ->where('company_id', Auth::guard('contact')->user()->company->id)
                                           ->where('client_id', Auth::guard('contact')->user()->client->id)
                                           ->where('status_id', Invoice::STATUS_SENT)
                                           ->whereNotNull('subscription_id')
                                           ->orderBy('id', 'DESC')
                                           ->first();

                    $data['late_invoice'] = false;
                }

                $recurring_invoice = RecurringInvoice::on('db-ninja-01')
                                            ->where('client_id', auth()->guard('contact')->user()->client->id)
                                            ->where('company_id', Auth::guard('contact')->user()->company->id)
                                            ->whereNotNull('subscription_id')
                                            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                            ->orderBy('id', 'desc')
                                            ->first();

                $monthly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 6)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $yearly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 31)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $monthly_plans = $monthly_plans->merge($yearly_plans);

                $current_subscription_id = $recurring_invoice ? $this->encodePrimaryKey($recurring_invoice->subscription_id) : false;

                //remove existing subscription
                if ($current_subscription_id) {
                    $monthly_plans = $monthly_plans->filter(function ($plan) use ($current_subscription_id) {
                        return (string) $plan->hashed_id != (string) $current_subscription_id;
                    });
                }

                $data['account'] = $account;
                $data['client'] = Auth::guard('contact')->user()->client;
                $data['plans'] = $monthly_plans;
                $data['current_subscription_id'] = $current_subscription_id;
                $data['current_recurring_id'] = $recurring_invoice ? $recurring_invoice->hashed_id : false;

                return $this->render('plan.index', $data);
            }
        } else {
            return redirect('/client/dashboard');
        }
    }
}
