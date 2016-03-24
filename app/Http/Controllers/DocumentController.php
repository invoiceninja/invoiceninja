<?php namespace App\Http\Controllers;

use Datatable;
use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;
use Validator;
use Response;
use App\Models\Document;
use App\Ninja\Repositories\DocumentRepository;

class DocumentController extends BaseController
{
    protected $documentRepo;
    protected $model = 'App\Models\Document';

    public function __construct(DocumentRepository $documentRepo)
    {
        // parent::__construct();

        $this->documentRepo = $documentRepo;
    }
    
    public function get($publicId)
    {
        $document = Document::scope($publicId)
                        ->firstOrFail();
        
        if(!$this->checkViewPermission($document, $response)){
            return $response;
        }
        
        return static::getDownloadResponse($document);
    }
    
    public static function getDownloadResponse($document){
        $direct_url = $document->getDirectUrl();
        if($direct_url){
            return redirect($direct_url);
        }
        
        $stream = $document->getStream();
        
        if($stream){
            $headers = [
                'Content-Type'        => $document->type,
                'Content-Length'      => $document->size,
            ];

            $response = Response::stream(function() use ($stream) {
                fpassthru($stream);
            }, 200, $headers);
        }
        else{
            $response = Response::make($document->getRaw(), 200);
            $response->header('content-type', $document->type);
        }
            
        return $response;
    }
    
    public function getPreview($publicId)
    {
        $document = Document::scope($publicId)
                        ->firstOrFail();
        
        if(!$this->checkViewPermission($document, $response)){
            return $response;
        }
        
        if(empty($document->preview)){
            return Response::view('error', array('error'=>'Preview does not exist!'), 404);
        }
        
        $direct_url = $document->getDirectPreviewUrl();
        if($direct_url){
            return redirect($direct_url);
        }
        
        $extension = pathinfo($document->preview, PATHINFO_EXTENSION);
        $response = Response::make($document->getRawPreview(), 200);
        $response->header('content-type', Document::$extensions[$extension]);
        
        return $response;
    }
    
    public function getVFSJS($publicId, $name){
        $document = Document::scope($publicId)
                        ->firstOrFail();
        
        if(substr($name, -3)=='.js'){
            $name = substr($name, 0, -3);
        }
        
        if(!$this->checkViewPermission($document, $response)){
            return $response;
        }
        
        if(substr($document->type, 0, 6) != 'image/'){
            return Response::view('error', array('error'=>'Image does not exist!'), 404);
        }
        
        $content = $document->preview?$document->getRawPreview():$document->getRaw();
        $content = 'ninjaAddVFSDoc('.json_encode(intval($publicId).'/'.strval($name)).',"'.base64_encode($content).'")';
        $response = Response::make($content, 200);
        $response->header('content-type', 'text/javascript');
        $response->header('cache-control', 'max-age=31536000');
        
        return $response;
    }
    
    public function postUpload()
    {
        if (!Utils::isPro()) {
            return;
        }
        
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
                
        $document = Input::all();
        
        $response = $this->documentRepo->upload($document);
        return $response;
    }
}
