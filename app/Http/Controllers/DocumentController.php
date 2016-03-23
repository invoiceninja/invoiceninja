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
                        ->withTrashed()
                        ->firstOrFail();
        
        if(!$this->checkViewPermission($document, $response)){
            return $response;
        }
        
        $public_url = $document->getPublicUrl();
        if($public_url){
            return redirect($public_url);
        }
        
        
        $response = Response::make($document->getRaw(), 200);
        $response->header('content-type', $document->type);
        
        return $response;
    }
    
    public function postUpload()
    {
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
                
        $document = Input::all();
        
        $response = $this->documentRepo->upload($document);
        return $response;
    }
}
