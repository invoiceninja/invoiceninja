<html>
<body>
    @if (false)
        @include('emails.view_action', ['link' => $link, 'entityType' => $entityType])
    @endif
    {!! $body !!}
</body>
</html>