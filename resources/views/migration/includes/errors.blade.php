@if(session('responseErrors'))
<div class="alert alert-danger">
    @foreach(session('responseErrors') as $error)
        <p>{!! $error !!}</p>
    @endforeach
</div>
@endif