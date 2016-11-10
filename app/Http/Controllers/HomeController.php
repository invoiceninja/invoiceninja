<?php namespace App\Http\Controllers;

use Response;
use Redirect;
use Auth;
use View;
use Input;
use Session;
use App\Models\Account;
use App\Libraries\Utils;
use App\Ninja\Mailers\Mailer;

/**
 * Class HomeController
 */
class HomeController extends BaseController
{
    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * HomeController constructor.
     *
     * @param Mailer $mailer
     */
    public function __construct(Mailer $mailer)
    {
        //parent::__construct();

        $this->mailer = $mailer;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showIndex()
    {
        Session::reflash();

        if (!Utils::isNinja() && (!Utils::isDatabaseSetup() || Account::count() == 0)) {
            return Redirect::to('/setup');
        } elseif (Auth::check()) {
            return Redirect::to('/dashboard');
        } else {
            return Redirect::to('/login');
        }
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showTerms()
    {
        return View::make('public.terms', ['hideHeader' => true]);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function viewLogo()
    {
        return View::make('public.logo');
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function invoiceNow()
    {
        if (Auth::check() && Input::get('new_company')) {
            Session::put(PREV_USER_ID, Auth::user()->id);
            Auth::user()->clearSession();
            Auth::logout();
        }

        // Track the referral/campaign code
        if (Input::has('rc')) {
            Session::set(SESSION_REFERRAL_CODE, Input::get('rc'));
        }

        if (Auth::check()) {
            $redirectTo = Input::get('redirect_to', 'invoices/create');
            return Redirect::to($redirectTo)->with('sign_up', Input::get('sign_up'));
        } else {
            return View::make('public.invoice_now');
        }
    }

    /**
     * @param $userType
     * @param $version
     * @return \Illuminate\Http\JsonResponse
     */
    public function newsFeed($userType, $version)
    {
        $response = Utils::getNewsFeedResponse($userType);

        return Response::json($response);
    }

    /**
     * @return string
     */
    public function hideMessage()
    {
        if (Auth::check() && Session::has('news_feed_id')) {
            $newsFeedId = Session::get('news_feed_id');
            if ($newsFeedId != NEW_VERSION_AVAILABLE && $newsFeedId > Auth::user()->news_feed_id) {
                $user = Auth::user();
                $user->news_feed_id = $newsFeedId;
                $user->save();
            }
        }

        Session::forget('news_feed_message');

        return 'success';
    }

    /**
     * @return string
     */
    public function logError()
    {
        return Utils::logError(Input::get('error'), 'JavaScript');
    }

    /**
     * @return mixed
     */
    public function keepAlive()
    {
        return RESULT_SUCCESS;
    }
}
