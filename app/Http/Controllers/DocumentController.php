<?php namespace App\Http\Controllers;

use App\Http\Requests\CreateDocumentRequest;
use App\Http\Requests\DocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Ninja\Repositories\DocumentRepository;
use Datatable;
use Input;
use Response;
use Session;
use URL;
use Utils;
use Validator;
use View;

class DocumentController extends BaseController
{
    protected $documentRepo;
    protected $entityType = ENTITY_DOCUMENT;

    const STATUS_CODE_OK = 200;
    const STATUS_CODE_NOT_FOUND = 404;
    const STATUS_CODE_ERROR = 400;

    public function __construct(DocumentRepository $documentRepo)
    {
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
            }, self::STATUS_CODE_OK, $headers);
        } else {
            $response = Response::make($document->getRaw(), self::STATUS_CODE_OK);
            $response->header('content-type', Document::$types[$document->type]['mime']);
        }

        return $response;
    }

    public function getPreview(DocumentRequest $request)
    {
        $document = $request->entity();

        if (empty($document->preview)) {
            return Response::view('error', array('error' => 'Preview does not exist!'), self::STATUS_CODE_NOT_FOUND);
        }

        $direct_url = $document->getDirectPreviewUrl();
        if ($direct_url) {
            return redirect($direct_url);
        }

        $previewType = pathinfo($document->preview, PATHINFO_EXTENSION);
        $response = Response::make($document->getRawPreview(), self::STATUS_CODE_OK);
        $response->header('content-type', Document::$types[$previewType]['mime']);

        return $response;
    }

    public function getVFSJS(DocumentRequest $request, $publicId, $name)
    {
        $document = $request->entity();

        if (substr($name, -3) == '.js') {
            $name = substr($name, 0, -3);
        }

        if (!$document->isPDFEmbeddable()) {
            return Response::view('error', array('error' => 'Image does not exist!'), self::STATUS_CODE_NOT_FOUND);
        }

        $content = $document->preview ? $document->getRawPreview() : $document->getRaw();
        $content = 'ninjaAddVFSDoc(' . json_encode(intval($publicId) . '/' . strval($name)) . ',"' . base64_encode($content) . '")';
        $response = Response::make($content, self::STATUS_CODE_OK);
        $response->header('content-type', 'text/javascript');
        $response->header('cache-control', 'max-age=31536000');

        return $response;
    }

    public function postUpload(CreateDocumentRequest $request)
    {
        if (!Utils::hasFeature(FEATURE_DOCUMENTS)) {
            return;
        }

        $result = $this->documentRepo->upload(Input::all()['file'], $doc_array);

        if (is_string($result)) {
            return Response::json([
                'error' => $result,
                'code' => self::STATUS_CODE_ERROR
            ], self::STATUS_CODE_ERROR);
        } else {
            return Response::json([
                'error' => false,
                'document' => $doc_array,
                'code' => self::STATUS_CODE_OK
            ], self::STATUS_CODE_OK);
        }
    }

    public function delete(UpdateDocumentRequest $request)
    {
        $request->entity()->delete();

        return RESULT_SUCCESS;
    }
}
