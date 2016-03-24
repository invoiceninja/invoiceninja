<?php namespace App\Models;

use Illuminate\Support\Facades\Storage;
use DB;

class Document extends EntityModel
{
    public static $extensions = array(
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'pdf' => 'application/pdf',
        'gif' => 'image/gif'
    );
    
    public static $types = array(
        'image/png' => array(
            'extension' => 'png',
        ),
        'image/jpeg' => array(
            'extension' => 'jpeg',
        ),
        'image/tiff' => array(
            'extension' => 'tiff',
        ),
        'image/gif' => array(
            'extension' => 'gif',
        ),
        'application/pdf' => array(
            'extension' => 'pdf',
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
        return $this->belongsTo('App\Models\User');
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
        return url('document/'.$this->public_id.'/'.$this->name);
    }
    
    public function getClientUrl($invitation){
        return url('client/document/'.$invitation->invitation_key.'/'.$this->public_id.'/'.$this->name);
    }
    
    public function getVFSJSUrl(){
        return url('document/js/'.$this->public_id.'/'.$this->name.'.js');
    }
    
    public function getClientVFSJSUrl(){
        return url('client/document/js/'.$this->public_id.'/'.$this->name.'.js');
    }
    
    public function getPreviewUrl(){
        return $this->preview?url('document/preview/'.$this->public_id.'/'.$this->name.'.'.pathinfo($this->preview, PATHINFO_EXTENSION)):null;
    }
    
    public function toArray()
    {
        $array = parent::toArray();
        $array['url'] = $this->getUrl();
        $array['preview_url'] = $this->getPreviewUrl();
        return $array;
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