{
   "type":"message/card.receipt",
   "attachments":[
      {
         "contentType":"application/vnd.microsoft.card.receipt",
         "content":{
            "title" : '{!! trans('texts.invoice_for_client', [
                'invoice' => link_to($invoice->getRoute(), $invoice->invoice_number),
                'client' => link_to($invoice->client->getRoute(), $invoice->client->getDisplayName())
            ]) !!}',
            "facts": [
                {
                    "key": "{{ trans('texts.email') }}:",
                    "value": "{!! addslashes(HTML::mailto($invoice->client->contacts[0]->email)->toHtml()) !!}"
                }
                @if ($invoice->due_date)
                    , {
                        "key": "{{ $invoice->present()->dueDateLabel }}:",
                        "value": "{{ $invoice->present()->due_date }}"
                    }
                @endif
                @if ($invoice->po_number)
                    , {
                        "key": "{{ trans('texts.po_number') }}:",
                        "value": "{{ $invoice->po_number }}"
                    }
                @endif
                @if ($invoice->discount)
                    , {
                        "key": "{{ trans('texts.discount') }}:",
                        "value": "{{ $invoice->present()->discount }}"
                    }
                @endif
            ],
            "items":[
                @foreach ($invoice->invoice_items as $item)
                    @if ($invoice->invoice_items[0] != $item)
                        ,
                    @endif
                    {
                      "title":"{{ $item->product_key }}",
                      "subtitle":"{{ $item->notes }}",
                      "price":"{{ $item->cost }}",
                      "quantity":"{{ $item->qty }}"
                    }
                @endforeach
            ],
            @if (false)
                "tax":"0.00",
            @endif
            "total":"{{ $invoice->present()->requestedAmount }}",
            "buttons":[
                {
                   "type":"imBack",
                   "title":"{{ trans('texts.send_email') }}",
                   "value":"send_email"
                },
                {
                   "type":"imBack",
                   "title":"{{ trans('texts.download_pdf') }}",
                   "value":"download_pdf"
                }
            ]
         }
      }
   ]
}
