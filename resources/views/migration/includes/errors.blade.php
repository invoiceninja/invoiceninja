@if(session('responseErrors'))
<div class="alert alert-danger">
    @foreach(session('responseErrors') as $error)
        <p>{!! $error !!}</p>
    @endforeach
</div>
@endif

@if($errors->any())
<div class="alert alert-danger">
    @foreach($errors->all() as $error)
        <p>{!! $error !!}</p>
    @endforeach
</div>
@endif