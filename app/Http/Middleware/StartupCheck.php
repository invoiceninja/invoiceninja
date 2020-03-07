<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Language;
use App\Utils\CurlUtils;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

/**
 * Class StartupCheck.
 */
class StartupCheck
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // $start = microtime(true);
        // Log::error('start up check');

        if ($request->has('clear_cache')) {
            Session::flash('message', 'Cache cleared');
        }
        
        /* Make sure our cache is built */
        $cached_tables = config('ninja.cached_tables');
        
        foreach ($cached_tables as $name => $class) {
            if ($request->has('clear_cache') || ! Cache::has($name)) {
                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }
        
        if(md5_file(app_path('Jobs/Account/CreateAccount.php')) != 'd3cf840e853161b40ed79f3006b58818')
            return response()->json(['message' => 'Restricted file tampered with.']);

        /* Catch claim license requests */
        if(config('ninja.environment') == 'selfhost' && $request->has('license_key') && $request->has('product_id') && $request->segment(3) == 'claim_license')
        {

            $license_key = $request->input('license_key');
            $product_id = $request->input('product_id');

            $url = config('ninja.license_url') . "/claim_license?license_key={$license_key}&product_id={$product_id}&get_date=true";
            $data = trim(CurlUtils::get($url));

            if ($data == Account::RESULT_FAILURE) {

                $error = [
                    'message' => trans('texts.invalid_white_label_license'),
                    'errors' => []
                ];

                return response()->json($error, 400);

            } elseif ($data) {

                $date = date_create($data)->modify('+1 year');

                if ($date < date_create()) {

                    $error = [
                        'message' => trans('texts.invalid_white_label_license'),
                        'errors' => []
                    ];

                    return response()->json($error, 400);

                } else {

                    $account = auth()->user()->company()->account;

                    $account->plan_term = Account::PLAN_TERM_YEARLY;
                    $account->plan_paid = $data;
                    $account->plan_expires = $date->format('Y-m-d');
                    $account->plan = Account::PLAN_WHITE_LABEL;
                    $account->save();

                    $error = [
                        'message' => trans('texts.bought_white_label'),
                        'errors' => []
                    ];

                    return response()->json($error, 200);

                }
            } else {

                    $error = [
                        'message' => trans('texts.white_label_license_error'),
                        'errors' => []
                    ];

                    return response()->json($error, 400);

            }

        }

        $response = $next($request);

        return $response;
    }
}
