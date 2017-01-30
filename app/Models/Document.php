<?php

namespace App\Models;

use DB;
use Illuminate\Support\Facades\Storage;

/**
 * Class Document.
 */
class Document extends EntityModel
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_DOCUMENT;
    }

    /**
     * @var array
     */
    protected $fillable = [
        'invoice_id',
        'expense_id',
    ];

    /**
     * @var array
     */
    public static $extraExtensions = [
        'jpg' => 'jpeg',
        'tif' => 'tiff',
    ];

    /**
     * @var array
     */
    public static $allowedMimes = [// Used by Dropzone.js; does not affect what the server accepts
        'image/png', 'image/jpeg', 'image/tiff', 'application/pdf', 'image/gif', 'image/vnd.adobe.photoshop', 'text/plain',
        'application/msword',
        'application/excel', 'application/vnd.ms-excel', 'application/x-excel', 'application/x-msexcel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/postscript', 'image/svg+xml',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-powerpoint',
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
     * @param array $attributes
     *
     * @return $this
     */
    public function fill(array $attributes)
    {
        parent::fill($attributes);

        if (empty($this->attributes['disk'])) {
            $this->attributes['disk'] = env('DOCUMENT_FILESYSTEM', 'documents');
        }

        return $this;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function expense()
    {
        return $this->belongsTo('App\Models\Expense')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function getDisk()
    {
        return Storage::disk(! empty($this->disk) ? $this->disk : env('DOCUMENT_FILESYSTEM', 'documents'));
    }

    /**
     * @param $value
     */
    public function setDiskAttribute($value)
    {
        $this->attributes['disk'] = $value ? $value : env('DOCUMENT_FILESYSTEM', 'documents');
    }

    /**
     * @return null|string
     */
    public function getDirectUrl()
    {
        return static::getDirectFileUrl($this->path, $this->getDisk());
    }

    /**
     * @return null|string
     */
    public function getDirectPreviewUrl()
    {
        return $this->preview ? static::getDirectFileUrl($this->preview, $this->getDisk(), true) : null;
    }

    /**
     * @param $path
     * @param $disk
     * @param bool $prioritizeSpeed
     *
     * @throws \OpenCloud\Common\Exceptions\NoNameError
     *
     * @return null|string
     */
    public static function getDirectFileUrl($path, $disk, $prioritizeSpeed = false)
    {
        $adapter = $disk->getAdapter();
        $fullPath = $adapter->applyPathPrefix($path);

        if ($adapter instanceof \League\Flysystem\AwsS3v3\AwsS3Adapter) {
            $client = $adapter->getClient();
            $command = $client->getCommand('GetObject', [
                'Bucket' => $adapter->getBucket(),
                'Key' => $fullPath,
            ]);

            return (string) $client->createPresignedRequest($command, '+10 minutes')->getUri();
        } elseif (! $prioritizeSpeed // Rackspace temp URLs are slow, so we don't use them for previews
                   && $adapter instanceof \League\Flysystem\Rackspace\RackspaceAdapter) {
            $secret = env('RACKSPACE_TEMP_URL_SECRET');
            if ($secret) {
                $object = $adapter->getContainer()->getObject($fullPath);

                if (env('RACKSPACE_TEMP_URL_SECRET_SET')) {
                    // Go ahead and set the secret too
                    $object->getService()->getAccount()->setTempUrlSecret($secret);
                }

                $url = $object->getUrl();
                $expiry = strtotime('+10 minutes');
                $urlPath = urldecode($url->getPath());
                $body = sprintf("%s\n%d\n%s", 'GET', $expiry, $urlPath);
                $hash = hash_hmac('sha1', $body, $secret);

                return sprintf('%s?temp_url_sig=%s&temp_url_expires=%d', $url, $hash, $expiry);
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getRaw()
    {
        $disk = $this->getDisk();

        return $disk->get($this->path);
    }

    /**
     * @return mixed
     */
    public function getStream()
    {
        $disk = $this->getDisk();

        return $disk->readStream($this->path);
    }

    /**
     * @return mixed
     */
    public function getRawPreview()
    {
        $disk = $this->getDisk();

        return $disk->get($this->preview);
    }

    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getUrl()
    {
        return url('documents/'.$this->public_id.'/'.$this->name);
    }

    /**
     * @param $invitation
     *
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getClientUrl($invitation)
    {
        return url('client/documents/'.$invitation->invitation_key.'/'.$this->public_id.'/'.$this->name);
    }

    /**
     * @return bool
     */
    public function isPDFEmbeddable()
    {
        return $this->type == 'jpeg' || $this->type == 'png' || $this->preview;
    }

    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|null|string
     */
    public function getVFSJSUrl()
    {
        if (! $this->isPDFEmbeddable()) {
            return null;
        }

        return url('documents/js/'.$this->public_id.'/'.$this->name.'.js');
    }

    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|null|string
     */
    public function getClientVFSJSUrl()
    {
        if (! $this->isPDFEmbeddable()) {
            return null;
        }

        return url('client/documents/js/'.$this->public_id.'/'.$this->name.'.js');
    }

    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|null|string
     */
    public function getPreviewUrl()
    {
        return $this->preview ? url('documents/preview/'.$this->public_id.'/'.$this->name.'.'.pathinfo($this->preview, PATHINFO_EXTENSION)) : null;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        if (empty($this->visible) || in_array('url', $this->visible)) {
            $array['url'] = $this->getUrl();
        }
        if (empty($this->visible) || in_array('preview_url', $this->visible)) {
            $array['preview_url'] = $this->getPreviewUrl();
        }

        return $array;
    }

    /**
     * @return mixed
     */
    public function cloneDocument()
    {
        $document = self::createNew($this);
        $document->path = $this->path;
        $document->preview = $this->preview;
        $document->name = $this->name;
        $document->type = $this->type;
        $document->disk = $this->disk;
        $document->hash = $this->hash;
        $document->size = $this->size;
        $document->width = $this->width;
        $document->height = $this->height;

        return $document;
    }
}

Document::deleted(function ($document) {
    $same_path_count = DB::table('documents')
        ->where('documents.account_id', '=', $document->account_id)
        ->where('documents.path', '=', $document->path)
        ->where('documents.disk', '=', $document->disk)
        ->count();

    if (! $same_path_count) {
        $document->getDisk()->delete($document->path);
    }

    if ($document->preview) {
        $same_preview_count = DB::table('documents')
            ->where('documents.account_id', '=', $document->account_id)
            ->where('documents.preview', '=', $document->preview)
            ->where('documents.disk', '=', $document->disk)
            ->count();
        if (! $same_preview_count) {
            $document->getDisk()->delete($document->preview);
        }
    }
});
