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

use App\Events\Invoice\InvoiceWasUpdated;
use App\Jobs\Entity\CreateEntityPdf;
use App\Utils\Ninja;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\InvoiceInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $invoice_id
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
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\InvoiceInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class InvoiceInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['invoice'];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return Invoice::class;
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
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

    public function getName()
    {
        return $this->key;
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function markOpened()
    {
        $this->opened_date = Carbon::now();
        $this->save();
    }

    public function pdf_file_path()
    {
        $storage_path = Storage::url($this->invoice->client->invoice_filepath().$this->invoice->numberFormatter().'.pdf');

        if (! Storage::exists($this->invoice->client->invoice_filepath($this).$this->invoice->numberFormatter().'.pdf')) {
            event(new InvoiceWasUpdated($this->invoice, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
            (new CreateEntityPdf($this))->handle();
        }

        return $storage_path;
    }
}
