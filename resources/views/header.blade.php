
<style type="text/css">

</style>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var elems = document.querySelectorAll('.sidenav');
        var instances = M.Sidenav.init(elems, options);
    });

    // Initialize collapsible (uncomment the lines below if you use the dropdown variation)
    // var collapsibleElem = document.querySelector('.collapsible');
    // var collapsibleInstance = M.Collapsible.init(collapsibleElem, options);

    // Or with jQuery

    $(document).ready(function(){
        $('.sidenav').sidenav();
    });
</script>
<header class="hero is-light">
    <div class="hero-head">
        <nav class="navbar" role="navigation" aria-label="main navigation">
            <div class="navbar-brand">
                <a class="navbar-item" href="https://app.invoiceninja.com/">
                    <img src="https://bulma.io/images/bulma-logo.png" width="112" height="28">
                </a>
            </div>

            <div id="navbarBasicExample" class="navbar-menu">
                <div class="navbar-start">



                    <div class="navbar-item field has-addons">
                        <div class="control has-icons-left">
                            <input class="input" type="email" placeholder="Search">
                              <span class="icon is-small is-left">
                                <i class="fa fa-search"></i>
                              </span>
                        </div>
                    </div>


                </div>

                <div class="navbar-end">
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link">Company Switcher</a>
                        <div class="navbar-dropdown is-right is-boxed">
                            <a class="navbar-item" href="/switch">
                                <span class="icon"><i class="fa fa-gear"></i></span>
                                <span>co 1</span>
                            </a>
                            <a class="navbar-item" href="/switch">
                                <span class="icon"><i class="fa fa-gear"></i></span>
                                <span>co 2</span>
                            </a>
                            <a class="navbar-item" href="/switch">
                                <span class="icon"><i class="fa fa-gear"></i></span>
                                <span>co 3</span>
                            </a>
                            <div class="navbar-divider"></div>
                            <a class="navbar-item" href="/log-out">
                                <span class="icon"><i class="fa fa-sign-out"></i></span>
                                <span>@lang('texts.add_company')</span>
                            </a>
                        </div>
                    </div>
                    <div class="navbar-item has-dropdown is-hoverable">

                        <a class="navbar-link">
                                <img src="https://placehold.it/80x80" style="padding-right: 5px;">
                            </figure>{{ auth()->user()->email }}</a>
                        <div class="navbar-dropdown is-right is-boxed">
                            <a class="navbar-item" href="/settings">
                                <span class="icon"><i class="far fa-user"></i></span>
                                <span>@lang('texts.profile')</span>
                            </a>

                            <div class="navbar-divider"></div>
                            <a class="navbar-item" href="/logout">
                                <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                                <span>@lang('texts.logout')</span>
                            </a>
                        </div>


                    </div>
                </div>
            </div>
        </nav>
    </div>
</header>
