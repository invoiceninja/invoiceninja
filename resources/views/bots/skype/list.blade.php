{
   "type":"message/card.carousel",
   "attachments":[
    @foreach ($items as $item)
        @if ($items[0] != $item)
           ,
        @endif
       {
           "contentType": "application/vnd.microsoft.card.hero",
           "content": {
               "title": "{{ $item['title'] }}",
               "subtitle": "{{ $item['subtitle'] }}",
               "buttons": [
                   @foreach($item['buttons'] as $button)
                       @if ($items['buttons'][0] != $button)
                          ,
                       @endif
                       {
                           "type": "{{ $button['type'] }}",
                           "title": "{{ $button['title'] }}",
                           "value": "https://en.wikipedia.org/wiki/{cardContent.Key}"
                       }
                   @endforeach
               ]
           }
       }
       @endforeach
   ]
}
