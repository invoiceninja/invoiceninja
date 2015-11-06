<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\UserRepository;

class UserService extends BaseService
{
    protected $userRepo;
    protected $datatableService;

    public function __construct(UserRepository $userRepo, DatatableService $datatableService)
    {
        $this->userRepo = $userRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->userRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->userRepo->find($accountId);

        return $this->createDatatable(ENTITY_USER, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'first_name',
                function ($model) {
                    return link_to('users/'.$model->public_id.'/edit', $model->first_name.' '.$model->last_name);
                }
            ],
            [
                'email',
                function ($model) {
                    return $model->email;
                }
            ],
            [
                'confirmed',
                function ($model) {
                    return $model->deleted_at ? trans('texts.deleted') : ($model->confirmed ? trans('texts.active') : trans('texts.pending'));
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_user'),
                function ($model) {
                    return URL::to("users/{$model->public_id}/edit");
                }
            ],
            [
                uctrans('texts.send_invite'),
                function ($model) {
                    return URL::to("send_confirmation/{$model->public_id}");
                },
                function ($model) {
                    return !$model->confirmed;
                }
            ]
        ];
    }

}