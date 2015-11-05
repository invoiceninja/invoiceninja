<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\TokenRepository;

class TokenService extends BaseService
{
    protected $tokenRepo;
    protected $datatableService;

    public function __construct(TokenRepository $tokenRepo, DatatableService $datatableService)
    {
        $this->tokenRepo = $tokenRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->tokenRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->tokenRepo->find($accountId);

        return $this->createDatatable(ENTITY_TOKEN, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("tokens/{$model->public_id}/edit", $model->name);
                }
            ],
            [
                'token',
                function ($model) {
                    return $model->token;
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_token'),
                function ($model) {
                    return URL::to("tokens/{$model->public_id}/edit");
                }
            ]
        ];
    }

}