<?php

namespace Jobs\Util;

use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class UploadFile
{

    use MakesHash;

    protected $file;

    protected $user;

    protected $company;

    $entity;

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

        $path = $this->encodePrimaryKey($this->company->id) . '/' . microtime() . '_' . str_replace(" ", "", $this->file->getClientOriginalName());

        Storage::put($path, $this->file); 

        $width = 0;
        $height = 0;

        if (in_array($this->file->getClientOriginalExtension(), ['jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) 
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
        $document->hash = $this->createHash();
        $document->size = filesize($filePath);
        $document->width = $width;
        $document->height = $height;

        $preview_path = $this->encodePrimaryKey($this->company->id) . '/' . microtime() . '_preview_' . str_replace(" ", "", $this->file->getClientOriginalName());

        $document->preview = $this->generatePreview($preview_path);

        $this->entity->documents()->save($document);

    }

    private function generatePreview($preview_path) : string
    {
        $preview = '';

        if (in_array($this->file->getClientOriginalExtension(), ['jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) 
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

                $img = $imgManager->make($filePath);

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
