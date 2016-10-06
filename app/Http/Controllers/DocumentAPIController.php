<?php namespace App\Http\Controllers;

use App\Models\Document;
use App\Ninja\Repositories\DocumentRepository;
use App\Http\Requests\DocumentRequest;
use App\Http\Requests\CreateDocumentRequest;

/**
 * Class DocumentAPIController
 */
class DocumentAPIController extends BaseAPIController
{
    /**
     * @var DocumentRepository
     */
    protected $documentRepo;

    /**
     * @var string
     */
    protected $entityType = ENTITY_DOCUMENT;

    /**
     * DocumentAPIController constructor.
     *
     * @param DocumentRepository $documentRepo
     */
    public function __construct(DocumentRepository $documentRepo)
    {
        parent::__construct();

        $this->documentRepo = $documentRepo;
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $documents = Document::scope();

        return $this->listResponse($documents);

    }

    /**
     * @param DocumentRequest $request
     *
     * @return \Illuminate\Http\Response|\Redirect|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function show(DocumentRequest $request)
    {
        $document = $request->entity();

        if(array_key_exists($document->type, Document::$types))
            return DocumentController::getDownloadResponse($document);
        else
            return $this->errorResponse(['error'=>'Invalid mime type'],400);
    }

    /**
     * @param CreateDocumentRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateDocumentRequest $request)
    {
        
        $document = $this->documentRepo->upload($request->all());

        return $this->itemResponse($document);
    }
}
