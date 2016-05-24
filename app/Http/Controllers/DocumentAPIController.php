<?php namespace App\Http\Controllers;

use App\Models\Document;

class DocumentAPIController extends BaseAPIController
{

    public function __construct()
    {
        parent::__construct();

    }

    public function index()
    {
        //stub
    }

    public function show($publicId)
    {
        $document = Document::scope($publicId)->firstOrFail();

        return DocumentController::getDownloadResponse($document);
    }

    public function store()
    {
        //stub
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
