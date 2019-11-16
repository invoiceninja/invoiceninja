@if($errors->any())
    <div class="mb-5 bg-red-800 p-4 rounded">
        @foreach($errors->all() as $error)
            <p class="text-white block">{{ $error }}</p>
        @endforeach
    </div>
@endif

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
