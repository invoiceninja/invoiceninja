<div class="app-body">
    <div class="sidebar">
        <nav class="sidebar-nav">
            <ul class="nav">
                @foreach($sidebar as $row)
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route($row['url']) }}">
                            <i class="nav-icon {{$row['icon']}}"></i> {{ $row['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>   
            <div class="ps__rail-x" style="left: 0px; bottom: 0px;">
                <div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div>
            </div>
            <div class="ps__rail-y" style="top: 0px; height: 1142px; right: 0px;">
                <div class="ps__thumb-y" tabindex="0" style="top: 0px; height: 1097px;"></div>
            </div>
        </nav>
            <button class="sidebar-minimizer brand-minimizer" type="button"></button>
    </div>
