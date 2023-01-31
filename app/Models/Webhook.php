<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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

    const EVENT_CREATE_CREDIT = 27;

    const EVENT_UPDATE_CREDIT = 28;

    const EVENT_DELETE_CREDIT = 29;

    const EVENT_PROJECT_DELETE = 30;

    const EVENT_UPDATE_PAYMENT = 31;

    const EVENT_ARCHIVE_PAYMENT = 32;

    const EVENT_ARCHIVE_INVOICE = 33;

    const EVENT_ARCHIVE_QUOTE = 34;

    const EVENT_ARCHIVE_CREDIT = 35;

    const EVENT_ARCHIVE_TASK = 36;

    const EVENT_ARCHIVE_CLIENT = 37;

    const EVENT_ARCHIVE_PROJECT = 38;

    const EVENT_ARCHIVE_EXPENSE = 39;

    const EVENT_RESTORE_PAYMENT = 40;

    const EVENT_RESTORE_INVOICE = 41;

    const EVENT_RESTORE_QUOTE = 42;

    const EVENT_RESTORE_CREDIT = 43;

    const EVENT_RESTORE_TASK = 44;

    const EVENT_RESTORE_CLIENT = 45;

    const EVENT_RESTORE_PROJECT = 46;

    const EVENT_RESTORE_EXPENSE = 47;

    const EVENT_ARCHIVE_VENDOR = 48;

    const EVENT_RESTORE_VENDOR = 49;

    const EVENT_CREATE_PRODUCT = 50;

    const EVENT_UPDATE_PRODUCT = 51;

    const EVENT_ARCHIVE_PRODUCT = 52;

    const EVENT_RESTORE_PRODUCT = 53;

    const EVENT_DELETE_PRODUCT = 54;





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
        self::EVENT_CREATE_CREDIT,
        self::EVENT_UPDATE_CREDIT,
        self::EVENT_DELETE_CREDIT,
        self::EVENT_PROJECT_DELETE,
        self::EVENT_UPDATE_PAYMENT,
        self::EVENT_ARCHIVE_EXPENSE,
        self::EVENT_ARCHIVE_PROJECT,
        self::EVENT_ARCHIVE_CLIENT,
        self::EVENT_ARCHIVE_TASK,
        self::EVENT_ARCHIVE_CREDIT,
        self::EVENT_ARCHIVE_QUOTE,
        self::EVENT_ARCHIVE_INVOICE,
        self::EVENT_ARCHIVE_PAYMENT,
        self::EVENT_ARCHIVE_VENDOR,
        self::EVENT_RESTORE_EXPENSE,
        self::EVENT_RESTORE_PROJECT,
        self::EVENT_RESTORE_CLIENT,
        self::EVENT_RESTORE_TASK,
        self::EVENT_RESTORE_CREDIT,
        self::EVENT_RESTORE_QUOTE,
        self::EVENT_RESTORE_INVOICE,
        self::EVENT_RESTORE_PAYMENT,
        self::EVENT_RESTORE_VENDOR,
        self::EVENT_CREATE_PRODUCT,
        self::EVENT_UPDATE_PRODUCT,
        self::EVENT_ARCHIVE_PRODUCT,
        self::EVENT_RESTORE_PRODUCT,
        self::EVENT_DELETE_PRODUCT

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
