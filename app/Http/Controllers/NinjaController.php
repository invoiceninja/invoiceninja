<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Country;
use App\Models\License;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Repositories\AccountRepository;
use App\Libraries\CurlUtils;
use Auth;
use Cache;
use CreditCard;
use Input;
use Omnipay;
use Session;
use URL;
use Utils;
use Validator;
use View;

class NinjaController extends BaseController
{
    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * @var ContactMailer
     */
    protected $contactMailer;

    /**
     * NinjaController constructor.
     *
     * @param AccountRepository $accountRepo
     * @param ContactMailer     $contactMailer
     */
    public function __construct(AccountRepository $accountRepo, ContactMailer $contactMailer)
    {
        $this->accountRepo = $accountRepo;
        $this->contactMailer = $contactMailer;
    }

    /**
     * @param array     $input
     * @param Affiliate $affiliate
     *
     * @return array
     */
    private function getLicensePaymentDetails(array $input, Affiliate $affiliate)
    {
        $country = Country::find($input['country_id']);

        $data = [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'email' => $input['email'],
            'number' => $input['card_number'],
            'expiryMonth' => $input['expiration_month'],
            'expiryYear' => $input['expiration_year'],
            'cvv' => $input['cvv'],
            'billingAddress1' => $input['address1'],
            'billingAddress2' => $input['address2'],
            'billingCity' => $input['city'],
            'billingState' => $input['state'],
            'billingPostcode' => $input['postal_code'],
            'billingCountry' => $country->iso_3166_2,
            'shippingAddress1' => $input['address1'],
            'shippingAddress2' => $input['address2'],
            'shippingCity' => $input['city'],
            'shippingState' => $input['state'],
            'shippingPostcode' => $input['postal_code'],
            'shippingCountry' => $country->iso_3166_2,
        ];

        $card = new CreditCard($data);

        return [
            'amount' => $affiliate->price,
            'card' => $card,
            'currency' => 'USD',
            'returnUrl' => URL::to('license_complete'),
            'cancelUrl' => URL::to('/'),
        ];
    }

    /**
     * @return $this|\Illuminate\Contracts\View\View
     */
    public function show_license_payment()
    {
        if (Input::has('return_url')) {
            Session::set('return_url', Input::get('return_url'));
        }

        if (Input::has('affiliate_key')) {
            if ($affiliate = Affiliate::where('affiliate_key', '=', Input::get('affiliate_key'))->first()) {
                Session::set('affiliate_id', $affiliate->id);
            }
        }

        if (Input::has('product_id')) {
            Session::set('product_id', Input::get('product_id'));
        } elseif (! Session::has('product_id')) {
            Session::set('product_id', PRODUCT_ONE_CLICK_INSTALL);
        }

        if (! Session::get('affiliate_id')) {
            return Utils::fatalError();
        }

        if (Utils::isNinjaDev() && Input::has('test_mode')) {
            Session::set('test_mode', Input::get('test_mode'));
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(GATEWAY_TYPE_CREDIT_CARD);
        $gateway = $accountGateway->gateway;
        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();

        $affiliate = Affiliate::find(Session::get('affiliate_id'));

        $data = [
            'showBreadcrumbs' => false,
            'hideHeader' => true,
            'url' => 'license',
            'amount' => $affiliate->price,
            'client' => false,
            'contact' => false,
            'gateway' => $gateway,
            'account' => $account,
            'accountGateway' => $accountGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => 1,
            'currencyCode' => 'USD',
            'paymentTitle' => $affiliate->payment_title,
            'paymentSubtitle' => $affiliate->payment_subtitle,
            'showAddress' => true,
        ];

        return View::make('payments.stripe.credit_card', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function do_license_payment()
    {
        $testMode = Session::get('test_mode') === 'true';

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'card_number' => 'required',
            'expiration_month' => 'required',
            'expiration_year' => 'required',
            'cvv' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postal_code' => 'required',
            'country_id' => 'required',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect()->to('license')
                ->withErrors($validator)
                ->withInput();
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(GATEWAY_TYPE_CREDIT_CARD);

        try {
            $affiliate = Affiliate::find(Session::get('affiliate_id'));

            if ($testMode) {
                $ref = 'TEST_MODE';
            } else {
                $details = self::getLicensePaymentDetails(Input::all(), $affiliate);

                $gateway = Omnipay::create($accountGateway->gateway->provider);
                $gateway->initialize((array) $accountGateway->getConfig());
                $response = $gateway->purchase($details)->send();

                $ref = $response->getTransactionReference();

                if (! $response->isSuccessful() || ! $ref) {
                    $this->error('License', $response->getMessage(), $accountGateway);

                    return redirect()->to('license')->withInput();
                }
            }

            $licenseKey = Utils::generateLicense();

            $license = new License();
            $license->first_name = Input::get('first_name');
            $license->last_name = Input::get('last_name');
            $license->email = Input::get('email');
            $license->transaction_reference = $ref;
            $license->license_key = $licenseKey;
            $license->affiliate_id = Session::get('affiliate_id');
            $license->product_id = Session::get('product_id');
            $license->save();

            $data = [
                'message' => $affiliate->payment_subtitle,
                'license' => $licenseKey,
                'hideHeader' => true,
                'productId' => $license->product_id,
                'price' => $affiliate->price,
            ];

            $name = "{$license->first_name} {$license->last_name}";
            $this->contactMailer->sendLicensePaymentConfirmation($name, $license->email, $affiliate->price, $license->license_key, $license->product_id);

            if (Session::has('return_url')) {
                $data['redirectTo'] = Session::get('return_url')."?license_key={$license->license_key}&product_id=".Session::get('product_id');
                $data['message'] = 'Redirecting to ' . Session::get('return_url');
            }

            return View::make('public.license', $data);
        } catch (\Exception $e) {
            $this->error('License-Uncaught', false, $accountGateway, $e);

            return redirect()->to('license')->withInput();
        }
    }

    /**
     * @return string
     */
    public function claim_license()
    {
        $licenseKey = Input::get('license_key');
        $productId = Input::get('product_id', PRODUCT_ONE_CLICK_INSTALL);

        $license = License::where('license_key', '=', $licenseKey)
                    ->where('is_claimed', '<', 10)
                    ->where('product_id', '=', $productId)
                    ->first();

        if ($license) {
            if ($license->transaction_reference != 'TEST_MODE') {
                $license->is_claimed = $license->is_claimed + 1;
                $license->save();
            }

            if ($productId == PRODUCT_INVOICE_DESIGNS) {
                return file_get_contents(storage_path() . '/invoice_designs.txt');
            } else {
                // temporary fix to enable previous version to work
                if (Input::get('get_date')) {
                    return $license->created_at->format('Y-m-d');
                } else {
                    return 'valid';
                }
            }
        } else {
            return RESULT_FAILURE;
        }
    }

    private function error($type, $error, $accountGateway = false, $exception = false)
    {
        $message = '';
        if ($accountGateway && $accountGateway->gateway) {
            $message = $accountGateway->gateway->name . ': ';
        }
        $message .= $error ?: trans('texts.payment_error');
        Session::flash('error', $message);
        Utils::logError("Payment Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message), 'PHP', true);
    }

    public function hideWhiteLabelMessage()
    {
        $user = Auth::user();
        $company = $user->account->company;

        $company->plan = null;
        $company->save();

        return RESULT_SUCCESS;
    }

    public function purchaseWhiteLabel()
    {
        if (Utils::isNinja()) {
            return redirect('/');
        }

        $user = Auth::user();
        $url = NINJA_APP_URL . '/buy_now';
        $contactKey = $user->primaryAccount()->account_key;

        $data = [
            'account_key' => NINJA_LICENSE_ACCOUNT_KEY,
            'contact_key' => $contactKey,
            'product_id' => PRODUCT_WHITE_LABEL,
            'first_name' => Auth::user()->first_name,
            'last_name' => Auth::user()->last_name,
            'email' => Auth::user()->email,
            'return_link' => true,
        ];

        if ($url = CurlUtils::post($url, $data)) {
            return redirect($url);
        } else {
            return redirect()->back()->withError(trans('texts.error_refresh_page'));
        }
    }
}
