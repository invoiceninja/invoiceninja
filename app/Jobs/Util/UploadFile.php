<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class UploadFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    use MakesHash;

    protected $file;

    protected $user;

    protected $company;

    public $entity;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct($file, $user, $company, $entity)
    {
        $this->file = $file;
        $this->user = $user;
        $this->company = $company;
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?Document
    {
        MultiDB::setDB($this->company->db);

        $path = $this->encodePrimaryKey($this->company->id);

        $file_path = $path . '/' . $this->file->hashName();

        Storage::put($path, $this->file); 

        $width = 0;

        $height = 0;

        if (in_array($this->file->getClientOriginalExtension(),['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) 
        {

            $imageSize = getimagesize($this->file);

            $width = $imageSize[0];

            $height = $imageSize[1];

        }

        $document = new Document();
        $document->user_id = $this->user->id;
        $document->company_id = $this->company->id;
        $document->path = $path;
        $document->name = $this->file->getClientOriginalName();
        $document->type = $this->file->getClientOriginalExtension();
        $document->disk = config('filesystems.default');
        $document->hash = $this->file->hashName();
        $document->size = filesize(Storage::path($file_path));
        $document->width = $width;
        $document->height = $height;

        $preview_path = $this->encodePrimaryKey($this->company->id);

        $document->preview = $this->generatePreview($preview_path);

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

        if (in_array($this->file->getClientOriginalExtension(),['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) 
        {
            $makePreview = false;
            $imageSize = getimagesize($this->file);
            $width = $imageSize[0];
            $height = $imageSize[1];
            $imgManagerConfig = [];
            if (in_array($this->file->getClientOriginalExtension(), ['gif', 'bmp', 'tiff', 'psd'])) 
            {
                // Needs to be converted
                $makePreview = true;
            } elseif ($width > Document::DOCUMENT_PREVIEW_SIZE || $height > Document::DOCUMENT_PREVIEW_SIZE) 
            {
                $makePreview = true;
            }

            if (in_array($documentType, ['bmp', 'tiff', 'psd'])) 
            {
                if (! class_exists('Imagick')) 
                {
                    // Cant't read this
                    $makePreview = false;
                } else 
                {
                    $imgManagerConfig['driver'] = 'imagick';
                }
            }

            if ($makePreview) 
            {
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
