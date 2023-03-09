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

use App\Jobs\Entity\CreateEntityPdf;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\CreditInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $credit_id
 * @property string $key
 * @property string|null $transaction_reference
 * @property string|null $message_id
 * @property string|null $email_error
 * @property string|null $signature_base64
 * @property string|null $signature_date
 * @property string|null $sent_date
 * @property string|null $viewed_date
 * @property string|null $opened_date
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $signature_ip
 * @property string|null $email_status
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\ClientContact $contact
 * @property-read \App\Models\Credit $credit
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\CreditInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereCreditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereSignatureIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class CreditInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        'id',
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['credit'];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return Credit::class;
    }

    /**
     * @return mixed
     */
    public function credit()
    {
        return $this->belongsTo(Credit::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getName()
    {
        return $this->key;
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function pdf_file_path()
    {
        $storage_path = Storage::url($this->credit->client->quote_filepath($this).$this->credit->numberFormatter().'.pdf');

        if (! Storage::exists($this->credit->client->credit_filepath($this).$this->credit->numberFormatter().'.pdf')) {
            (new CreateEntityPdf($this))->handle();
        }

        return $storage_path;
    }
}
