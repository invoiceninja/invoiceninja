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
     * @SWG\Get(
     *   path="/documents",
     *   summary="List of document",
     *   tags={"document"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with documents",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Document"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
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
     * @SWG\Post(
     *   path="/documents",
     *   tags={"document"},
     *   summary="Create a document",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Document")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New document",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Document"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateDocumentRequest $request)
    {

        $document = $this->documentRepo->upload($request->all());

        return $this->itemResponse($document);
    }
}
