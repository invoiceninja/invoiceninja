@extends('header')

@section('content')

	{{ Former::open($entityType . 's/bulk')->addClass('listForm') }}
	<div style="display:none">{{ Former::text('action') }}</div>

	{{ DropdownButton::normal('Archive',
		  Navigation::links(
		    array(
		      array('Archive', "javascript:submitForm('archive')"),
		      array('Delete', "javascript:submitForm('delete')"),
		    )
		  )
		, array('id'=>'archive'))->split(); }}
	

	@if (in_array($entityType, [ENTITY_CLIENT, ENTITY_INVOICE]))
	{{ Button::primary_link(URL::to($entityType . 's/create'), 'New ' . ucwords($entityType), array('class' => 'pull-right')) }}	
	@endif
	
	{{ Datatable::table()		
    	->addColumn($columns)
    	->setUrl(route('api.' . $entityType . 's'))    	
    	->setOptions('sPaginationType', 'bootstrap')
    	->setOptions('bFilter', false)
    	->render('datatable') }}

    {{ Former::close() }}

    <script type="text/javascript">

	function submitForm(action) {
		if (action == 'delete') {
			if (!confirm('Are you sure')) {
				return;
			}
		}
		$('#action').val(action);
		$('form.listForm').submit();		
	}

	function deleteEntity(id) {
		if (confirm("Are you sure?")) {
			window.location = "{{ URL::to($entityType . 's') }}/" + id + "/delete";
		}
	}

    </script>

@stop

@section('onReady')
	
	window.onDatatableReady = function() {
		$(':checkbox').click(function() {
			setArchiveEnabled();
		});	

		$('tbody tr').click(function(event) {
			if (event.target.type !== 'checkbox' && event.target.type !== 'button' && event.target.tagName.toLowerCase() !== 'a') {
				$checkbox = $(this).closest('tr').find(':checkbox');
				var checked = $checkbox.prop('checked');
				$checkbox.prop('checked', !checked);
				setArchiveEnabled();
			}
		});

		$('tbody tr').mouseover(function() {
			$(this).closest('tr').find('.tr-action').show();			
		}).mouseout(function() {
			$dropdown = $(this).closest('tr').find('.tr-action');
			if (!$dropdown.hasClass('open')) {
				$dropdown.hide();
			}
		});
	}	

	$('#archive > button').prop('disabled', true);
	$('#archive > button:first').click(function() {
		submitForm('archive');
	});

	$('#selectAll').click(function() {
		$(':checkbox').prop('checked', this.checked);		

	});

	function setArchiveEnabled() {
		var checked = $('tbody :checkbox:checked').length > 0;
		$('#archive > button').prop('disabled', !checked);	
	}


	
@stop