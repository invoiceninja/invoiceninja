<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class StepsController extends BaseController
{
    private $availableSteps;

    public function __construct()
    {
        $this->availableSteps = [
            'settings', 'clients',
        ];
    }

    /**
     * @param $step
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($step)
    {
        if (!in_array($step, $this->availableSteps)) {
            abort(404);
        }

        return view(
            sprintf("migration.steps.%s", $step)
        );
    }

    /**
     * @param $step
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function handle($step, Request $request)
    {
        if (!in_array($step, $this->availableSteps)) {
            abort(404);
        }

        $service = ucfirst(
            Str::camel($step)
        );

        $serviceClass = "App\Services\Migration\Steps\\{$service}StepService";
        $service = new $serviceClass($request);

        $service->start();

        if ($service->getSuccessful()) {
            return redirect($service->onSuccess())->with('message', $service->getResponse());
        }

        return redirect($service->onFailure())->with('message', $service->getResponse());
    }
}
