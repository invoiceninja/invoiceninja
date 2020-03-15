<!DOCTYPE html>
<html lang="{!! $lang !!}">
{!! $includes !!}
	<body>
		{!! $header !!}
		{!! $body !!}
			@if($product)
				{!! $product !!}
			@endif
			@if($task)
				{!! $task !!}
			@endif
		{!! $footer !!}
	</body>
</html>