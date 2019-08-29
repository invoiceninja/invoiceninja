@extends('portal.default.layouts.master')
@section('body')
<main class="main">
    <div class="container-fluid">

		<object id="pdfObject" type="application/pdf" style="display:block;background-color:#525659;border:solid 2px #9a9a9a;" frameborder="1" width="100%" height="1180px"></object>
		<div id="pdfCanvas" style="display:none;width:100%;background-color:#525659;border:solid 2px #9a9a9a;padding-top:40px;text-align:center">
		    <canvas id="theCanvas" style="max-width:100%;border:solid 1px #CCCCCC;"></canvas>
		</div>

	</div>
</main>
</body>
@endsection