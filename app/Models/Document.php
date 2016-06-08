<?php namespace App\Models;

use Illuminate\Support\Facades\Storage;
use DB;
use Auth;

class Document extends EntityModel
{
    public static $extraExtensions = array(
        'jpg' => 'jpeg',
        'tif' => 'tiff',
    );

    public static $allowedMimes = array(// Used by Dropzone.js; does not affect what the server accepts
        'image/png', 'image/jpeg', 'image/tiff', 'application/pdf', 'image/gif', 'image/vnd.adobe.photoshop', 'text/plain',
        'application/msword',
        'application/excel', 'application/vnd.ms-excel', 'application/x-excel', 'application/x-msexcel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/postscript', 'image/svg+xml',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-powerpoint',
    );

    public static $types = array(
        'png' => array(
            'mime' => 'image/png',
        ),
        'ai' => array(
            'mime' => 'application/postscript',
        ),
        'svg' => array(
            'mime' => 'image/svg+xml',
        ),
        'jpeg' => array(
            'mime' => 'image/jpeg',
        ),
        'tiff' => array(
            'mime' => 'image/tiff',
        ),
        'pdf' => array(
            'mime' => 'application/pdf',
        ),
        'gif' => array(
            'mime' => 'image/gif',
        ),
        'psd' => array(
            'mime' => 'image/vnd.adobe.photoshop',
        ),
        'txt' => array(
            'mime' => 'text/plain',
        ),
        'doc' => array(
            'mime' => 'application/msword',
        ),
        'xls' => array(
            'mime' => 'application/vnd.ms-excel',
        ),
        'ppt' => array(
            'mime' => 'application/vnd.ms-powerpoint',
        ),
        'xlsx' => array(
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ),
        'docx' => array(
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ),
        'pptx' => array(
            'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ),
    );

    public function fill(array $attributes)
    {
        parent::fill($attributes);

        if(empty($this->attributes['disk'])){
            $this->attributes['disk'] = env('DOCUMENT_FILESYSTEM', 'documents');
        }

        return $this;
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense')->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function getDisk(){
        return Storage::disk(!empty($this->disk)?$this->disk:env('DOCUMENT_FILESYSTEM', 'documents'));
    }

    public function setDiskAttribute($value)
    {
        $this->attributes['disk'] = $value?$value:env('DOCUMENT_FILESYSTEM', 'documents');
    }

    public function getDirectUrl(){
        return static::getDirectFileUrl($this->path, $this->getDisk());
    }

    public function getDirectPreviewUrl(){
        return $this->preview?static::getDirectFileUrl($this->preview, $this->getDisk(), true):null;
    }

    public static function getDirectFileUrl($path, $disk, $prioritizeSpeed = false){
        $adapter = $disk->getAdapter();
        $fullPath = $adapter->applyPathPrefix($path);

        if($adapter instanceof \League\Flysystem\AwsS3v3\AwsS3Adapter) {
            $client = $adapter->getClient();
            $command = $client->getCommand('GetObject', [
                'Bucket' => $adapter->getBucket(),
                'Key'    => $fullPath
            ]);

            return (string) $client->createPresignedRequest($command, '+10 minutes')->getUri();
        } else if (!$prioritizeSpeed // Rackspace temp URLs are slow, so we don't use them for previews
                   && $adapter instanceof \League\Flysystem\Rackspace\RackspaceAdapter) {
            $secret = env('RACKSPACE_TEMP_URL_SECRET');
            if($secret){
                $object = $adapter->getContainer()->getObject($fullPath);

                if(env('RACKSPACE_TEMP_URL_SECRET_SET')){
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

    public function getRaw(){
        $disk = $this->getDisk();

        return $disk->get($this->path);
    }

    public function getStream(){
        $disk = $this->getDisk();

        return $disk->readStream($this->path);
    }

    public function getRawPreview(){
        $disk = $this->getDisk();

        return $disk->get($this->preview);
    }

    public function getUrl(){
        return url('documents/'.$this->public_id.'/'.$this->name);
    }

    public function getClientUrl($invitation){
        return url('client/documents/'.$invitation->invitation_key.'/'.$this->public_id.'/'.$this->name);
    }

    public function isPDFEmbeddable(){
        return $this->type == 'jpeg' || $this->type == 'png' || $this->preview;
    }

    public function getVFSJSUrl(){
        if(!$this->isPDFEmbeddable())return null;
        return url('documents/js/'.$this->public_id.'/'.$this->name.'.js');
    }

    public function getClientVFSJSUrl(){
        if(!$this->isPDFEmbeddable())return null;
        return url('client/documents/js/'.$this->public_id.'/'.$this->name.'.js');
    }

    public function getPreviewUrl(){
        return $this->preview?url('documents/preview/'.$this->public_id.'/'.$this->name.'.'.pathinfo($this->preview, PATHINFO_EXTENSION)):null;
    }

    public function toArray()
    {
        $array = parent::toArray();

        if(empty($this->visible) || in_array('url', $this->visible))$array['url'] = $this->getUrl();
        if(empty($this->visible) || in_array('preview_url', $this->visible))$array['preview_url'] = $this->getPreviewUrl();

        return $array;
    }

    public function cloneDocument(){
        $document = Document::createNew($this);
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

    if(!$same_path_count){
        $document->getDisk()->delete($document->path);
    }

    if($document->preview){
        $same_preview_count = DB::table('documents')
            ->where('documents.account_id', '=', $document->account_id)
            ->where('documents.preview', '=', $document->preview)
            ->where('documents.disk', '=', $document->disk)
            ->count();
        if(!$same_preview_count){
            $document->getDisk()->delete($document->preview);
        }
    }

});
