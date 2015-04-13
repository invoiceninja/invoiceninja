<html>
<body>
    @include('emails.view_action', ['link' => $link, 'entityType' => $entityType])
    {!! $body !!}
</body>
</html>