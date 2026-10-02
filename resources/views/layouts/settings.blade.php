{{-- resources/views/settings/_nav.blade.php --}}
<nav class="settings-nav">
    <div class="settings-nav-title">
        &#9881; Settings
    </div>
    <ul>
        @if(Auth::user()->canActAsOwner())
        <li>
            <a href="{{ route('settings.business') }}"
               class="{{ request()->routeIs(
                'settings.business')
                ? 'active' : '' }}">
                &#127968; Business Profile
            </a>
        </li>
        @endif
        <li>
            <a href="{{ route('settings.password') }}"
               class="{{ request()->routeIs(
                'settings.password')
                ? 'active' : '' }}">
                &#128274; Change Password
            </a>
        </li>
        @if(Auth::user()->canActAsOwner() || Auth::user()->hasRole('manager'))
        <li>
            <a href="{{ route('settings.team') }}"
               class="{{ request()->routeIs(
                'settings.team*')
                ? 'active' : '' }}">
                &#128101; Team Members
            </a>
        </li>
        @if(Auth::user()->currentBusiness()?->organization?->hasFeature('payroll'))
        <li>
            <a href="{{ route('settings.payroll') }}"
               class="{{ request()->routeIs('settings.payroll') ? 'active' : '' }}">
                &#9881; Payroll Setup
            </a>
        </li>
        @endif
        @endif
        <li>
            <a href="{{ route('settings.subscription') }}"
               class="{{ request()->routeIs(
                'settings.subscription')
                ? 'active' : '' }}">
                &#128176; Subscription
            </a>
        </li>
    </ul>
</nav>