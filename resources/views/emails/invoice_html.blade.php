<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    @include('emails.view_action', ['link' => $link, 'entityType' => $entityType])
    {!! $body !!}
</body>
</html>