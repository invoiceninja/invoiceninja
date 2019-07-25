@extends('portal.default.layouts.master')

@section('header')
	@parent
	<link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css"/>
@stop

@section('body')
    <main class="main">
        <div class="container-fluid">

			<div class="row">
			
				<div class="col-lg-12">
					<div class="animated fadeIn">
	                    <div class="col-md-12 card">

	                        {!! $html->table() !!}

	                    </div>
	                </div>
				</div>
			</div>

        </div>
    </main>
</body>
@endsection

@section('footer')
    @parent
    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" type="text/javascript" defer></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js" defer></script>

    {!! $html->scripts() !!}
@endsection