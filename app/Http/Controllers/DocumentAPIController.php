<?php namespace App\Http\Controllers;

use App\Models\Document;

use App\Ninja\Repositories\DocumentRepository;
use App\Http\Requests\DocumentRequest;
use App\Http\Requests\CreateDocumentRequest;
use Illuminate\Support\Facades\Log;

class DocumentAPIController extends BaseAPIController
{
    protected $documentRepo;

    protected $entityType = ENTITY_DOCUMENT;

    public function __construct(DocumentRepository $documentRepo)
    {
        parent::__construct();

        $this->documentRepo = $documentRepo;
    }

    public function index()
    {
        //stub
    }

    public function show(DocumentRequest $request)
    {
        $document = $request->entity();

        return DocumentController::getDownloadResponse($document);
    }

    public function store(CreateDocumentRequest $request)
    {
        $document = $this->documentRepo->upload($request->all());

        return $this->itemResponse($document);
    }

    public function update()
    {
        //stub
    }

    public function destroy($publicId)
    {
        //stub
    }
}
