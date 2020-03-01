@if(session('responseErrors'))
<div class="alert alert-danger">
    <ul>
        @foreach(session('responseErrors') as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif