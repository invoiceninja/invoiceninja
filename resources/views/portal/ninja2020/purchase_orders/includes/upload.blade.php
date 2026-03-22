<link rel="stylesheet" href="{{ asset('vendor/dropzone-5.7.0/dist/min/basic.min.css') }}">
<script src="{{ asset('vendor/dropzone-5.7.0/dist/min/dropzone.min.js') }}"></script>

{{-- TODO: HOSTED --}}
<div class="bg-white rounded shadow p-4 mb-10">
    <span class="text-sm mb-4 block text-gray-500 break-words">{{ ctrans('texts.allowed_file_types' )}} png, ai, svg, jpeg, tiff, pdf, gif, psd, txt, doc, xls, ppt, xlsx, docx, pptx</span>
    <form action="{{ route('vendor.upload.store',['purchase_order' => $purchase_order->hashed_id]) }}" class="dropzone p-8 border-4 border-dashed border-gray-200 rounded-md" method="post" enctype="multipart/form-data">
        @csrf
        <div class="fallback">
            <input name="file[]" type="file" multiple/>
            <input name="purchase_order" type="hidden" value="{{$purchase_order->hashed_id}}">
        </div>
    </form>
</div>

<script>

Dropzone.prototype.defaultOptions.dictDefaultMessage = '{!! ctrans('texts.dropzone_default_message') !!}';

</script>