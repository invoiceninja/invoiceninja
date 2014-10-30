@extends('header')

@section('head')
	@parent
@stop

@section('content')

    <ol class="breadcrumb">
        <li>{{ link_to('timesheets', trans('texts.timesheets')) }}</li>
        <li class='active'>{{ 1 }}</li>
    </ol>
    <div id="timesheet_table" style="height: 300px; overflow: auto">
    </div>
    <script type="text/javascript">
        
        var table = $("#timesheet_table");
        table.handsontable({
            colHeaders: ['','Date', 'From', 'To', 'Hours', 'Owner', 'Project', 'Code', 'Tags' ],
            startRows: 6,
            startCols: 4,
            //minSpareRows: 1,
            stretchH: 'last',
            colWidths: [25, 97, 50, 50, 50, 150, 150, 150, 150],
            columns: [
                { data: "selected", type: 'checkbox' },
                { data: "date", type: 'date', dateFormat: 'yy-mm-dd' },
                { data: "from" },
                { data: "to" },
                { data: "hours", type: 'numeric', format: '0.00' },
                { data: "owner" },
                { data: "project", type: 'dropdown', source: ["Hobby", "Large Development"], strict: true },
                { data: "code", type: 'dropdown', source: ["DAMCO", "MYDAMCO", "SAXO", "STUDIE"], strict: true },
                { data: "tags", type: 'dropdown', source: ["design", "development", "bug"], strict: false },
            ],
                //cells: function(row, col, prop) {
                //  return {
                //      type : {
                //        renderer : function (instance, td, row, col, prop, value, cellProperties) {
                //        }
                //      }
                //  };
                //}
        });
        
        
        var data = [
            { selected: true, date:'2014-01-01', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-02', from:'00:00', to:'23:00', hours:'11', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-03', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-04', from:'23:00', to:'24:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-01', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-02', from:'00:00', to:'23:00', hours:'11', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-03', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-04', from:'23:00', to:'24:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-01', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-02', from:'00:00', to:'23:00', hours:'11', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-03', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-04', from:'23:00', to:'24:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-01', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-02', from:'00:00', to:'23:00', hours:'11', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-03', from:'10:00', to:'11:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
            { selected: true, date:'2014-01-04', from:'23:00', to:'24:00', hours:'1', owner:'Troels Liebe Bentsen', project:'Hobby', code:'TEST', tags:'design'},
        ];
        
        table.handsontable("loadData", data);
        


        /*
        $.ajax({
            url: "/api/events",
            dataType: 'json',
            type: 'GET',
            success: function (res) {
                $("#dataTable").data("handsontable").loadData(res);
            }
        });
        */

    </script>
@stop
