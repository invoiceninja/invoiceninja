<?php

class AjaxController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function pdfupload()
    {
        $uploadsDir = storage_path().'/pdfcache/';
        if ($_FILES['fileblob']['error'] === UPLOAD_ERR_OK && $_FILES['fileblob']['type'] === 'application/pdf') {
            $tmpName = $_FILES['fileblob']['tmp_name'];
            $name = $_POST['filename'];

            $finfo = new finfo(FILEINFO_MIME);
            if ($finfo->file($tmpName) === 'application/pdf; charset=binary') {
            	move_uploaded_file($tmpName, $uploadsDir.$name);
            }
        }
    }
}