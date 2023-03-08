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

use App\Helpers\Document\WithTypeHelpers;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\Document
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property string|null $url
 * @property string|null $preview
 * @property string|null $name
 * @property string|null $type
 * @property string|null $disk
 * @property string|null $hash
 * @property int|null $size
 * @property int|null $width
 * @property int|null $height
 * @property int $is_default
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $deleted_at
 * @property int $documentable_id
 * @property string $documentable_type
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $is_public
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $documentable
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\DocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Document filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Document onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereDocumentableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereDocumentableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document wherePreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document whereWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Document withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Document withoutTrashed()
 * @mixin \Eloquent
 */
class Document extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use WithTypeHelpers;

    const DOCUMENT_PREVIEW_SIZE = 300; // pixels

    /**
     * @var array
     */
    protected $fillable = [
        'is_default',
        'is_public',
    ];

    /**
     * @var array
     */
    public static $types = [
        'png' => [
            'mime' => 'image/png',
        ],
        'ai' => [
            'mime' => 'application/postscript',
        ],
        'jpeg' => [
            'mime' => 'image/jpeg',
        ],
        'jpg' => [
            'mime' => 'image/jpeg',
        ],
        'tiff' => [
            'mime' => 'image/tiff',
        ],
        'pdf' => [
            'mime' => 'application/pdf',
        ],
        'gif' => [
            'mime' => 'image/gif',
        ],
        'psd' => [
            'mime' => 'image/vnd.adobe.photoshop',
        ],
        'txt' => [
            'mime' => 'text/plain',
        ],
        'doc' => [
            'mime' => 'application/msword',
        ],
        'xls' => [
            'mime' => 'application/vnd.ms-excel',
        ],
        'ppt' => [
            'mime' => 'application/vnd.ms-powerpoint',
        ],
        'xlsx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'docx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'pptx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ];

    /**
     * @var array
     */
    public static $extraExtensions = [
        'jpg' => 'jpeg',
        'tif' => 'tiff',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function generateUrl($absolute = false)
    {
        $url = Storage::disk($this->disk)->url($this->url);

        if ($url && $absolute) {
            return url($url);
        }

        if ($url) {
            return $url;
        }

        return null;
    }

    public function generateRoute($absolute = false)
    {
        return route('api.documents.show', ['document' => $this->hashed_id]).'/download';
    }

    public function deleteFile()
    {
        Storage::disk($this->disk)->delete($this->url);
    }

    public function filePath()
    {
        return Storage::disk($this->disk)->url($this->url);
    }

    public function diskPath(): string
    {
        return Storage::disk($this->disk)->path($this->url);
    }

    public function getFile()
    {
        return Storage::get($this->url);
    }

    public function translate_entity()
    {
        return ctrans('texts.document');
    }
}
