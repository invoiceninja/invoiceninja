@extends('portal.default.layouts.master')

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

                            @include('generic.dropzone')

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

@section('footer')

<script>

$(document).ready(function() {

});  

@endsection