<div class="app-body">
<div class="sidebar">
    <nav class="sidebar-nav">
        <ul class="nav">
            @foreach($sidebar as $row)
                <li class="nav-item ">
                    <a class="nav-link" href="{{ route($row['url']) }}">
                        <span><i class="{{$row['icon']}}"></i></span> <span> {{ $row['title'] }} </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    <button class="sidebar-minimizer brand-minimizer" type="button"></button>
</div>