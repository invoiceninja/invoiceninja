<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Illuminate\Http\Request;
use Turbo124\Beacon\Facades\LightLogs;
use App\DataMapper\Analytics\FeedbackCreated;

class FeedbackController extends Controller
{
    public function __invoke(Request $request)
    {
        if(Ninja::isHosted()){

            $user = auth()->user();
            $company = $user->company();

            $rating = $request->input('rating', 0);
            $notes = $request->input('notes', '');
            
            LightLogs::create(new FeedbackCreated($rating, $notes, $company->company_key, $company->account->key, $user->present()->name()))->batch();
        }
        
        return response()->noContent();

    }
}
