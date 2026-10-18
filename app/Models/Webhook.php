<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Webhook
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property int|null $event_id
 * @property bool $is_deleted
 * @property string $target_url
 * @property string $format
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $rest_method
 * @property array|null $headers
 * @property-read \App\Models\Company|null $company
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Webhook where()
 * @mixin \Eloquent
 */
class Webhook extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    public const EVENT_CREATE_CLIENT = 1; //tested

    public const EVENT_CREATE_INVOICE = 2; //tested

    public const EVENT_CREATE_QUOTE = 3; //tested

    public const EVENT_CREATE_PAYMENT = 4; //tested

    public const EVENT_CREATE_VENDOR = 5; //tested

    public const EVENT_UPDATE_QUOTE = 6; //tested

    public const EVENT_DELETE_QUOTE = 7; //tested

    public const EVENT_UPDATE_INVOICE = 8; //tested

    public const EVENT_DELETE_INVOICE = 9; //tested

    public const EVENT_UPDATE_CLIENT = 10; //tested

    public const EVENT_DELETE_CLIENT = 11; //tested

    public const EVENT_DELETE_PAYMENT = 12; //tested

    public const EVENT_UPDATE_VENDOR = 13; //tested

    public const EVENT_DELETE_VENDOR = 14; //tested

    public const EVENT_CREATE_EXPENSE = 15; //tested

    public const EVENT_UPDATE_EXPENSE = 16; //tested

    public const EVENT_DELETE_EXPENSE = 17; //tested

    public const EVENT_CREATE_TASK = 18; //tested

    public const EVENT_UPDATE_TASK = 19; //tested

    public const EVENT_DELETE_TASK = 20; //tested

    public const EVENT_APPROVE_QUOTE = 21; //tested

    public const EVENT_LATE_INVOICE = 22;

    public const EVENT_EXPIRED_QUOTE = 23;

    public const EVENT_REMIND_INVOICE = 24;

    public const EVENT_PROJECT_CREATE = 25; //tested

    public const EVENT_PROJECT_UPDATE = 26; //tested

    public const EVENT_CREATE_CREDIT = 27; //tested

    public const EVENT_UPDATE_CREDIT = 28; //tested

    public const EVENT_DELETE_CREDIT = 29; //tested

    public const EVENT_PROJECT_DELETE = 30; //tested

    public const EVENT_UPDATE_PAYMENT = 31; //tested

    public const EVENT_ARCHIVE_PAYMENT = 32; //tested

    public const EVENT_ARCHIVE_INVOICE = 33; //tested

    public const EVENT_ARCHIVE_QUOTE = 34; //tested

    public const EVENT_ARCHIVE_CREDIT = 35; //tested

    public const EVENT_ARCHIVE_TASK = 36; //tested

    public const EVENT_ARCHIVE_CLIENT = 37; //tested

    public const EVENT_ARCHIVE_PROJECT = 38; //tested

    public const EVENT_ARCHIVE_EXPENSE = 39;  //tested

    public const EVENT_RESTORE_PAYMENT = 40; //tested

    public const EVENT_RESTORE_INVOICE = 41; //tested

    public const EVENT_RESTORE_QUOTE = 42; ///tested

    public const EVENT_RESTORE_CREDIT = 43; //tested

    public const EVENT_RESTORE_TASK = 44; //tested

    public const EVENT_RESTORE_CLIENT = 45; //tested

    public const EVENT_RESTORE_PROJECT = 46; //tested

    public const EVENT_RESTORE_EXPENSE = 47; //tested

    public const EVENT_ARCHIVE_VENDOR = 48; //tested

    public const EVENT_RESTORE_VENDOR = 49; //tested

    public const EVENT_CREATE_PRODUCT = 50; //tested

    public const EVENT_UPDATE_PRODUCT = 51; //tested

    public const EVENT_DELETE_PRODUCT = 52; //tested

    public const EVENT_RESTORE_PRODUCT = 53; //tested

    public const EVENT_ARCHIVE_PRODUCT = 54; //tested

    public const EVENT_CREATE_PURCHASE_ORDER = 55; //tested

    public const EVENT_UPDATE_PURCHASE_ORDER = 56; //tested

    public const EVENT_DELETE_PURCHASE_ORDER = 57; //tested

    public const EVENT_RESTORE_PURCHASE_ORDER = 58; //tested

    public const EVENT_ARCHIVE_PURCHASE_ORDER = 59; //tested

    public const EVENT_SENT_INVOICE = 60;

    public const EVENT_SENT_QUOTE = 61;

    public const EVENT_SENT_CREDIT = 62;

    public const EVENT_SENT_PURCHASE_ORDER = 63;

    public const EVENT_REMIND_QUOTE = 64;

    public const EVENT_ACCEPTED_PURCHASE_ORDER = 65;

    /**
     * @var array<int, string>
     */
    public static array $event_translation_keys = [
        self::EVENT_CREATE_CLIENT => 'create_client',
        self::EVENT_UPDATE_CLIENT => 'update_client',
        self::EVENT_ARCHIVE_CLIENT => 'archive_client',
        self::EVENT_RESTORE_CLIENT => 'restore_client',
        self::EVENT_DELETE_CLIENT => 'delete_client',
        self::EVENT_CREATE_INVOICE => 'create_invoice',
        self::EVENT_SENT_INVOICE => 'sent_invoice',
        self::EVENT_UPDATE_INVOICE => 'update_invoice',
        self::EVENT_LATE_INVOICE => 'late_invoice',
        self::EVENT_REMIND_INVOICE => 'remind_invoice',
        self::EVENT_ARCHIVE_INVOICE => 'archive_invoice',
        self::EVENT_RESTORE_INVOICE => 'restore_invoice',
        self::EVENT_DELETE_INVOICE => 'delete_invoice',
        self::EVENT_CREATE_QUOTE => 'create_quote',
        self::EVENT_SENT_QUOTE => 'sent_quote',
        self::EVENT_UPDATE_QUOTE => 'update_quote',
        self::EVENT_APPROVE_QUOTE => 'approve_quote',
        self::EVENT_EXPIRED_QUOTE => 'expired_quote',
        self::EVENT_ARCHIVE_QUOTE => 'archive_quote',
        self::EVENT_RESTORE_QUOTE => 'restore_quote',
        self::EVENT_DELETE_QUOTE => 'delete_quote',
        self::EVENT_REMIND_QUOTE => 'remind_quote',
        self::EVENT_CREATE_CREDIT => 'create_credit',
        self::EVENT_SENT_CREDIT => 'sent_credit',
        self::EVENT_UPDATE_CREDIT => 'update_credit',
        self::EVENT_ARCHIVE_CREDIT => 'archive_credit',
        self::EVENT_RESTORE_CREDIT => 'restore_credit',
        self::EVENT_DELETE_CREDIT => 'delete_credit',
        self::EVENT_CREATE_PAYMENT => 'create_payment',
        self::EVENT_UPDATE_PAYMENT => 'update_payment',
        self::EVENT_ARCHIVE_PAYMENT => 'archive_payment',
        self::EVENT_RESTORE_PAYMENT => 'restore_payment',
        self::EVENT_DELETE_PAYMENT => 'delete_payment',
        self::EVENT_CREATE_VENDOR => 'create_vendor',
        self::EVENT_UPDATE_VENDOR => 'update_vendor',
        self::EVENT_ARCHIVE_VENDOR => 'archive_vendor',
        self::EVENT_RESTORE_VENDOR => 'restore_vendor',
        self::EVENT_DELETE_VENDOR => 'delete_vendor',
        self::EVENT_CREATE_EXPENSE => 'create_expense',
        self::EVENT_UPDATE_EXPENSE => 'update_expense',
        self::EVENT_ARCHIVE_EXPENSE => 'archive_expense',
        self::EVENT_RESTORE_EXPENSE => 'restore_expense',
        self::EVENT_DELETE_EXPENSE => 'delete_expense',
        self::EVENT_CREATE_TASK => 'create_task',
        self::EVENT_UPDATE_TASK => 'update_task',
        self::EVENT_ARCHIVE_TASK => 'archive_task',
        self::EVENT_RESTORE_TASK => 'restore_task',
        self::EVENT_DELETE_TASK => 'delete_task',
        self::EVENT_PROJECT_CREATE => 'create_project',
        self::EVENT_PROJECT_UPDATE => 'update_project',
        self::EVENT_ARCHIVE_PROJECT => 'archive_project',
        self::EVENT_RESTORE_PROJECT => 'restore_project',
        self::EVENT_PROJECT_DELETE => 'delete_project',
        self::EVENT_CREATE_PRODUCT => 'create_product',
        self::EVENT_UPDATE_PRODUCT => 'update_product',
        self::EVENT_DELETE_PRODUCT => 'delete_product',
        self::EVENT_RESTORE_PRODUCT => 'restore_product',
        self::EVENT_ARCHIVE_PRODUCT => 'archive_product',
        self::EVENT_CREATE_PURCHASE_ORDER => 'create_purchase_order',
        self::EVENT_SENT_PURCHASE_ORDER => 'sent_purchase_order',
        self::EVENT_UPDATE_PURCHASE_ORDER => 'update_purchase_order',
        self::EVENT_DELETE_PURCHASE_ORDER => 'delete_purchase_order',
        self::EVENT_RESTORE_PURCHASE_ORDER => 'restore_purchase_order',
        self::EVENT_ARCHIVE_PURCHASE_ORDER => 'archive_purchase_order',
        self::EVENT_ACCEPTED_PURCHASE_ORDER => 'accept_purchase_order',
    ];

    public static $valid_events = [
        self::EVENT_ACCEPTED_PURCHASE_ORDER,
        self::EVENT_REMIND_QUOTE,
        self::EVENT_CREATE_PURCHASE_ORDER,
        self::EVENT_UPDATE_PURCHASE_ORDER,
        self::EVENT_DELETE_PURCHASE_ORDER,
        self::EVENT_RESTORE_PURCHASE_ORDER,
        self::EVENT_ARCHIVE_PURCHASE_ORDER,
        self::EVENT_CREATE_PRODUCT,
        self::EVENT_UPDATE_PRODUCT,
        self::EVENT_DELETE_PRODUCT,
        self::EVENT_RESTORE_PRODUCT,
        self::EVENT_ARCHIVE_PRODUCT,
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
        self::EVENT_SENT_INVOICE,
        self::EVENT_SENT_QUOTE,
        self::EVENT_SENT_CREDIT,
        self::EVENT_SENT_PURCHASE_ORDER,

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

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function eventTranslationKey(int $event_id): ?string
    {
        return self::$event_translation_keys[$event_id] ?? null;
    }

    public static function eventLabel(int $event_id): string
    {
        $translation_key = self::eventTranslationKey($event_id);

        if ($translation_key === null) {
            return '';
        }

        return ctrans('texts.'.$translation_key);
    }

    public function getEventLabel(): string
    {
        return self::eventLabel($this->event_id);
    }
}
