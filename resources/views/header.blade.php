
<script>

    // https://bulma.io/documentation/components/navbar/
    document.addEventListener('DOMContentLoaded', () => {

        // Get all "navbar-burger" elements
        const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Check if there are any navbar burgers
    if ($navbarBurgers.length > 0) {

        // Add a click event on each of them
        $navbarBurgers.forEach( el => {
            el.addEventListener('click', () => {

            // Get the target from the "data-target" attribute
            const target = el.dataset.target;
        const $target = document.getElementById(target);

        // Toggle the "is-active" class on both the "navbar-burger" and the "navbar-menu"
        el.classList.toggle('is-active');
        $target.classList.toggle('is-active');

    });
    });
    }
    });
</script>

<header class="hero is-light">
    <div class="hero-head">
        <nav class="navbar" role="navigation" aria-label="main navigation">
            <div class="navbar-brand">
                <a class="navbar-item" href="https://app.invoiceninja.com/">
                    <img src="https://bulma.io/images/bulma-logo.png" width="112" height="28">
                </a>

                <a role="button" class="navbar-burger burger" aria-label="menu" aria-expanded="false" data-target="navbarBasicExample">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </a>
            </div>

            <div id="navbarBasicExample" class="navbar-menu">
                <div class="navbar-start">

                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link">
                            More
                        </a>

                        <div class="navbar-dropdown">
                            <a class="navbar-item">
                                About
                            </a>
                            <a class="navbar-item">
                                Jobs
                            </a>
                            <a class="navbar-item">
                                Contact
                            </a>
                            <hr class="navbar-divider">
                            <a class="navbar-item">
                                Report an issue
                            </a>
                        </div>
                    </div>

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
                                <span>Add Co</span>
                            </a>
                        </div>
                    </div>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link">Ninja User</a>
                        <div class="navbar-dropdown is-right is-boxed">
                            <a class="navbar-item" href="/settings">
                                <span class="icon"><i class="fa fa-gear"></i></span>
                                <span>Settings</span>
                            </a>

                            <div class="navbar-divider"></div>
                            <a class="navbar-item" href="/log-out">
                                <span class="icon"><i class="fa fa-sign-out"></i></span>
                                <span>Log out</span>
                            </a>
                        </div>

                        <div class="buttons">
                            <a class="button is-primary">
                                <strong>Sign up</strong>
                            </a>
                            <a class="button is-light">
                                Log in
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</header>
