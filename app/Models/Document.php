<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends BaseModel
{
    use SoftDeletes;
    use Filterable;

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
        'svg' => [
            'mime' => 'image/svg+xml',
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
}
