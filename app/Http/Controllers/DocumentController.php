<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDocumentRequest;
use App\Http\Requests\DocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Ninja\Repositories\DocumentRepository;
use Redirect;
use Response;
use View;

class DocumentController extends BaseController
{
    protected $documentRepo;
    protected $entityType = ENTITY_DOCUMENT;

    public function __construct(DocumentRepository $documentRepo)
    {
        // parent::__construct();

        $this->documentRepo = $documentRepo;
    }

    public function get(DocumentRequest $request)
    {
        return static::getDownloadResponse($request->entity());
    }

    public static function getDownloadResponse($document)
    {
        $direct_url = $document->getDirectUrl();
        if ($direct_url) {
            return redirect($direct_url);
        }

        $stream = $document->getStream();

        if ($stream) {
            $headers = [
                'Content-Type' => Document::$types[$document->type]['mime'],
                'Content-Length' => $document->size,
            ];

            $response = Response::stream(function () use ($stream) {
                fpassthru($stream);
            }, 200, $headers);
        } else {
            $response = Response::make($document->getRaw(), 200);
            $response->header('content-type', Document::$types[$document->type]['mime']);
        }

        return $response;
    }

    public function getPreview(DocumentRequest $request)
    {
        $document = $request->entity();

        if (empty($document->preview)) {
            return Response::view('error', ['error' => 'Preview does not exist!'], 404);
        }

        $direct_url = $document->getDirectPreviewUrl();
        if ($direct_url) {
            return redirect($direct_url);
        }

        $previewType = pathinfo($document->preview, PATHINFO_EXTENSION);
        $response = Response::make($document->getRawPreview(), 200);
        $response->header('content-type', Document::$types[$previewType]['mime']);

        return $response;
    }

    public function getVFSJS(DocumentRequest $request, $publicId, $name)
    {
        $document = $request->entity();

        if (substr($name, -3) == '.js') {
            $name = substr($name, 0, -3);
        }

        if (! $document->isPDFEmbeddable()) {
            return Response::view('error', ['error' => 'Image does not exist!'], 404);
        }

        $content = $document->preview ? $document->getRawPreview() : $document->getRaw();
        $content = 'ninjaAddVFSDoc('.json_encode(intval($publicId).'/'.strval($name)).',"'.base64_encode($content).'")';
        $response = Response::make($content, 200);
        $response->header('content-type', 'text/javascript');
        $response->header('cache-control', 'max-age=31536000');

        return $response;
    }

    public function postUpload(CreateDocumentRequest $request)
    {
        $result = $this->documentRepo->upload($request->all(), $doc_array);

        if (is_string($result)) {
            return Response::json([
                'error' => $result,
                'code' => 400,
            ], 400);
        } else {
            return Response::json([
                'error' => false,
                'document' => $doc_array,
                'code' => 200,
            ], 200);
        }
    }

    public function delete(UpdateDocumentRequest $request)
    {
        $request->entity()->delete();

        return RESULT_SUCCESS;
    }
}
