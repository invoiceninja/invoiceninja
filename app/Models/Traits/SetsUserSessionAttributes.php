<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Auth;

trait SetsUserSessionAttributes
{

    public function setCurrentCompanyId($value)
    {
        Auth::user()->setAttribute('current_company_id', $value);
    }

}
