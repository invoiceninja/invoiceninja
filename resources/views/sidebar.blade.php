<div class="sidebar">
    <nav class="sidebar-nav">
        <ul class="nav">

            <li class="nav-item ">
                <a class="nav-link" href="{{ route('dashboard.index') }}">
                    <i class="nav-icon icon-speedometer"></i> @lang::get('texts.dashboard')
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="/clients">
                    <i class="nav-icon icon-user"></i> @lang::get('texts.clients')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('invoices.index') }}">
                    <i class="nav-icon icon-notebook"></i> @lang::get('texts.invoices')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-wallet"></i> @lang::get('texts.payments')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-docs"></i> @lang::get('texts.recurring')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-badge"></i> @lang::get('texts.credits')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-vector"></i> @lang::get('texts.quotes')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-wrench"></i> @lang::get('texts.projects')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-grid"></i> @lang::get('texts.tasks')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-envelope-open"></i> @lang::get('texts.expenses')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-bell"></i> @lang::get('texts.vendors')</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="typography.html">
                    <i class="nav-icon icon-printer"></i> @lang::get('texts.reports')</a>
            </li>
        </ul>
    </nav>
    <button class="sidebar-minimizer brand-minimizer" type="button"></button>
</div>