@extends('portal.ninja2020.layout.clean', ['custom_body_class' => 'bg-gray-50'])
@section('meta_title', ctrans('texts.sign_up_with_wepay'))

@section('body')
    <div class="flex flex-col justify-center items-center mt-10">
        <img src="{{ asset('images/wepay.svg') }}" alt="We Pay">
    </div>

    <div class="flex flex-col justify-center items-center mt-10">
        	<h1>Wepay setup complete:</h1>
    </div>

    <div class="flex flex-col justify-center items-center mt-10">
    	@if(isset($message))
    	{{ $message ?? '' }}
    	@endif
    </div>
    </div>
@endsection

@push('footer')
    <script>
    </script>
@endpush
