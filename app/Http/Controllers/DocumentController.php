<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\DestroyDocumentRequest;
use App\Http\Requests\Document\EditDocumentRequest;
use App\Http\Requests\Document\ShowDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Models\Document;
use App\Repositories\DocumentRepository;
use App\Transformers\DocumentTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends BaseController
{
    use MakesHash;

    protected $entity_type = Document::class;

    protected $entity_transformer = DocumentTransformer::class;

    /**
     * @var DocumentRepository
     */
    protected $document_repo;

    /**
     * DocumentController constructor.
     * @param DocumentRepository $document_repo
     */
    public function __construct(DocumentRepository $document_repo)
    {
        parent::__construct();

        $this->middleware('password_protected', ['only' => ['destroy']]);

        $this->document_repo = $document_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDocumentRequest $request
     * @return void
     */
    public function store(StoreDocumentRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param ShowDocumentRequest $request
     * @param Document $document
     * @return Response
     */
    public function show(ShowDocumentRequest $request, Document $document)
    {
        return $this->itemResponse($document);
    }

    public function download(ShowDocumentRequest $request, Document $document)
    {
        return response()->streamDownload(function () use ($document) {
            echo file_get_contents($document->generateUrl());
        }, basename($document->generateUrl()));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditDocumentRegquest $request
     * @param Document $document
     * @return Response
     */
    public function edit(EditDocumentRegquest $request, Document $document)
    {
        return $this->itemResponse($document);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDocumentRequest $request
     * @param Document $document
     * @return Response
     */
    public function update(UpdateDocumentRequest $request, Document $document)
    {
        return $this->itemResponse($document);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyDocumentRequest $request
     * @param Document $document
     * @return Response
     */
    public function destroy(DestroyDocumentRequest $request, Document $document)
    {
        $this->document_repo->delete($document);

        return response()->json(['message'=>'success']);
    }

    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $documents = Document::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if (! $invoices) {
            return response()->json(['message' => 'No Documents Found']);
        }

        /*
         * Send the other actions to the switch
         */
        $documents->each(function ($document, $key) use ($action) {
            if (auth()->user()->can('edit', $document)) {
                $this->{$action}($document);
            }
        });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(Document::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }
}
