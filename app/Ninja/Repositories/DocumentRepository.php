<?php

namespace App\Ninja\Repositories;

use App\Models\Document;
use DB;
use Form;
use Intervention\Image\ImageManager;
use Utils;

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
                ->get();
    }

    public function find()
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('clients')
                    ->join('accounts', 'accounts.id', '=', 'clients.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'clients.client_id')
                    ->where('documents.account_id', '=', $accountid)
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

    public function upload($data, &$doc_array = null)
    {
        $uploaded = $data['file'];
        $extension = strtolower($uploaded->getClientOriginalExtension());
        if (empty(Document::$types[$extension]) && ! empty(Document::$extraExtensions[$extension])) {
            $documentType = Document::$extraExtensions[$extension];
        } else {
            $documentType = $extension;
        }

        if (empty(Document::$types[$documentType])) {
            return 'Unsupported file type';
        }

        $documentTypeData = Document::$types[$documentType];

        $filePath = $uploaded->path();
        $name = $uploaded->getClientOriginalName();
        $size = filesize($filePath);

        if ($size / 1000 > MAX_DOCUMENT_SIZE) {
            return 'File too large';
        }

        // don't allow a document to be linked to both an invoice and an expense
        if (array_get($data, 'invoice_id') && array_get($data, 'expense_id')) {
            unset($data['expense_id']);
        }

        $hash = sha1_file($filePath);
        $filename = \Auth::user()->account->account_key.'/'.$hash.'.'.$documentType;

        $document = Document::createNew();
        $document->fill($data);

        $disk = $document->getDisk();
        if (! $disk->exists($filename)) {// Have we already stored the same file
            $stream = fopen($filePath, 'r');
            $disk->getDriver()->putStream($filename, $stream, ['mimetype' => $documentTypeData['mime']]);
            fclose($stream);
        }

        // This is an image; check if we need to create a preview
        if (in_array($documentType, ['jpeg', 'png', 'gif', 'bmp', 'tiff', 'psd'])) {
            $makePreview = false;
            $imageSize = getimagesize($filePath);
            $width = $imageSize[0];
            $height = $imageSize[1];
            $imgManagerConfig = [];
            if (in_array($documentType, ['gif', 'bmp', 'tiff', 'psd'])) {
                // Needs to be converted
                $makePreview = true;
            } elseif ($width > DOCUMENT_PREVIEW_SIZE || $height > DOCUMENT_PREVIEW_SIZE) {
                $makePreview = true;
            }

            if (in_array($documentType, ['bmp', 'tiff', 'psd'])) {
                if (! class_exists('Imagick')) {
                    // Cant't read this
                    $makePreview = false;
                } else {
                    $imgManagerConfig['driver'] = 'imagick';
                }
            }

            if ($makePreview) {
                $previewType = 'jpeg';
                if (in_array($documentType, ['png', 'gif', 'tiff', 'psd'])) {
                    // Has transparency
                    $previewType = 'png';
                }

                $document->preview = \Auth::user()->account->account_key.'/'.$hash.'.'.$documentType.'.x'.DOCUMENT_PREVIEW_SIZE.'.'.$previewType;
                if (! $disk->exists($document->preview)) {
                    // We haven't created a preview yet
                    $imgManager = new ImageManager($imgManagerConfig);

                    $img = $imgManager->make($filePath);

                    if ($width <= DOCUMENT_PREVIEW_SIZE && $height <= DOCUMENT_PREVIEW_SIZE) {
                        $previewWidth = $width;
                        $previewHeight = $height;
                    } elseif ($width > $height) {
                        $previewWidth = DOCUMENT_PREVIEW_SIZE;
                        $previewHeight = $height * DOCUMENT_PREVIEW_SIZE / $width;
                    } else {
                        $previewHeight = DOCUMENT_PREVIEW_SIZE;
                        $previewWidth = $width * DOCUMENT_PREVIEW_SIZE / $height;
                    }

                    $img->resize($previewWidth, $previewHeight);

                    $previewContent = (string) $img->encode($previewType);
                    $disk->put($document->preview, $previewContent);
                    $base64 = base64_encode($previewContent);
                } else {
                    $base64 = base64_encode($disk->get($document->preview));
                }
            } else {
                $base64 = base64_encode(file_get_contents($filePath));
            }
        }

        $document->path = $filename;
        $document->type = $documentType;
        $document->size = $size;
        $document->hash = $hash;
        $document->name = substr($name, -255);

        if (! empty($imageSize)) {
            $document->width = $imageSize[0];
            $document->height = $imageSize[1];
        }

        $document->save();
        $doc_array = $document->toArray();

        if (! empty($base64)) {
            $mime = Document::$types[! empty($previewType) ? $previewType : $documentType]['mime'];
            $doc_array['base64'] = 'data:'.$mime.';base64,'.$base64;
        }

        return $document;
    }

    public function getClientDatatable($contactId, $entityType, $search)
    {
        $query = DB::table('invitations')
          ->join('accounts', 'accounts.id', '=', 'invitations.account_id')
          ->join('invoices', 'invoices.id', '=', 'invitations.invoice_id')
          ->join('documents', 'documents.invoice_id', '=', 'invitations.invoice_id')
          ->join('clients', 'clients.id', '=', 'invoices.client_id')
          ->where('invitations.contact_id', '=', $contactId)
          ->where('invitations.deleted_at', '=', null)
          ->where('invoices.is_deleted', '=', false)
          ->where('clients.deleted_at', '=', null)
          ->where('invoices.is_recurring', '=', false)
          ->where('invoices.is_public', '=', true)
          // TODO: This needs to be a setting to also hide the activity on the dashboard page
          //->where('invoices.invoice_status_id', '>=', INVOICE_STATUS_SENT)
          ->select(
                'invitations.invitation_key',
                'invoices.invoice_number',
                'documents.name',
                'documents.public_id',
                'documents.created_at',
                'documents.size'
          );

        $table = \Datatable::query($query)
            ->addColumn('invoice_number', function ($model) {
                return link_to(
                    '/view/'.$model->invitation_key,
                    $model->invoice_number
                )->toHtml();
            })
            ->addColumn('name', function ($model) {
                return link_to(
                    '/client/documents/'.$model->invitation_key.'/'.$model->public_id.'/'.$model->name,
                    $model->name,
                    ['target' => '_blank']
                )->toHtml();
            })
            ->addColumn('document_date', function ($model) {
                return Utils::dateToString($model->created_at);
            })
            ->addColumn('document_size', function ($model) {
                return Form::human_filesize($model->size);
            });

        return $table->make();
    }
}
