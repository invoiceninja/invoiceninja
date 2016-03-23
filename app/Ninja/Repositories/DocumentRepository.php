<?php namespace app\Ninja\Repositories;

use DB;
use Utils;
use Response;
use App\Models\Document;
use App\Ninja\Repositories\BaseRepository;
use Intervention\Image\ImageManager;
use Session;

class DocumentRepository extends BaseRepository
{
    // Expenses
    public function getClassName()
    {
        return 'App\Models\Document';
    }

    public function all()
    {
        return Document::scope()
                ->with('user')
                ->get();
    }

    public function find()
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('clients')
                    ->join('accounts', 'accounts.id', '=', 'clients.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'clients.client_id')
                    /*->leftJoin('expenses', 'expenses.id', '=', 'clients.expense_id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'clients.invoice_id')*/
                    ->where('documents.account_id', '=', $accountid)
                    /*->where('vendors.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)*/
                    ->select(
                        'documents.account_id',
                        'documents.path',
                        'documents.deleted_at',
                        'documents.size',
                        'documents.width',
                        'documents.height',
                        'documents.id',
                        'documents.is_deleted',
                        'documents.public_id',
                        'documents.invoice_id',
                        'documents.expense_id',
                        'documents.user_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'expenses.public_id as expense_public_id',
                        'expenses.user_id as expense_user_id'
                    );

        return $query;
    }

    public function upload($input)
    {
        $uploaded = $input['file'];

        $extension = strtolower($uploaded->extension());
        if(empty(Document::$extensions[$extension])){
            return Response::json([
                'error' => 'Unsupported extension',
                'code' => 400
            ], 400);
        }
        
        $documentType = Document::$extensions[$extension];
        $filePath = $uploaded->path();
        $name = $uploaded->getClientOriginalName();
        
        if(filesize($filePath)/1000 > MAX_DOCUMENT_SIZE){
            return Response::json([
                'error' => 'File too large',
                'code' => 400
            ], 400);
        }
        
        $documentTypeData = Document::$types[$documentType];
        
        $hash = sha1_file($filePath);
        $filename = \Auth::user()->account->account_key.'/'.$hash.'.'.$documentTypeData['extension'];
                
        $document = Document::createNew();
        $disk = $document->getDisk();
        if(!$disk->exists($filename)){// Have we already stored the same file
            $disk->put($filename, file_get_contents($filePath));
        }
        
        // This is an image; check if we need to create a preview
        if(in_array($documentType, array('image/jpeg','image/png','image/gif','image/bmp','image/tiff'))){
            $makePreview = false;
            $imageSize = getimagesize($filePath);
            $width = $imageSize[0];
            $height = $imageSize[1];
            $imgManagerConfig = array();
            if(in_array($documentType, array('image/gif','image/bmp','image/tiff'))){
                // Needs to be converted
                $makePreview = true;
            } else {
                if($width > DOCUMENT_PREVIEW_SIZE || $height > DOCUMENT_PREVIEW_SIZE){
                    $makePreview = true;
                }                
            }
            
            if($documentType == 'image/bmp' || $documentType == 'image/tiff'){
                if(!class_exists('Imagick')){
                    // Cant't read this
                    $makePreview = false;
                } else {
                    $imgManagerConfig['driver'] = 'imagick';
                }                
            }
            
            if($makePreview){
                $previewType = 'jpg';
                if(in_array($documentType, array('image/png','image/gif','image/bmp','image/tiff'))){
                    // Has transparency
                    $previewType = 'png';
                }
                    
                $document->preview = \Auth::user()->account->account_key.'/'.$hash.'.'.$documentTypeData['extension'].'.x'.DOCUMENT_PREVIEW_SIZE.'.'.$previewType;
                if(!$disk->exists($document->preview)){
                    // We haven't created a preview yet
                    $imgManager = new ImageManager($imgManagerConfig);
                    
                    $img = $imgManager->make($filePath);
                    
                    if($width <= DOCUMENT_PREVIEW_SIZE && $height <= DOCUMENT_PREVIEW_SIZE){
                        $previewWidth = $width;
                        $previewHeight = $height;
                    } else if($width > $height) {
                        $previewWidth = DOCUMENT_PREVIEW_SIZE;
                        $previewHeight = $height * DOCUMENT_PREVIEW_SIZE / $width;
                    } else {
                        $previewHeight = DOCUMENT_PREVIEW_SIZE;
                        $previewWidth = $width * DOCUMENT_PREVIEW_SIZE / $height;
                    }
                    
                    $img->resize($previewWidth, $previewHeight);
                    
                    $previewContent = (string) $img->encode($previewType);
                    $disk->put($document->preview, $previewContent);
                    $base64 = base64_encode($previewContent);
                }
                else{
                    $base64 = base64_encode($disk->get($document->preview));
                }
            }else{
                $base64 = base64_encode(file_get_contents($filePath));
            }      
        }
        
        $document->path = $filename;
        $document->type = $documentType;
        $document->size = filesize($filePath);
        $document->name = substr($name, -255);
        
        if(!empty($imageSize)){
            $document->width = $imageSize[0];
            $document->height = $imageSize[1];
        }
        
        $document->save();
        $doc_array = $document->toArray();
        
        if(!empty($base64)){
            $mime = !empty($previewType)?Document::$extensions[$previewType]:$documentType;
            $doc_array['base64'] = 'data:'.$mime.';base64,'.$base64;
        }

        return Response::json([
            'error' => false,
            'document' => $doc_array,
            'code'  => 200
        ], 200);
    }
}
