<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    const EVENT_CREATE_CLIENT = 1;

    const EVENT_CREATE_INVOICE = 2;

    const EVENT_CREATE_QUOTE = 3;

    const EVENT_CREATE_PAYMENT = 4;

    const EVENT_CREATE_VENDOR = 5;

    const EVENT_UPDATE_QUOTE = 6;

    const EVENT_DELETE_QUOTE = 7;

    const EVENT_UPDATE_INVOICE = 8;

    const EVENT_DELETE_INVOICE = 9;

    const EVENT_UPDATE_CLIENT = 10;

    const EVENT_DELETE_CLIENT = 11;

    const EVENT_DELETE_PAYMENT = 12;

    const EVENT_UPDATE_VENDOR = 13;

    const EVENT_DELETE_VENDOR = 14;

    const EVENT_CREATE_EXPENSE = 15;

    const EVENT_UPDATE_EXPENSE = 16;

    const EVENT_DELETE_EXPENSE = 17;

    const EVENT_CREATE_TASK = 18;

    const EVENT_UPDATE_TASK = 19;

    const EVENT_DELETE_TASK = 20;

    const EVENT_APPROVE_QUOTE = 21;

    const EVENT_LATE_INVOICE = 22;

    const EVENT_EXPIRED_QUOTE = 23;

    const EVENT_REMIND_INVOICE = 24;

    const EVENT_PROJECT_CREATE = 25;

    const EVENT_PROJECT_UPDATE = 26;

    public static $valid_events = [
        self::EVENT_CREATE_CLIENT,
        self::EVENT_CREATE_INVOICE,
        self::EVENT_CREATE_QUOTE,
        self::EVENT_CREATE_PAYMENT,
        self::EVENT_CREATE_VENDOR,
        self::EVENT_UPDATE_QUOTE,
        self::EVENT_DELETE_QUOTE,
        self::EVENT_UPDATE_INVOICE,
        self::EVENT_DELETE_INVOICE,
        self::EVENT_UPDATE_CLIENT,
        self::EVENT_DELETE_CLIENT,
        self::EVENT_DELETE_PAYMENT,
        self::EVENT_UPDATE_VENDOR,
        self::EVENT_DELETE_VENDOR,
        self::EVENT_CREATE_EXPENSE,
        self::EVENT_UPDATE_EXPENSE,
        self::EVENT_DELETE_EXPENSE,
        self::EVENT_CREATE_TASK,
        self::EVENT_UPDATE_TASK,
        self::EVENT_DELETE_TASK,
        self::EVENT_APPROVE_QUOTE,
        self::EVENT_LATE_INVOICE,
        self::EVENT_EXPIRED_QUOTE,
        self::EVENT_REMIND_INVOICE,
        self::EVENT_PROJECT_CREATE,
        self::EVENT_PROJECT_UPDATE,
    ];

    protected $fillable = [
        'target_url',
        'format',
        'event_id',
        'rest_method',
        'headers',
    ];

    protected $casts = [
        'headers' => 'array',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
