<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
<header class="app-header navbar">
    <button class="navbar-toggler sidebar-toggler d-lg-none mr-auto" type="button" data-toggle="sidebar-show">
        <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand" href="https://invoiceninja.com">
        <img class="navbar-brand-full" src="/images/logo.png" width="50" height="50" alt="Invoice Ninja Logo">
        <img class="navbar-brand-minimized" src="/images/logo.png" width="30" height="30" alt="Invoice Ninja Logo">
    </a>
    <button class="sidebar-minimizer brand-minimizer" type="button">
        <span class="navbar-toggler-icon"></span>
    </button>

    <ul class="nav navbar-nav ml-auto">
        <li class="nav-item dropdown d-md-down-none" style="padding-left:20px; padding-right: 20px;">
            <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-envelope" aria-hidden="true"></i> 
                <span class="badge badge-pill badge-warning">15</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
                <div class="dropdown-header text-center">
                    <strong>@lang('texts.notifications')</strong>
                </div>
                <a class="dropdown-item" href="#">
                    <div class="small mb-1">Mr Miyagi todos
                        <span class="float-right">
                        <strong>0%</strong>
                        </span>
                    </div>
                        <span class="progress progress-xs">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                </a>
                <a class="dropdown-item text-center" href="#">
                    <strong>@lang('texts.more')</strong>
                </a>
            </div>
        </li>

        <li class="nav-item dropdown" style="padding-right: 20px;">
            <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <img class="img-avatar" src="/images/logo.png" alt=""> {{ auth()->user()->present()->name() }}
            </a>
            <div class="dropdown-menu dropdown-menu-sm">
                <div class="dropdown-header text-center">
                    <strong>@lang('texts.settings')</strong>
                </div>
                <a class="dropdown-item" href="{{ route('client.profile.edit', ['id' => auth()->user()->hashed_id])}}">
                    <i class="fa fa-user"></i> @lang('texts.profile')</a>
                <a class="dropdown-item" href="">
                    <i class="fa fa-wrench"></i> @lang('texts.settings')</a>

                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('client.logout') }}">
                    <i class="fa fa-lock"></i> @lang('texts.logout')</a>
            </div>
        </li>
    </ul>
</header>
