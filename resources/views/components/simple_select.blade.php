{!! Former::select($selectId)
    ->addOption('', '')
    ->label($label)
    ->addGroupClass('required') !!}

@push('component_scripts')
    <script type="text/javascript">

        $(function() {
            var entityType = '{!! $entityType !!}';
            var items = {!! $items !!};
            var secondaryItemLabel = '{!! $secondaryItemLabel !!}';

            var itemMap = {};
            var $itemSelect = $('select#{!! $selectId !!}');
            for (var i=0; i<items.length; i++) {
                var item = items[i];
                var itemName = '';

                itemMap[item.public_id] = item;

                switch(entityType) {
                    case '{!! ENTITY_CLIENT !!}':
                        itemName = getClientDisplayName(item);
                        break;
                    case '{!! ENTITY_CONTACT !!}':
                        itemName = getContactDisplayName(item);
                        break;
                    default:
                        itemName = item.{!! $itemLabel !!};

                }

                if (!itemName) {
                    continue;
                }

                var itemNameLabel = '';

                if(secondaryItemLabel != '') {
                    switch(entityType) {
                        case '{!! ENTITY_CLIENT !!}':
                            itemNameLabel = getContactDisplayName(item.{!! $secondaryItemLabel !!});
                            break;
                        default:
                            itemNameLabel = item.{!! $secondaryItemLabel !!};
                    }
                }

                $itemSelect.append(new Option(itemName + ((itemNameLabel) ? ' - ' + itemNameLabel : ''), item.public_id));
            }
            @if (! empty($itemPublicId))
                $itemSelect.val({{ $itemPublicId }});
            @endif

            $itemSelect.combobox({highlighter: comboboxHighlighter}).change(function() {
                var item = itemMap[$('#{!! $selectId !!}').val()];
            });
        });
    </script>
@endpush

