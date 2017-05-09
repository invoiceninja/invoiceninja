<?php

namespace App\Providers;

use Form;
use Illuminate\Support\ServiceProvider;
use Request;
use URL;
use Utils;
use Validator;
use Queue;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Class AppServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // support selecting job database 
        Queue::before(function (JobProcessing $event) {
            $body = $event->job->getRawBody();
            preg_match('/db-ninja-[\d+]/', $body, $matches);
            if (count($matches)) {
                config(['database.default' => $matches[0]]);
            }
        });

        Form::macro('image_data', function ($image, $contents = false) {
            if (! $contents) {
                $contents = file_get_contents($image);
            } else {
                $contents = $image;
            }

            return $contents ? 'data:image/jpeg;base64,' . base64_encode($contents) : '';
        });

        Form::macro('nav_link', function ($url, $text) {
            //$class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2.'/*') ) ? ' class="active"' : '';
            $class = (Request::is($url) || Request::is($url.'/*')) ? ' class="active"' : '';
            $title = trans("texts.$text")  . Utils::getProLabel($text);

            return '<li'.$class.'><a href="'.URL::to($url).'">'.$title.'</a></li>';
        });

        Form::macro('tab_link', function ($url, $text, $active = false) {
            $class = $active ? ' class="active"' : '';

            return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
        });

        Form::macro('menu_link', function ($type) {
            $types = $type.'s';
            $Type = ucfirst($type);
            $Types = ucfirst($types);
            $class = (Request::is($types) || Request::is('*'.$type.'*')) && ! Request::is('*settings*') ? ' active' : '';

            return '<li class="dropdown '.$class.'">
                    <a href="'.URL::to($types).'" class="dropdown-toggle">'.trans("texts.$types").'</a>
                   </li>';
        });

        Form::macro('flatButton', function ($label, $color) {
            return '<input type="button" value="' . trans("texts.{$label}") . '" style="background-color:' . $color . ';border:0 none;border-radius:5px;padding:12px 40px;margin:0 6px;cursor:hand;display:inline-block;font-size:14px;color:#fff;text-transform:none;font-weight:bold;"/>';
        });

        Form::macro('emailViewButton', function ($link = '#', $entityType = ENTITY_INVOICE) {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => "view_{$entityType}",
                            'color' => '#0b4d78',
                        ])
                        ->render();
        });

        Form::macro('emailPaymentButton', function ($link = '#') {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => 'pay_now',
                            'color' => '#36c157',
                        ])
                        ->render();
        });

        Form::macro('breadcrumbs', function ($status = false) {
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
            for ($i = 0; $i < count($crumbs); $i++) {
                $crumb = trim($crumbs[$i]);
                if (! $crumb) {
                    continue;
                }
                if ($crumb == 'company') {
                    return '';
                }

                if (! Utils::isNinjaProd() && $module = \Module::find($crumb)) {
                    $name = mtrans($crumb);
                } else {
                    $name = trans("texts.$crumb");
                }

                if ($i == count($crumbs) - 1) {
                    $str .= "<li class='active'>$name</li>";
                } else {
                    $str .= '<li>'.link_to($crumb, $name).'</li>';
                }
            }

            if ($status) {
                $str .= $status;
            }

            return $str . '</ol>';
        });

        Form::macro('human_filesize', function ($bytes, $decimals = 1) {
            $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            $factor = floor((strlen($bytes) - 1) / 3);
            if ($factor == 0) {
                $decimals = 0;
            }// There aren't fractional bytes
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
        });

        Validator::extend('positive', function ($attribute, $value, $parameters) {
            return Utils::parseFloat($value) >= 0;
        });

        Validator::extend('has_credit', function ($attribute, $value, $parameters) {
            $publicClientId = $parameters[0];
            $amount = $parameters[1];

            $client = \App\Models\Client::scope($publicClientId)->firstOrFail();
            $credit = $client->getTotalCredit();

            return $credit >= $amount;
        });

        // check that the time log elements don't overlap
        Validator::extend('time_log', function ($attribute, $value, $parameters) {
            $lastTime = 0;
            $value = json_decode($value);
            array_multisort($value);
            foreach ($value as $timeLog) {
                list($startTime, $endTime) = $timeLog;
                if (! $endTime) {
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

        Validator::extend('has_counter', function ($attribute, $value, $parameters) {
            if (! $value) {
                return true;
            }

            if (strstr($value, '{$counter}') !== false) {
                return true;
            }

            return ((strstr($value, '{$idNumber}') !== false || strstr($value, '{$clientIdNumber}') != false) && (strstr($value, '{$clientCounter}')));
        });

        Validator::extend('valid_invoice_items', function ($attribute, $value, $parameters) {
            $total = 0;
            foreach ($value as $item) {
                $qty = ! empty($item['qty']) ? $item['qty'] : 1;
                $cost = ! empty($item['cost']) ? $item['cost'] : 1;
                $total += $qty * $cost;
            }

            return $total <= MAX_INVOICE_AMOUNT;
        });

        Validator::extend('valid_subdomain', function ($attribute, $value, $parameters) {
            return ! in_array($value, ['www', 'app', 'mail', 'admin', 'blog', 'user', 'contact', 'payment', 'payments', 'billing', 'invoice', 'business', 'owner', 'info', 'ninja', 'docs', 'doc', 'documents', 'download']);
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
