<?php

namespace App\Utils;

use Cache;

public class TranslationHelper
{
	
	public static function getIndustries()
	{
		return Cache::get('industries')->each(function ($industry) {
            $industry->name = trans('texts.industry_'.$industry->name);
        })->sortBy(function ($industry) {
            return $industry->name;
        })
	}
}