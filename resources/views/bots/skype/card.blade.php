{
   "type":"message/card.carousel",
   "attachments":[
      {
         "contentType":"application/vnd.microsoft.card.hero",
         "content":{
            "title":"{!! $title !!}"
            @if ( ! empty($subtitle))
                , "subtitle":"{!! $subtitle !!}"
            @endif
            @if ( ! empty($text))
                , "text":"{!! $text !!}"
            @endif
            @if ( ! empty($images))
                , "images":[
                @foreach($images as $image)
                    @if ($images[0] != $image)
                        ,
                    @endif
                    {
                      "image":"{{ $image }}"
                    }
                @endforeach
                ]
            @endif
            @if ( ! empty($buttons))
                , "buttons":[
                @foreach($buttons as $button)
                    @if ($buttons[0] != $button)
                        ,
                    @endif
                   {
                      "type":"{{ $button['type'] }}",
                      "title":"{!! $button['title'] !!}",
                      "value":"{!! $button['value'] !!}"
                   }
                @endforeach
                ]
            @endif
         }
      }
   ]
}
