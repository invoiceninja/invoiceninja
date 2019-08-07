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
		    
		    //dropzone.on('addedfile', handleDocumentAdded);
		    //dropzone.on('removedfile', handleDocumentRemoved);
		    //dropzone.on('success', handleDocumentUploaded);
		    //dropzone.on('canceled', handleDocumentCanceled);
		    dropzone.on('error', handleDocumentError);

		}

	function handleDocumentError(file, responseText) {
	    dropzone.removeFile(file);
	 //   window.countUploadingDocuments--;
	    
	    
	    swal(responseText);
	}
    </script>
@endpush