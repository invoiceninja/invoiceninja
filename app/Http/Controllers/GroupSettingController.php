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


use App\Factory\GroupSettingFactory;
use App\Http\Requests\GroupSetting\CreateGroupSettingRequest;
use App\Http\Requests\GroupSetting\DestroyGroupSettingRequest;
use App\Http\Requests\GroupSetting\EditGroupSettingRequest;
use App\Http\Requests\GroupSetting\ShowGroupSettingRequest;
use App\Http\Requests\GroupSetting\StoreGroupSettingRequest;
use App\Http\Requests\GroupSetting\UpdateGroupSettingRequest;
use App\Models\GroupSetting;
use App\Repositories\GroupSettingRepository;
use App\Transformers\GroupSettingTransformer;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;

class GroupSettingController extends BaseController
{
    use DispatchesJobs;

    protected $entity_type = GroupSetting::class;

    protected $entity_transformer = GroupSettingTransformer::class;

    protected $group_setting_repo;

    public function __construct(GroupSettingRepository $group_setting_repo)
    {
        parent::__construct();

        $this->group_setting_repo = $group_setting_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $group_settings = GroupSetting::whereCompanyId(auth()->user()->company()->id);

        return $this->listResponse($group_settings);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateGroupSettingRequest $request)
    {
        $group_setting = GroupSettingFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($group_setting);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\SignupRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGroupSettingRequest $request)
    {
        //need to be careful here as we may also receive some
        //supporting attributes such as logo which need to be handled outside of the
        //settings object
        $group_setting = GroupSettingFactory::create(auth()->user()->company()->id, auth()->user()->id);

        $group_setting = $this->group_setting_repo->save($request->all(), $group_setting);
        
        return $this->itemResponse($group_setting);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowGroupSettingRequest $request, GroupSetting $group_setting)
    {
        return $this->itemResponse($group_setting);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditGroupSettingRequest $request, GroupSetting $group_setting)
    {
        return $this->itemResponse($group_setting);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGroupSettingRequest $request, GroupSetting $group_setting)
    {

       $group_setting = $this->group_setting_repo->save($request->all(), $group_setting);
        
        return $this->itemResponse($group_setting);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyGroupSettingRequest $request, GroupSetting $group_setting)
    {
        $group_setting->delete();

        return response()->json([], 200);

    }
}
