<?php namespace App\Services;

use Utils;
use Str;
use DB;
use Auth;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\ProductRepository;

class ProductService extends BaseService
{
    protected $datatableService;
    protected $productRepo;

    public function __construct(DatatableService $datatableService, ProductRepository $productRepo)
    {
        $this->datatableService = $datatableService;
        $this->productRepo = $productRepo;
    }

    protected function getRepo()
    {
        return $this->productRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->productRepo->find($accountId);

        return $this->createDatatable(ENTITY_PRODUCT, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'product_key',
                function ($model) {
                    return link_to('products/'.$model->public_id.'/edit', $model->product_key)->toHtml();
                }
            ],
            [
                'notes',
                function ($model) {
                    return nl2br(Str::limit($model->notes, 100));
                }
            ],
            [
                'cost',
                function ($model) {
                    return Utils::formatMoney($model->cost);
                }
            ],
            [
                'tax_rate',
                function ($model) {
                    return $model->tax_rate ? ($model->tax_name . ' ' . $model->tax_rate . '%') : '';
                },
                Auth::user()->account->invoice_item_taxes
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_product'),
                function ($model) {
                    return URL::to("products/{$model->public_id}/edit");
                }
            ]
        ];
    }

}