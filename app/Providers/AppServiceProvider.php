<?php namespace App\Providers;

use Session;
use Auth;
use Utils;
use HTML;
use Form;
use URL;
use Request;
use Validator;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Vendor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
        Form::macro('image_data', function($image, $contents = false) {
            if(!$contents){
                $contents = file_get_contents($image);
            }
            else{
                $contents = $image;
            }
                
            return 'data:image/jpeg;base64,' . base64_encode($contents);            
        });

        Form::macro('nav_link', function($url, $text, $url2 = '', $extra = '') {
            $capitalize = config('former.capitalize_translations');
            $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2.'/*') ) ? ' class="active"' : '';
            if ($capitalize) {
              $title = ucwords(trans("texts.$text")) . Utils::getProLabel($text);
            } else {
              $title = trans("texts.$text")  . Utils::getProLabel($text);
            }
            return '<li'.$class.'><a href="'.URL::to($url).'" '.$extra.'>'.$title.'</a></li>';
        });

        Form::macro('tab_link', function($url, $text, $active = false) {
            $class = $active ? ' class="active"' : '';
            return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
        });

        Form::macro('menu_link', function($type) {
            $types = $type.'s';
            $Type = ucfirst($type);
            $Types = ucfirst($types);
            $class = ( Request::is($types) || Request::is('*'.$type.'*')) && !Request::is('*settings*') ? ' active' : '';
            $user = Auth::user();

            $str = '<li class="dropdown '.$class.'">
                   <a href="'.URL::to($types).'" class="dropdown-toggle">'.trans("texts.$types").'</a>';
                   
            $items = [];
                       
            if($user->can('create', $type))$items[] = '<li><a href="'.URL::to($types.'/create').'">'.trans("texts.new_$type").'</a></li>';
                    
            if ($type == ENTITY_INVOICE) {
                if(!empty($items))$items[] = '<li class="divider"></li>';
                $items[] = '<li><a href="'.URL::to('recurring_invoices').'">'.trans("texts.recurring_invoices").'</a></li>';
                if($user->can('create', ENTITY_INVOICE))$items[] = '<li><a href="'.URL::to('recurring_invoices/create').'">'.trans("texts.new_recurring_invoice").'</a></li>';
                if ($user->hasFeature(FEATURE_QUOTES)) {
                    $items[] = '<li class="divider"></li>';
                    $items[] = '<li><a href="'.URL::to('quotes').'">'.trans("texts.quotes").'</a></li>';
                    if($user->can('create', ENTITY_INVOICE))$items[] = '<li><a href="'.URL::to('quotes/create').'">'.trans("texts.new_quote").'</a></li>';
                }
            } else if ($type == ENTITY_CLIENT) {
                if(!empty($items))$items[] = '<li class="divider"></li>';
                $items[] = '<li><a href="'.URL::to('credits').'">'.trans("texts.credits").'</a></li>';
                if($user->can('create', ENTITY_CREDIT))$items[] = '<li><a href="'.URL::to('credits/create').'">'.trans("texts.new_credit").'</a></li>';
            } else if ($type == ENTITY_EXPENSE) {
				if(!empty($items))$items[] = '<li class="divider"></li>';
                $items[] = '<li><a href="'.URL::to('vendors').'">'.trans("texts.vendors").'</a></li>';
                if($user->can('create', ENTITY_VENDOR))$items[] = '<li><a href="'.URL::to('vendors/create').'">'.trans("texts.new_vendor").'</a></li>';
			}
            
            if(!empty($items)){
                $str.= '<ul class="dropdown-menu" id="menu1">'.implode($items).'</ul>';
            }

            $str .= '</li>';

            return $str;
        });

        Form::macro('flatButton', function($label, $color) {
            return '<input type="button" value="' . trans("texts.{$label}") . '" style="background-color:' . $color . ';border:0 none;border-radius:5px;padding:12px 40px;margin:0 6px;cursor:hand;display:inline-block;font-size:14px;color:#fff;text-transform:none;font-weight:bold;"/>';
        });

        Form::macro('emailViewButton', function($link = '#', $entityType = ENTITY_INVOICE) {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => "view_{$entityType}",
                            'color' => '#0b4d78',
                        ])
                        ->render();
        });

        Form::macro('emailPaymentButton', function($link = '#') {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => 'pay_now',
                            'color' => '#36c157',
                        ])
                        ->render();
        });

        Form::macro('breadcrumbs', function($status = false) {
            $str = '<ol class="breadcrumb">';

            // Get the breadcrumbs by exploding the current path.
            $basePath = Utils::basePath();
            $parts = explode('?', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
            $path = $parts[0];

            if ($basePath != '/') {
                $path = str_replace($basePath, '', $path);
            }
            $crumbs = explode('/', $path);

            foreach ($crumbs as $key => $val) {
                if (is_numeric($val)) {
                    unset($crumbs[$key]);
                }
            }

            $crumbs = array_values($crumbs);
            for ($i=0; $i<count($crumbs); $i++) {
                $crumb = trim($crumbs[$i]);
                if (!$crumb) {
                    continue;
                }
                if ($crumb == 'company') {
                    return '';
                }
                $name = trans("texts.$crumb");
                if ($i==count($crumbs)-1) {
                    $str .= "<li class='active'>$name</li>";
                } else {
                    $str .= '<li>'.link_to($crumb, $name).'</li>';
                }
            }

            if ($status) {
                $str .= '&nbsp;&nbsp;&nbsp;&nbsp;' . $status;
            }

            return $str . '</ol>';
        });
        
        Form::macro('human_filesize', function($bytes, $decimals = 1) {
            $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
            $factor = floor((strlen($bytes) - 1) / 3);
            if($factor == 0)$decimals=0;// There aren't fractional bytes
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
        });
        
        Validator::extend('positive', function($attribute, $value, $parameters) {
            return Utils::parseFloat($value) >= 0;
        });

        Validator::extend('has_credit', function($attribute, $value, $parameters) {
            $publicClientId = $parameters[0];
            $amount = $parameters[1];

            $client = \App\Models\Client::scope($publicClientId)->firstOrFail();
            $credit = $client->getTotalCredit();

            return $credit >= $amount;
        });

        // check that the time log elements don't overlap
        Validator::extend('time_log', function($attribute, $value, $parameters) {
            $lastTime = 0;
            $value = json_decode($value);
            array_multisort($value);
            foreach ($value as $timeLog) {
                list($startTime, $endTime) = $timeLog;
                if (!$endTime) {
                    continue;
                }
                if ($startTime < $lastTime || $startTime > $endTime) {
                    return false;
                }
                if ($endTime < min($startTime, $lastTime)) {
                    return false;
                }
                $lastTime = max($lastTime, $endTime);
            }
            return true;
        });

        Validator::extend('less_than', function($attribute, $value, $parameters) {
            return floatval($value) <= floatval($parameters[0]);
        });

        Validator::replacer('less_than', function($message, $attribute, $rule, $parameters) {
            return str_replace(':value', $parameters[0], $message);
        });

        Validator::extend('has_counter', function($attribute, $value, $parameters) {
            return !$value || strstr($value, '{$counter}');
        });

        Validator::extend('valid_contacts', function($attribute, $value, $parameters) {
            foreach ($value as $contact) {
                $validator = Validator::make($contact, [
                        'email' => 'email|required_without:first_name',
                        'first_name' => 'required_without:email',
                    ]);
                if ($validator->fails()) {
                    return false;
                }
            }
            return true;
        });

        Validator::extend('valid_invoice_items', function($attribute, $value, $parameters) {
            $total = 0;
            foreach ($value as $item) {
                $qty = isset($item['qty']) ? $item['qty'] : 1;
                $cost = isset($item['cost']) ? $item['cost'] : 1;
                $total += $qty * $cost;
            }
            return $total <= MAX_INVOICE_AMOUNT;
        });

	}

	/**
	 * Register any application services.
	 *
	 * This service provider is a great spot to register your various container
	 * bindings with the application. As you can see, we are registering our
	 * "Registrar" implementation here. You can add your own bindings too!
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind(
			'Illuminate\Contracts\Auth\Registrar',
			'App\Services\Registrar'
		);
	}

}
