Dropzone.autoDiscover = false;
window.countUploadingDocuments = 0;

window.dropzone = new Dropzone('#document-upload .dropzone', {
    url: {!! json_encode(url('documents')) !!},
    params: {
        '_token': '{{ Session::token() }}',
        'is_default': {{ isset($isDefault) && $isDefault ? '1' : '0' }},
    },
    acceptedFiles: {!! json_encode(implode(',',\App\Models\Document::$allowedMimes)) !!},
    addRemoveLinks: true,
    dictRemoveFileConfirmation: "{{trans('texts.are_you_sure')}}",
    @foreach(['default_message', 'fallback_message', 'fallback_text', 'file_too_big', 'invalid_file_type', 'response_error', 'cancel_upload', 'cancel_upload_confirmation', 'remove_file'] as $key)
        "dict{{ Utils::toClassCase($key) }}" : {!! json_encode(trans('texts.dropzone_'.$key)) !!},
    @endforeach
    maxFilesize: {{ floatval(MAX_DOCUMENT_SIZE/1000) }},
    parallelUploads: 1,
});

if (dropzone instanceof Dropzone) {
    dropzone.on('addedfile', handleDocumentAdded);
    dropzone.on('removedfile', handleDocumentRemoved);
    dropzone.on('success', handleDocumentUploaded);
    dropzone.on('canceled', handleDocumentCanceled);
    dropzone.on('error', handleDocumentError);
    for (var i=0; i < {{ $documentSource }}.length; i++) {
        var document = {{ $documentSource }}[i];
        var mockFile = {
            name: ko.utils.unwrapObservable(document.name),
            size: ko.utils.unwrapObservable(document.size),
            type: ko.utils.unwrapObservable(document.type),
            public_id: ko.utils.unwrapObservable(document.public_id),
            status: Dropzone.SUCCESS,
            accepted: true,
            url: ko.utils.unwrapObservable(document.url),
            mock: true,
            index: i,
        };

        dropzone.emit('addedfile', mockFile);
        dropzone.emit('complete', mockFile);

        var documentType = ko.utils.unwrapObservable(document.type);
        var previewUrl = ko.utils.unwrapObservable(document.preview_url);
        var documentUrl = ko.utils.unwrapObservable(document.url);

        if (previewUrl) {
            dropzone.emit('thumbnail', mockFile, previewUrl);
        } else if (documentType == 'jpeg' || documentType == 'png' || documentType == 'svg') {
            dropzone.emit('thumbnail', mockFile, documentUrl);
        }

        dropzone.files.push(mockFile);
    }
}

function handleDocumentAdded(file){
    // open document when clicked
    if (file.url) {
        file.previewElement.addEventListener("click", function() {
            window.open(file.url, '_blank');
        });
    }
    if(file.mock)return;
    if (window.addDocument) {
        addDocument(file);
    }
    window.countUploadingDocuments++;
}

function handleDocumentRemoved(file){
    if (window.deleteDocument) {
        deleteDocument(file);
    }
    $.ajax({
        url: '{{ '/documents/' }}' + file.public_id,
        type: 'DELETE',
        success: function(result) {
            // Do something with the result
        }
    });
}

function handleDocumentUploaded(file, response){
    window.countUploadingDocuments--;
    file.public_id = response.document.public_id
    if (window.addedDocument) {
        addedDocument(file, response);
    }
    if(response.document.preview_url){
        dropzone.emit('thumbnail', file, response.document.preview_url);
    }
}

function handleDocumentCanceled() {
    window.countUploadingDocuments--;
}

function handleDocumentError(file) {
    dropzone.removeFile(file);
    window.countUploadingDocuments--;
    swal({!! json_encode(trans('texts.error_refresh_page')) !!});
}
