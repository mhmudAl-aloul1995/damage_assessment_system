<aside class="bg-dark text-white vh-100 position-fixed" style="width:250px;">

    <div class="p-3 border-bottom">
        <h5 class="mb-0">{{ config('app.name') }}</h5>
    </div>

    <ul class="nav flex-column p-2">

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('dashboard') }}">
                {{ __('ui.search.dashboard') }}
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('building.index') }}">
                {{ __('ui.search.buildings') }}
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('apartments.index') }}">
                {{ __('ui.search.housing_units') }}
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('tenants.index') }}">
                {{ __('ui.search.customers') }}
            </a>
        </li>

        <li class="nav-item mt-3 border-top pt-3">
            <small class="text-muted px-3">{{ __('ui.app.brand') }}</small>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white" href="{{ route('users.index') }}">
                {{ __('ui.search.users') }}
            </a>
        </li>

    </ul>

</aside>
