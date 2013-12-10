@extends('header')

@section('content')

	{{ Former::open($entityType . 's/bulk')->addClass('listForm') }}
	<div style="display:none">
		{{ Former::text('action') }}
		{{ Former::text('id') }}
	</div>

	{{ DropdownButton::normal('Archive',
		  Navigation::links(
		    array(
		      array('Archive '.ucwords($entityType), "javascript:submitForm('archive')"),
		      array('Delete '.ucwords($entityType), "javascript:submitForm('delete')"),
		    )
		  )
		, array('id'=>'archive'))->split(); }}
	

	{{ Button::primary_link(URL::to($entityType . 's/create'), 'New ' . Utils::getEntityName($entityType), array('class' => 'pull-right')) }}	
	
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
			$('#id').val(id);
			submitForm('delete');	
		}
	}

	function archiveEntity(id) {
		$('#id').val(id);
		submitForm('archive');
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
			$(this).closest('tr').find('.tr-action').css('visibility','visible');
		}).mouseout(function() {
			$dropdown = $(this).closest('tr').find('.tr-action');
			if (!$dropdown.hasClass('open')) {
				$dropdown.css('visibility','hidden');
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