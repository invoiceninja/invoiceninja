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
                <i class="icon-list"></i>
                <span class="badge badge-pill badge-warning">15</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
                <div class="dropdown-header text-center">
                    <strong>You have 5 pending tasks</strong>
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
                <a class="dropdown-item" href="#">
                    <div class="small mb-1">First, wash all car.
                        <span class="float-right">
                        <strong>25%</strong>
                        </span>
                    </div>
                        <span class="progress progress-xs">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                </a>
                <a class="dropdown-item" href="#">
                    <div class="small mb-1">Then wax. Wax on...
                        <span class="float-right">
                        <strong>50%</strong>
                        </span>
                    </div>
                        <span class="progress progress-xs">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                </a>
                <a class="dropdown-item" href="#">
                    <div class="small mb-1">No questions!
                        <span class="float-right">
                        <strong>75%</strong>
                        </span>
                    </div>
                        <span class="progress progress-xs">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                </a>
                <a class="dropdown-item" href="#">
                    <div class="small mb-1">Wax on... wax off. Wax on... wax off.
                        <span class="float-right">
                        <strong>100%</strong>
                        </span>
                    </div>
                        <span class="progress progress-xs">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                </a>
                <a class="dropdown-item text-center" href="#">
                    <strong>View all tasks</strong>
                </a>
            </div>
        </li>

        <li class="nav-item dropdown d-md-down-none" style="padding-left:20px; padding-right: 20px;">
            <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-building" aria-hidden="true"></i> {{ $current_company->present()->name() }}
            </a>
            <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
                <div class="dropdown-header text-center">
                    <strong>trans('texts.manage_companies')</strong>
                </div>

                @foreach($companies as $company) <!-- List all remaining companies here-->
                    <a class="dropdown-item" href="#">
                        <div class="small mb-1">{{ $company->present()->name }}
                        <span class="float-right">
                        </span>
                        </div>
                        <span class="progress progress-xs">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </span>
                    </a>
                @endforeach

                <div class="dropdown-divider"></div>

                <!-- Add Company-->
                @if(count($companies) < 5)
                <a class="dropdown-item" href="{{ route('user.logout') }}">
                    <i class="fa fa-plus"></i> trans('texts.add_company')</a>
                @endif
            </div>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <img class="img-avatar" src="/images/logo.png" alt=""> {{ auth()->user()->present()->name }}
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <div class="dropdown-header text-center">
                    <strong>Settings</strong>
                </div>
                <a class="dropdown-item" href="#">
                    <i class="fa fa-user"></i> Profile</a>
                <a class="dropdown-item" href="{{  route('user.settings') }}">
                    <i class="fa fa-wrench"></i> trans('texts.settings')</a>

                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('user.logout') }}">
                    <i class="fa fa-lock"></i> Logout</a>
            </div>
        </li>
    </ul>
    <button class="navbar-toggler aside-menu-toggler d-md-down-none" type="button" data-toggle="aside-menu-lg-show">
        <span class="navbar-toggler-icon"></span>
    </button>
    <button class="navbar-toggler aside-menu-toggler d-lg-none" type="button" data-toggle="aside-menu-show">
        <span class="navbar-toggler-icon"></span>
    </button>
</header>
<div class="app-body">