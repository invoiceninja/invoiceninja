{!! Former::select($selectId)
    ->addOption('', '')
    ->label($label)
    ->addGroupClass('client-select required') !!}

@push('component_scripts')
    <script type="text/javascript">
        $(function() {
            var clients = {!! $clients !!};
            var displayContact = {!! $displayContact == true ? 1 : 0 !!};

            var clientMap = {};
            var $clientSelect = $('select#{!! $selectId !!}');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];

                clientMap[client.public_id] = client;

                var clientName = getClientDisplayName(client);
                if (!clientName) {
                    continue;
                }

                if(displayContact) {
                    var contactName = getContactDisplayName(client.contacts[0]);
                }

                $clientSelect.append(new Option(clientName + ((displayContact && contactName) ? ' - ' + contactName : ''), client.public_id));
            }
            @if (! empty($clientPublicId))
                $clientSelect.val({{ $clientPublicId }});
            @endif

            $clientSelect.combobox({highlighter: comboboxHighlighter}).change(function() {
                var client = clientMap[$('#{!! $selectId !!}').val()];
            });

            @if (! empty($clientPublicId) && $clientPublicId)
                $('#name').focus();
            @else
                $('.client-select input.form-control').focus();
            @endif
        });
    </script>
@endpush

