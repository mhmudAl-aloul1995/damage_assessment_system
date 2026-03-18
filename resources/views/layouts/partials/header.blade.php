<header class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2">
    <div class="container-fluid">

        <button class="btn btn-sm btn-outline-secondary me-3" id="sidebarToggle">
            ☰
        </button>

        <h5 class="mb-0">
            @yield('pageName')
        </h5>

        <div class="ms-auto d-flex align-items-center">

            <div class="dropdown">
                <a class="d-flex align-items-center text-decoration-none dropdown-toggle"
                   href="#"
                   role="button"
                   data-bs-toggle="dropdown"
                   aria-expanded="false">

                    <img src="{{ asset('images/user.png') }}" width="32" height="32" class="rounded-circle me-2">
                    <span>{{ auth()->user()->name ?? 'User' }}</span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <a class="dropdown-item" href="{{ route('profile') }}">
                            Profile
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item">
                                Logout
                            </button>
                        </form>
                    </li>

                </ul>
            </div>

        </div>

    </div>
</header>