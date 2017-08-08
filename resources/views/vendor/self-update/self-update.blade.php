@extends('header')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="alert alert-warning" role="alert">
                        <strong>
                            {{ trans('texts.warning') }}:
                        </strong>
                        {{ trans('texts.update_invoiceninja_warning') }}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    @if(!$updateAvailable)
                        {{ trans('texts.update_invoiceninja_unavailable') }}
                    @else
                        <strong>
                            {{ trans('texts.update_invoiceninja_available') }}
                        </strong>
                        <br/>
                        {!! trans('texts.update_invoiceninja_instructions', ['version' => $versionAvailable]) !!}
                    @endif
                </div>
            </div>
            @if($updateAvailable)
            <div class="row">
                <div class="col-lg-12">
                    <br/>
                    <form name="download-update-form" action="{{ url('self-update') }}" method="post">
                        {{ csrf_field() }}
                        <input type="hidden" name="action" id="update-action" value="update"/>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="do-updade">
                                {{ trans('texts.update_invoiceninja_update_start') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
    <script type="text/javascript">
        $('#download-update').click(function (){
            $('#update-action').val('download');
        });
        $('#do-update').click(function (){
            $('#update-action').val('update');
        });
    </script>
@endsection