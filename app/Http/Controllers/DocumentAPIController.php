<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Http\Requests\CreateDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Ninja\Repositories\DocumentRepository;

/**
 * Class DocumentAPIController.
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
     *   summary="List document",
     *   operationId="listDocuments",
     *   tags={"document"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of documents",
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
     *
     * @SWG\Get(
     *   path="/documents/{document_id}",
     *   summary="Download a document",
     *   operationId="getDocument",
     *   tags={"document"},
     *   produces={"application/octet-stream"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="document_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A file",
     *      @SWG\Schema(type="file")
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(DocumentRequest $request)
    {
        $document = $request->entity();

        if (array_key_exists($document->type, Document::$types)) {
            return DocumentController::getDownloadResponse($document);
        } else {
            return $this->errorResponse(['error' => 'Invalid mime type'], 400);
        }
    }

    /**
     * @SWG\Post(
     *   path="/documents",
     *   summary="Create a document",
     *   operationId="createDocument",
     *   tags={"document"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="document",
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

    /**
     * @SWG\Delete(
     *   path="/documents/{document_id}",
     *   summary="Delete a document",
     *   operationId="deleteDocument",
     *   tags={"document"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="document_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted document",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Document"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateDocumentRequest $request)
    {
        $entity = $request->entity();

        $this->documentRepo->delete($entity);

        return $this->itemResponse($entity);
    }
}
