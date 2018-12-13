

        <div class="container-fluid">
                @if($client)
                    <span>{{ $client->name }}  </span>
                @endif

                <ul>
                @foreach($client->notes()->get() as $note)
                   <li> {{ $note->description }} </li>
                @endforeach
                </ul>
        </div>
