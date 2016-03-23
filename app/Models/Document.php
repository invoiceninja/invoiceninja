<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends EntityModel
{
    public static $extensions = array(
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pdf' => 'application/pdf',
        'gif' => 'image/gif'
    );
    
    public static $types = array(
        'image/png' => array(
            'extension' => 'png',
            'image' => true,
        ),
        'image/jpeg' => array(
            'extension' => 'jpeg',
            'image' => true,
        ),
        'image/gif' => array(
            'extension' => 'gif',
            'image' => true,
        ),
        'application/pdf' => array(
            'extension' => 'pdf',
        ),
    );
    
    // Expenses
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    
    public function fill(array $attributes)
    {
        parent::fill($attributes);
        
        if(empty($this->attributes['disk'])){
            $this->attributes['disk'] = env('LOGO_DISK', 'documents');
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
        return Storage::disk(!empty($this->disk)?$this->disk:env('LOGO_DISK', 'documents'));
    }

    public function setDiskAttribute($value)
    {
        $this->attributes['disk'] = $value?$value:env('LOGO_DISK', 'documents');
    }
    
    public function getPublicUrl(){
        $disk = $this->getDisk();
        $adapter = $disk->getAdapter();
        
        return null;
    }
    
    public function getRaw(){
        $disk = $this->getDisk();
        
        return $disk->get($this->path);
    }
    
    public function getUrl(){
        return url('document/'.$this->public_id.'/'.$this->name);
    }
    
    public function toArray()
    {
        $array = parent::toArray();
        $array['url'] = $this->getUrl();
        return $array;
    }
}