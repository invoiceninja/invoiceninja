@section('header')
    @parent
    <link href="/vendors/css/dropzone.min.css" rel="stylesheet">
    <link href="/vendors/css/dropzone-basic.min.css" rel="stylesheet">
    <link href="/vendors/css/sweetalert.css" rel="stylesheet">
    <style>
        .dropzone {
            background: white;
            border-radius: 5px;
            border: 2px dashed rgb(0, 135, 247);
            border-image: none;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .dropzone .dz-preview .dz-image {
          width: 250px;
          height: 250px;
        }
    </style>
@stop
<div id="dropzone">
    <form class="dropzone needsclick" id="demo-upload">
    	<div class="dz-message needsclick">    
        	{{ ctrans('texts.dropzone_default_message')}}<br>
      	</div>
    </form>
</div>
@push('scripts')
    <script src="/vendors/js/dropzone.min.js"></script>
    <script src="/vendors/js/sweetalert.min.js"></script>
    <script>

        var contact = {!! auth()->user() !!};

    	Dropzone.autoDiscover = false;
		window.countUploadingDocuments = 0;

		window.dropzone = new Dropzone('.dropzone', {
		    url: '{!! $url !!}',
		    params: {
		        '_token': '{{ csrf_token() }}',
        		@foreach($params as $key => $value)
        		'{!! $key !!}' : '{!! $value !!}', 
    			@endforeach
		    },
            thumbnailWidth: 250,
            thumbnailHeight: 250,
		    addRemoveLinks: true,
		    dictRemoveFileConfirmation: "{{ctrans('texts.are_you_sure')}}",
            dictDefaultMessage : "{{ctrans('texts.dropzone_default_message')}}",
            dictFallbackMessage : "{{ctrans('texts.dropzone_fallback_message')}}",
            dictFallbackText : "{{ctrans('texts.dropzone_fallback_text')}}",
            dictFileTooBig : "{{ctrans('texts.dropzone_file_too_big')}}",
            dictInvalidFileType : "{{ctrans('texts.dropzone_invalid_file_type')}}",
            dictResponseError : "{{ctrans('texts.dropzone_response_error')}}",
            dictCancelUpload : "{{ctrans('texts.dropzone_cancel_upload')}}",
            dictCancelUploadConfirmation : "{{ctrans('texts.dropzone_cancel_upload_confirmation')}}",
            dictRemoveFile : "{{ctrans('texts.dropzone_remove_file')}}",
		    parallelUploads: 1,
		    maxFiles: {{ $multi_upload ? 100000 : 1 }},
		    clickable: true,
		    maxfilesexceeded: function(file) {
		        this.removeAllFiles();
		        this.addFile(file);
		    },
			    init: function(){
	          //  this.on("addedfile", handleFileAdded);
	          //  this.on("removedfile", handleFileRemoved);
	            this.on("error", function(file){if (!file.accepted) this.removeFile(file);});
	        },
		});


		if (dropzone instanceof Dropzone) {

		    dropzone.on('addedfile', handleDocumentAdded);
		    dropzone.on('removedfile', handleDocumentRemoved);
		    dropzone.on('success', handleDocumentUploaded);
		    //dropzone.on('canceled', handleDocumentCanceled);
		    dropzone.on('error', handleDocumentError);

            var mockFile = makeMockFile();

            if(contact.avatar) {
                dropzone.emit('addedfile', mockFile);
                dropzone.emit('complete', mockFile);
            }
            var documentType = contact.avatar_type;
            var previewUrl = contact.avatar;
            var documentUrl = contact.avatar;

            if (contact && previewUrl) {
                dropzone.emit('thumbnail', mockFile, previewUrl);
            } else if (documentType == 'jpeg' || documentType == 'png' || documentType == 'svg') {
                dropzone.emit('thumbnail', mockFile, documentUrl);
            }

            dropzone.files.push(mockFile);

		}

    function makeMockFile()
    {
        var mockFile = {
            name: contact.avatar,
            size: contact.avatar_size,
            type: contact.avatar_type,
            status: Dropzone.SUCCESS,
            accepted: true,
            url: contact.avatar,
            mock: true
        };

        return mockFile;
    }

	function handleDocumentError(file, responseText) {
	    
        dropzone.removeFile(file);

        for (var error in responseText.errors) {

            swal({title: '{{ctrans('texts.error')}}', text: responseText.errors[error]});

        }
           
	}

    function handleDocumentAdded(file){

        if (contact.avatar) {
            file.previewElement.addEventListener("click", function() {
                window.open(contact.avatar, '_blank');
            });
        }
        if(file.mock)return;
        if (window.addDocument) {
            addDocument(file);
        }
    }

    function handleDocumentUploaded(file, response){

        contact = response;
        dropzone.files = [];

        var mockFile = makeMockFile();

        if(contact.avatar) {
            dropzone.emit('complete', mockFile);
        }
        var documentType = contact.avatar_type;
        var previewUrl = contact.avatar;
        var documentUrl = contact.avatar;

        if (contact && previewUrl) {
            dropzone.emit('thumbnail', mockFile, previewUrl);
        } else if (documentType == 'jpeg' || documentType == 'png' || documentType == 'svg') {
            dropzone.emit('thumbnail', mockFile, documentUrl);
        }

        dropzone.files.push(mockFile);

    }

    function handleDocumentRemoved(file){
        if (window.deleteDocument) {
            deleteDocument(file);
        }
        $.ajax({
            url: '{!! $url !!}',
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(result) {
                // Do something with the result
            }
        });
    }
    </script>
@endpush
