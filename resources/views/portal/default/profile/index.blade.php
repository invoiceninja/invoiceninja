@extends('portal.default.layouts.master')
@section('header')
    @parent
    <link href="/vendors/css/dropzone.min.css" rel="stylesheet">
    <link href="/vendors/css/dropzone-basic.min.css" rel="stylesheet">
    <style>


        .dropzone {
            background: white;
            border-radius: 5px;
            border: 2px dashed rgb(0, 135, 247);
            border-image: none;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
@stop
@section('body')
    <main class="main">
        <div class="container-fluid">

			<div class="row" style="padding-top: 30px;">
			
				<div class="col-sm-6" style="padding-bottom: 10px;">
					
                    <div class="card">

                        <div class="card-header">
                        
                            <strong>{{ ctrans('texts.avatar') }}</strong>
                        </div>

                        <div class="card-body">

                            <i class="fa fa-user fa-5x"></i>

                            <div id="dropzone">
                                <form class="dropzone needsclick" id="demo-upload" action="/upload">
                                  <div class="dz-message needsclick">    
                                    Drop files here or click to upload.<BR>
                                  </div>
                                </form>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-sm-6" style="padding-bottom: 10px;">
                    
                    <div class="card">

                        <div class="card-header">
                        
                        <strong> {{ ctrans('texts.profile') }}</strong>

                        </div>

                        <div class="card-body">
                        
                        </div>


                    </div>

                </div>

			</div>

        </div>
    </main>
</body>
@endsection
@push('scripts')
    <script src="/vendors/js/dropzone.min.js"></script>
@endpush
@section('footer')


<script>

$(document).ready(function() {



});  


@endsection

