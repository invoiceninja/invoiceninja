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

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class UploadFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    const IMAGE = 1;

    const DOCUMENT = 2;

    const PROPERTIES = [
        self::IMAGE => [
            'path' => 'images',
        ],
        self::DOCUMENT => [
            'path' => 'documents',
        ],
    ];

    protected $file;

    protected $user;

    protected $company;

    protected $type;

    protected $is_public;

    public $entity;

    public function __construct($file, $type, $user, $company, $entity, $disk = null, $is_public = false)
    {
        $this->file = $file;
        $this->type = $type;
        $this->user = $user;
        $this->company = $company;
        $this->entity = $entity;
        $this->disk = $disk ?? config('filesystems.default');
        $this->is_public = $is_public;

        //MultiDB::setDB($this->company->db);
    }

    /**
     * Execute the job.
     *
     * @return Document|null
     */
    public function handle() : ?Document
    {
        if (is_array($this->file)) { //return early if the payload is just JSON
            return null;
        }

        $path = self::PROPERTIES[$this->type]['path'];

        if ($this->company) {
            $path = sprintf('%s/%s', $this->company->company_key, self::PROPERTIES[$this->type]['path']);
        }

        $instance = Storage::disk($this->disk)->putFileAs(
            $path,
            $this->file,
            $this->file->hashName()
        );

        if (in_array($this->file->extension(), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) {
            $image_size = getimagesize($this->file);

            $width = $image_size[0];
            $height = $image_size[1];
        }

        $document = new Document();
        $document->user_id = $this->user->id;
        $document->company_id = $this->company->id;
        $document->url = $instance;
        $document->name = $this->file->getClientOriginalName();
        $document->type = $this->file->extension();
        $document->disk = $this->disk;
        $document->hash = $this->file->hashName();
        $document->size = $this->file->getSize();
        $document->width = isset($width) ? $width : null;
        $document->height = isset($height) ? $height : null;
        $document->is_public = $this->is_public;

        // $preview_path = $this->encodePrimaryKey($this->company->id);
        // $document->preview = $this->generatePreview($preview_path);

        $this->entity->documents()->save($document);

        return $document;
    }

    private function generatePreview($preview_path) : string
    {
        $extension = $this->file->getClientOriginalExtension();

        if (empty(Document::$types[$extension]) && ! empty(Document::$extraExtensions[$extension])) {
            $documentType = Document::$extraExtensions[$extension];
        } else {
            $documentType = $extension;
        }

        if (empty(Document::$types[$documentType])) {
            return 'Unsupported file type';
        }

        $preview = '';

        if (in_array($this->file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) {
            $makePreview = false;
            $imageSize = getimagesize($this->file);
            $width = $imageSize[0];
            $height = $imageSize[1];
            $imgManagerConfig = [];
            if (in_array($this->file->getClientOriginalExtension(), ['gif', 'bmp', 'tiff', 'psd'])) {
                // Needs to be converted
                $makePreview = true;
            } elseif ($width > Document::DOCUMENT_PREVIEW_SIZE || $height > Document::DOCUMENT_PREVIEW_SIZE) {
                $makePreview = true;
            }

            if (in_array($documentType, ['bmp', 'tiff', 'psd'])) {
                if (! class_exists('Imagick')) {
                    // Cant't read this
                    $makePreview = false;
                } else {
                    $imgManagerConfig['driver'] = 'imagick';
                }
            }

            if ($makePreview) {
                // We haven't created a preview yet
                $imgManager = new ImageManager($imgManagerConfig);

                $img = $imgManager->make($preview_path);

                if ($width <= Document::DOCUMENT_PREVIEW_SIZE && $height <= Document::DOCUMENT_PREVIEW_SIZE) {
                    $previewWidth = $width;
                    $previewHeight = $height;
                } elseif ($width > $height) {
                    $previewWidth = Document::DOCUMENT_PREVIEW_SIZE;
                    $previewHeight = $height * Document::DOCUMENT_PREVIEW_SIZE / $width;
                } else {
                    $previewHeight = Document::DOCUMENT_PREVIEW_SIZE;
                    $previewWidth = $width * DOCUMENT_PREVIEW_SIZE / $height;
                }

                $img->resize($previewWidth, $previewHeight);

                $previewContent = (string) $img->encode($this->file->getClientOriginalExtension());

                Storage::put($preview_path, $previewContent);

                $preview = $preview_path;
            }
        }

        return $preview;
    }
}
