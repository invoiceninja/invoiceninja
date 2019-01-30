<script type="text/javascript">

function ViewModel(data) {
    var self = this;

    self.invoice_fields = ko.observableArray();
    self.client_fields = ko.observableArray();
    self.account_fields1 = ko.observableArray();
    self.account_fields2 = ko.observableArray();
    self.product_fields = ko.observableArray();
    self.task_fields = ko.observableArray();
    window.field_map = [];

    self.addField = function(section, field, label) {
        if (self[section].indexOf(field) < 0) {
            self[section].push(field);
        }
    }

    self.resetInvoiceFields = function() {
        self.invoice_fields.removeAll();
        self.client_fields.removeAll();
        self.account_fields1.removeAll();
        self.account_fields2.removeAll();
    }

    self.resetProductFields = function() {
        self.product_fields.removeAll();
        self.task_fields.removeAll();
    }

    self.onChange = function() {
        self.updateSelects();
        refreshPDF();
        NINJA.formIsChanged = true;
    }

    self.updateSelects = function() {
        var usedFields = [].concat(
            self.invoice_fields(),
            self.client_fields(),
            self.account_fields1(),
            self.account_fields2());
        var selects = [
            'invoice_fields',
            'client_fields',
            'account_fields1',
            'account_fields2',
            'product_fields',
            'task_fields',
        ];

        for (var i=0; i<selects.length; i++) {
            var select = selects[i];
            $('#' + select + '_select > option').each(function() {
                if (select == 'product_fields') {
                    usedFields = self.product_fields();
                } else if (select == 'task_fields') {
                    usedFields = self.task_fields();
                }
                var isUsed = usedFields.indexOf(this.value) >= 0;
                $(this).css('color', isUsed ? '#888' : 'black');
            });
        }
    }

    self.onDragged = function() {
        self.onChange();
    }

    self.removeInvoiceFields = function(item) {
        self.invoice_fields.remove(item);
        self.onChange();
    }
    self.removeClientFields = function(item) {
        self.client_fields.remove(item);
        self.onChange();
    }
    self.removeAccountFields1 = function(item) {
        self.account_fields1.remove(item);
        self.onChange();
    }
    self.removeAccountFields2 = function(item) {
        self.account_fields2.remove(item);
        self.onChange();
    }
    self.removeProductFields = function(item) {
        self.product_fields.remove(item);
        self.onChange();
    }
    self.removeTaskFields = function(item) {
        self.task_fields.remove(item);
        self.onChange();
    }
}

function addField(section) {
    var $select = $('#' + section + '_select');
    var field = $select.val();
    var label = $select.find('option:selected').text();
    window.model.addField(section, field, label);
    window.model.onChange();
    $select.val(null).blur();
}

$(function() {
    window.model = new ViewModel();

    var selectedFields = {!! json_encode($account->getInvoiceFields()) !!};
    var allFields = {!! json_encode($account->getAllInvoiceFields()) !!};

    loadFields(selectedFields);
    loadMap(allFields);

    model.updateSelects();
    ko.applyBindings(model);
})

function resetInvoiceFields() {
    var defaultFields = {!! json_encode($account->getDefaultInvoiceFields()) !!};
    window.model.resetInvoiceFields();
    loadFields(defaultFields, 'invoice');
    window.model.onChange();
}

function resetProductFields() {
    var defaultFields = {!! json_encode($account->getDefaultInvoiceFields()) !!};
    window.model.resetProductFields();
    loadFields(defaultFields, 'product');
    window.model.onChange();
}

function loadMap(allFields) {
    for (var section in allFields) {
        if ( ! allFields.hasOwnProperty(section)) {
            continue;
        }
        var fields = allFields[section];
        for (var field in fields) {
            if ( ! fields.hasOwnProperty(field)) {
                continue;
            }
            var label = fields[field];
            window.field_map[field] = label;
        }
    }
}

function loadFields(selectedFields, filter) {
    for (var section in selectedFields) {
        if ( ! selectedFields.hasOwnProperty(section)) {
            continue;
        }

        if (filter == 'invoice' && (section == 'product_fields' || section == 'task_fields')) {
            continue;
        } else if (filter == 'product' && (section != 'product_fields' && section != 'task_fields')) {
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
}

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
    xwidth: 146px;
    width: 100%;
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

</style>
