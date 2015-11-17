<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:47
 */

namespace app\Ninja\Import\FreshBooks;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;
use stdClass;
use Illuminate\Support\Facades\Auth;

class TimesheetTransformer extends TransformerAbstract
{

    public function transform($data)
    {
        return new Collection($data, function(array $data) {
            $data = $this->arrayToObject($data);
            // start by converting to seconds
            $seconds = ($data->hours * 3600);
            $timeLogFinish =  strtotime($data->date);
            $timeLogStart = intval($timeLogFinish - $seconds);
            $timeLog[] = [];
            $timelog[] = $timeLogStart;
            $timelog[] = $timeLogFinish;
            //dd(json_decode("[[$timeLogStart,$timeLogFinish]]"));
            $timeLog = json_encode(array($timelog));
            return [
                'action'        => 'stop',
                'time_log'       => $timeLog     !== array() ? $timeLog        : '',
                'user_id'       => Auth::user()->id,
                'description'   => $data->task  !== array() ? $data->task         : '',
            ];
        });
    }

    private function arrayToObject($array)
    {
        $object             = new stdClass();
        $object->fname      = $array[0];
        $object->lname      = $array[1];
        $object->date       = $array[2];
        $object->project    = $array[3];
        $object->task       = $array[4];
        $object->hours      = $array[5];
        return $object;
    }

    public function validateHeader($csvHeader)
    {
        $header = [
            0 => "fname",
            1 => "lname",
            2 => "date",
            3 => "project",
            4 => "task",
            5 => "hours"];

        if(!empty(array_diff($header, $csvHeader)))
            throw new Exception(trans('texts.invalid_csv_header'));
    }


}