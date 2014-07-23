@extends('accounts.nav')

@section('content')	
	@parent
	@include('accounts.nav_advanced')

  {{ Button::success_link(URL::to('users/create'), trans("texts.add_user"), array('class' => 'pull-right'))->append_with_icon('plus-sign') }} 

  {{ Datatable::table()   
      ->addColumn(
        trans('texts.name'),
        trans('texts.email'),
        trans('texts.user_state'),
        trans('texts.action'))
      ->setUrl(url('api/users/'))      
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)      
      ->setOptions('bAutoWidth', false)      
      ->setOptions('aoColumns', [[ "sWidth"=> "20%" ], [ "sWidth"=> "45%" ], ["sWidth"=> "20%"], ["sWidth"=> "15%" ]])      
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[3]]])
      ->render('datatable') }}


  {{ Former::open('users/delete')->addClass('user-form') }}
  <div style="display:none">
    {{ Former::text('userPublicId') }}
  </div>
  {{ Former::close() }}

  <script>
  window.onDatatableReady = function() {        
    $('tbody tr').mouseover(function() {
      $(this).closest('tr').find('.tr-action').css('visibility','visible');
    }).mouseout(function() {
      $dropdown = $(this).closest('tr').find('.tr-action');
      if (!$dropdown.hasClass('open')) {
        $dropdown.css('visibility','hidden');
      }     
    });
  } 

  function deleteUser(id) {
    if (!confirm('Are you sure?')) {
      return;
    }

    $('#userPublicId').val(id);
    $('form.user-form').submit();    
  }  
  </script>  

@stop
