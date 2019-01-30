@if (count($errors))
    @foreach ($errors as $error)
        <p>{{ $error }}</p>
    @endforeach
@else
    There are no errors
@endif
