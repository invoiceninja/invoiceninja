<script type="text/javascript">

function ViewModel(data) {
    var self = this;

    self.invoice_fields = ko.observableArray();
    self.client_fields = ko.observableArray();
    self.company_fields1 = ko.observableArray();
    self.company_fields2 = ko.observableArray();
    window.field_map = [];

    self.addField = function(section, field, label) {
        self[section].push(field);
        window.field_map[field] = label;
    }

    self.removeInvoiceFields = function(item) {
        self.invoice_fields.remove(item);
    }
    self.removeClientFields = function(item) {
        self.client_fields.remove(item);
    }
    self.removeCompanyFields1 = function(item) {
        self.company_fields1.remove(item);
    }
    self.removeCompanyFields2 = function(item) {
        self.company_fields2.remove(item);
    }
}

function addField(section) {
    var $select = $('#' + section + '_select');
    var field = $select.val();
    var label = $select.find('option:selected').text();
    window.model.addField(section, field, label);
    $select.val(null).blur();
}

$(function() {
    window.model = new ViewModel();

    var selectedFields = {!! json_encode($account->getInvoiceFields()) !!};
    for (var section in selectedFields) {
        if ( ! selectedFields.hasOwnProperty(section)) {
            continue;
        }
        var fields = selectedFields[section];
        for (var field in fields) {
            if ( ! fields.hasOwnProperty(field)) {
                continue;
            }
            var label = fields[field];
            model.addField(section, field, label);
        }
    }

    console.log(selectedFields);


    ko.applyBindings(model);
})

</script>


<style type="text/css">
.field-list {
    width: 100%;
    margin-top: 12px;
}
.field-list tr {
    width: 100%;
    cursor: pointer;
    border-bottom: solid 1px #CCC;
}

.field-list td {
    width: 100%;
    background-color: white;
    padding-top: 10px;
    padding-bottom: 10px;
}

.field-list td i {
    float: left;
    width: 18px;
    padding-top: 2px;
}

.field-list td div {
    float: left;
    width: 146px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.field-list tr:hover .fa {
    visibility: visible;
}

.field-list tr:hover div {
    width: 120px;
}


.field-list .fa {
    visibility: hidden;
}

.field-list .fa-close {
    color: red;
}

.field-list .fa-bars {
    position: absolute;
    right: 20px;
    color: #AAA;
}

</style>
