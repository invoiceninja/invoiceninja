<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;


use App\Models\Activity;
use App\Transformers\ActivityTransformer;
use Illuminate\Http\Request;

class ActivityController extends BaseController
{

    protected $entity_type = Activity::class;

    protected $entity_transformer = ActivityTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $activities = Activity::whereCompanyId(auth()->user()->company()->id)
                                ->orderBy('created_at', 'DESC')
                                ->take(50);

        return $this->listResponse($activities);

    }

}