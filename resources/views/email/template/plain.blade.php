<!DOCTYPE html>
<html lang="{{ App::getLocale() }}" class="bg-white">

<head>
	<meta charset="utf-8">
</head>

<body class="bg-white p-4">
	{!! $body !!}


	    @if($signature)
        	<div style="margin-top: 20px">
        		{!! $signature !!}
    		</div>
    	@endif
</body>

<footer class="p-4">
	{!! $footer !!}
</footer>

</html>