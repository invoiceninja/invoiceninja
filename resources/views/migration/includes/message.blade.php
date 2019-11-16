<div class="my-2">
    @foreach($errors as $errorStack)
        @foreach($errorStack as $error)
            <p class="block py-2 text-red-700">{{ $error }}</p>
        @endforeach
    @endforeach
</div>

@if(session('success'))
    <p class="bg-green-700 py-2 px-3 rounded text-white mb-5 flex items-center">
        {{ session('success') }}
    </p>
@endif

@if(session('failure'))
    <p class="bg-red-700 py-2 px-3 rounded text-white mb-5 flex items-center">
        {{ session('failure') }}
    </p>
@endif
