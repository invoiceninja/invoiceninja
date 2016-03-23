<?php namespace app\Ninja\Repositories;

use DB;
use Utils;
use Response;
use App\Models\Document;
use App\Ninja\Repositories\BaseRepository;
use Intervention\Image\Facades\Image;
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
                ->withTrashed()
                ->where('is_deleted', '=', false)
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
        $fileContents = null;
        $name = $uploaded->getClientOriginalName();
        
        if(filesize($filePath)/1000 > env('MAX_DOCUMENT_SIZE', DEFAULT_MAX_DOCUMENT_SIZE)){
            return Response::json([
                'error' => 'File too large',
                'code' => 400
            ], 400);
        }
        
        if($documentType == 'image/gif'){
            // Convert gif to png
            $img = Image::make($filePath);
            
            $fileContents = (string) $img->encode('png');
            $documentType = 'image/png';
            $name = pathinfo($name)['filename'].'.png';
        }
        
        $documentTypeData = Document::$types[$documentType];
        
        
        $hash = $fileContents?sha1($fileContents):sha1_file($filePath);
        $filename = \Auth::user()->account->account_key.'/'.$hash.'.'.$documentTypeData['extension'];
                
        $document = Document::createNew();
        $disk = $document->getDisk();
        if(!$disk->exists($filename)){// Have we already stored the same file
            $disk->put($filename, $fileContents?$fileContents:file_get_contents($filePath));
        }
        
        $document->path = $filename;
        $document->type = $documentType;
        $document->size = $fileContents?strlen($fileContents):filesize($filePath);
        $document->name = substr($name, -255);
        
        if(!empty($documentTypeData['image'])){
            $imageSize = getimagesize($filePath);
            if($imageSize){
                $document->width = $imageSize[0];
                $document->height = $imageSize[1];
            }
        }
        
        $document->save();
        

        return Response::json([
            'error' => false,
            'document' => $document,
            'code'  => 200
        ], 200);
    }
}
