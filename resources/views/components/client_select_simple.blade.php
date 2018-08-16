{!! Former::select('client_id')
    ->addOption('', '')
    ->label(trans('texts.client'))
    ->addGroupClass('client-select required') !!}

@push('component_scripts')
    <script type="text/javascript">
        var clients = {!! $clients !!};
        var clientMap = {};

         $(function() {
            var $clientSelect = $('select#client_id');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                                clientMap[client.public_id] = client;
                var clientName = getClientDisplayName(client);
                if (!clientName) {
                    continue;
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }
            @if (! empty($clientPublicId))
                $clientSelect.val({{ $clientPublicId }});
            @endif

            $clientSelect.combobox({highlighter: comboboxHighlighter}).change(function() {
                var client = clientMap[$('#client_id').val()];
            });

            @if (! empty($clientPublicId) && $clientPublicId)
                $('#name').focus();
            @else
                $('.client-select input.form-control').focus();
            @endif
        });
    </script>
@endpush

