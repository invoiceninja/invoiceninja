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

    					{!! $html->table(['class' => 'table table-hover table-striped'], true) !!}

	                    </div>
	                </div>
				</div>
			</div>

        </div>
    </main>
</body>
@endsection

@push('scripts')
	<script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" type="text/javascript"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
@endpush

@section('footer')
    {!! $html->scripts() !!}
@endsection