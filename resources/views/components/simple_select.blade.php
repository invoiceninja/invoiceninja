{!! Former::select($selectId)
    ->addOption('', '')
    ->label($fieldLabel) !!}

@push('component_scripts')
    <script type="text/javascript">

        $(function() {
            var entityType = '{!! $entityType !!}';
            var items = {!! $items !!};
            var secondaryItemLabel = '{!! $secondaryItemLabel !!}';
            var secondaryItemLabelType = '{!! empty($secondaryItemLabelType) ? "field" : $secondaryItemLabelType !!}';

            var itemMap = {};
            var $itemSelect = $('select#{!! $selectId !!}');

            for (var i=0; i<items.length; i++) {
                var entity = items[i];
                var itemName = '';

                itemMap[entity.public_id] = entity;

                switch(entityType) {
                    case '{!! ENTITY_CLIENT !!}':
                        itemName = getClientDisplayName(entity);
                        break;
                    case '{!! ENTITY_CONTACT !!}':
                        itemName = getContactDisplayName(entity);
                        break;
                    default:
                        itemName = entity.{!! $itemLabel !!};

                }

                if (!itemName) {
                    continue;
                }

                var itemNameLabel = '';

                if(secondaryItemLabel != '') {
                    itemNameLabel = {!! empty($secondaryItemLabel) ? "''" : $secondaryItemLabel !!};
                 }

                $itemSelect.append(new Option(itemName + ((itemNameLabel != '') ? ' - ' + itemNameLabel : ''), entity.public_id));
            }
            @if (! empty($itemPublicId))
                $itemSelect.val({{ $itemPublicId }});
            @endif

            $itemSelect.combobox({highlighter: comboboxHighlighter}).change(function() {
                var entity = itemMap[$('#{!! $selectId !!}').val()];
            });
        });
    </script>
@endpush

