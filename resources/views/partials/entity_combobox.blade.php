var {{ $entityType }}Name = '';

${{ $entityType }}Select.combobox({
    highlighter: function (item) {
        if (item.indexOf("{{ trans("texts.create_{$entityType}") }}") == 0) {
            {{ $entityType }}Name = this.query;
            return "{{ trans("texts.create_{$entityType}") }}: " + this.query;
        } else {
            var query = this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
            return item.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
              return '<strong>' + match + '</strong>';
            })
        }
    },
    template: '<div class="combobox-container"> <input type="hidden" /> <div class="input-group"> <input type="text" id="{{ $entityType }}_name" name="{{ $entityType }}_name" autocomplete="off" /> <span class="input-group-addon dropdown-toggle" data-dropdown="dropdown"> <span class="caret" /> <i class="fa fa-times"></i> </span> </div> </div> ',
    matcher: function (item) {
        // if the user has entered a value show the 'Create ...' option
        if (item.indexOf("{{ trans("texts.create_{$entityType}") }}") == 0) {
            return this.query.length;
        }
        return ~item.toLowerCase().indexOf(this.query.toLowerCase());
    }
}).on('change', function(e) {
    var {{ $entityType }}Id = $('input[name={{ $entityType }}_id]').val();
    if ({{ $entityType }}Id == '-1') {
        $('#{{ $entityType }}_name').val({{ $entityType }}Name);
    }
});
