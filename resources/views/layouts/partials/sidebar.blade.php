<aside class="bg-dark text-white vh-100 position-fixed" style="width:250px;">

    <div class="p-3 border-bottom">
        <h5 class="mb-0">{{ config('app.name') }}</h5>
    </div>

    <ul class="nav flex-column p-2">

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('dashboard') }}">
                Dashboard
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('building.index') }}">
                Buildings
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('apartments.index') }}">
                Apartments
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('tenants.index') }}">
                Tenants
            </a>
        </li>

        <li class="nav-item mt-3 border-top pt-3">
            <small class="text-muted px-3">SYSTEM</small>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('users.index') }}">
                Users
            </a>
        </li>

    </ul>

</aside>