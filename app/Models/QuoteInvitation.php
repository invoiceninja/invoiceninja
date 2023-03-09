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
 * App\Models\QuoteInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $quote_id
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
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Quote $quote
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\QuoteInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereQuoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereSignatureIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class QuoteInvitation extends BaseModel
{
    use MakesDates;
    use Inviteable;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['quote'];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return Quote::class;
    }

    /**
     * @return mixed
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class)->withTrashed();
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

    public function signatureDiv()
    {
        if (! $this->signature_base64) {
            return false;
        }

        return sprintf('<img src="data:image/svg+xml;base64,%s"></img><p/>%s: %s', $this->signature_base64, ctrans('texts.signed'), $this->createClientDate($this->signature_date, $this->contact->client->timezone()->name));
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function pdf_file_path()
    {
        $storage_path = Storage::url($this->quote->client->quote_filepath($this).$this->quote->numberFormatter().'.pdf');

        if (! Storage::exists($this->quote->client->quote_filepath($this).$this->quote->numberFormatter().'.pdf')) {
            (new CreateEntityPdf($this))->handle();
        }

        return $storage_path;
    }
}
