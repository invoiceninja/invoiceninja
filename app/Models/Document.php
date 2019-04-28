<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends BaseModel
{

    /**
     * @var array
     */
    protected $fillable = [
        'is_default',
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

    public function documentable()
    {
        return $this->morphTo();
    }

}
