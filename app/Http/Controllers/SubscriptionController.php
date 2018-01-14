<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Auth;
use Input;
use Redirect;
use Session;
use URL;
use Validator;
use View;

/**
 * Class SubscriptionController.
 */
class SubscriptionController extends BaseController
{
    /**
     * @var SubscriptionService
     */
    protected $subscriptionService;

    /**
     * SubscriptionController constructor.
     *
     * @param SubscriptionService $subscriptionService
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
        //parent::__construct();

        $this->subscriptionService = $subscriptionService;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        return $this->subscriptionService->getDatatable(Auth::user()->account_id);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit($publicId)
    {
        $subscription = Subscription::scope($publicId)->firstOrFail();

        $data = [
            'subscription' => $subscription,
            'method' => 'PUT',
            'url' => 'subscriptions/' . $publicId,
            'title' => trans('texts.edit_subscription'),
        ];

        return View::make('accounts.subscription', $data);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        return $this->save();
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $data = [
          'subscription' => null,
          'method' => 'POST',
          'url' => 'subscriptions',
          'title' => trans('texts.add_subscription'),
        ];

        return View::make('accounts.subscription', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');

        $count = $this->subscriptionService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_subscription'));

        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    /**
     * @param bool $subscriptionPublicId
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function save($subscriptionPublicId = false)
    {
        if (Auth::user()->account->hasFeature(FEATURE_API)) {
            $rules = [
                'event_id' => 'required',
                'target_url' => 'required|url',
            ];

            if ($subscriptionPublicId) {
                $subscription = Subscription::scope($subscriptionPublicId)->firstOrFail();
            } else {
                $subscription = Subscription::createNew();
                $subscriptionPublicId = $subscription->public_id;
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to($subscriptionPublicId ? 'subscriptions/edit' : 'subscriptions/create')->withInput()->withErrors($validator);
            }

            $subscription->fill(request()->all());
            $subscription->save();

            if ($subscriptionPublicId) {
                $message = trans('texts.updated_subscription');
            } else {
                $message = trans('texts.created_subscription');
            }

            Session::flash('message', $message);
        }

        return redirect('/settings/api_tokens');

        /*
        if ($subscriptionPublicId) {
            return Redirect::to('subscriptions/' . $subscriptionPublicId . '/edit');
        } else {
            return redirect('/settings/api_tokens');
        }
        */
    }
}
