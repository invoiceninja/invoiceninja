<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTicketTemplateRequest;
use App\Libraries\Utils;
use App\Models\TicketTemplate;
use App\Services\TicketTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class TicketTemplateController extends BaseController
{

    /**
     * @var TicketTemplateService
     */

    protected $ticketTemplateService;


    /**
     * TicketTemplateController constructor.
     * @param TicketTemplateService $ticketTemplateService
     */

    public function __construct(TicketTemplateService $ticketTemplateService)
    {

        $this->ticketTemplateService = $ticketTemplateService;

    }

    /**
     * @return mixed
     */

    public function index()
    {

        return Redirect::to('settings/' . ACCOUNT_TICKETS);

    }

    /**
     * @param null $clientPublicId
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable($clientPublicId = null)
    {
        return $this->ticketTemplateService->getDatatable();
    }

    /**
     * @param $publicId
     * @return mixed
     */

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("ticket_templates/$publicId/edit");
    }

    /**
     * @param $publicId
     * @return mixed
     */

    public function edit($publicId)
    {

        $ticketTemplate = TicketTemplate::scope($publicId)->firstOrFail();

        $data = self::getViewModel($ticketTemplate);

        $data = array_merge($data, [
            'method' => 'PUT',
            'url' => '/ticket_templates/'.$publicId,
        ]);

            return View::make('accounts.ticket_templates', $data);

    }

    /**
     * @param $publicId
     * @return mixed
     */

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * @param CreateTicketTemplateRequest $request
     * @return mixed
     */

    public function store(CreateTicketTemplateRequest $request)
    {

        return $this->save();

    }

    /**
     * Displays the form for account creation.
     */

    public function create()
    {

        $data = self::getViewModel(null);

        $data = array_merge($data,[
            'method' => 'POST',
            'url' => '/ticket_template/create',
            'title' => trans('texts.add_template')
        ]);

            return View::make('accounts.ticket_templates', $data);

    }

    /**
     * @param $ticketTemplate
     * @return array
     */

    private function getViewModel($ticketTemplate)
    {
        $user = Auth::user();

        $account = $user->account;

        return [
            'account' => $account,
            'user' => $user,
            'config' => false,
            'ticket_templates' => $ticketTemplate,
        ];

    }

    /**
     * @return mixed
     */

    public function bulk()
    {

        $action = Input::get('bulk_action');

        $ids = Input::get('bulk_public_id');

        $count = $this->ticketTemplateService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_ticket_template', $count);

        Session::flash('message', $message);

            return Redirect::to('settings/' . ACCOUNT_TICKETS);

    }


    /**
     * @param bool $ticketTemplatePublicId
     * @return mixed
     */

    public function save($ticketTemplatePublicId = false)
    {

        if ($ticketTemplatePublicId)
            $ticketTemplate = TicketTemplate::scope($ticketTemplatePublicId)->firstOrFail();
        else
            $ticketTemplate = TicketTemplate::createNew();

        $ticketTemplate->name = Input::get('name');
        $ticketTemplate->description = Input::get('description');
        $ticketTemplate->save();

        $message = $ticketTemplatePublicId ? trans('texts.updated_ticket_template') : trans('texts.created_ticket_template');

        Session::flash('message', $message);

            return Redirect::to('settings/' . ACCOUNT_TICKETS);

    }


}
