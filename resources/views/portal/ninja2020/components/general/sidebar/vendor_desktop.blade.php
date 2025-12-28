<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex items-center h-16 flex-shrink-0 px-4 bg-white border-r justify-center z-10">
            <a href="{{ route('vendor.dashboard') }}">
                <img class="h-10 w-auto" src="{!! auth()->guard('vendor')->user()->company->present()->logo($settings) !!}"
                     alt="{{ auth()->guard('vendor')->user()->company->present()->name() }} logo"/>
            </a>
        </div>
        <div class="h-0 flex-1 flex flex-col overflow-y-auto z-0 border-r">
            <nav class="flex-1 pb-4 pt-0 bg-white">
                @foreach($sidebar as $row)
                    <a class="group flex items-center p-4 text-sm leading-5 font-medium hover:font-semibold focus:outline-none focus:bg-primary-darken transition ease-in-out duration-150 {{ isActive($row['url'], true) ? 'bg-primary text-white' : 'text-gray-900' }}"
                       href="{{ route($row['url']) }}"
                       id="{{ $row['id'] }}">
                        @if(isActive($row['url'], true))
                            <img src="{{ asset('images/svg/' . $row['icon'] . '.svg') }}"
                                 class="w-5 h-5 fill-current text-white mr-3" alt=""/>
                        @else
                            <img src="{{ asset('images/svg/dark/' . $row['icon'] . '.svg') }}"
                                 class="w-5 h-5 fill-current text-white mr-3" alt=""/>
                        @endif

                        <span>{{ $row['title'] }}</span>
                    </a>
                @endforeach
            </nav>

            @if(!auth()->guard('vendor')->user()->user->account->isPaid())
                <div class="flex-shrink-0 flex bg-white p-4 justify-center">
                    <div class="flex items-center">
                            <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg"
                                 width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </a>
                        <a target="_blank" href="https://www.youtube.com/channel/UCXAHcBvhW05PDtWYIq7WDFA">
                            <svg class="text-gray-900 hover:text-gray-300 mr-4" xmlns="http://www.w3.org/2000/svg"
                                 width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @endif
        </div>
        <div class="flex-shrink-0 w-14"></div>
    </div>
</div>
