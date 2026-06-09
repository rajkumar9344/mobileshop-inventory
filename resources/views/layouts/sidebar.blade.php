<div class="c-sidebar c-sidebar-dark c-sidebar-fixed c-sidebar-lg-show {{ request()->routeIs('app.pos.*') ? 'c-sidebar-minimized' : '' }}" id="sidebar">
    <div class="c-sidebar-brand d-md-down-none">
        @php
            $siteLogo = settings()->site_logo ?: asset('images/logo.png');
        @endphp
        <a href="{{ route('home') }}">
            <img class="c-sidebar-brand-full" src="{{ $siteLogo }}" alt="Site Logo" style="max-width: 110px; width: auto; height: auto; object-fit: contain;">
            <img class="c-sidebar-brand-minimized" src="{{ $siteLogo }}" alt="Site Logo" style="max-width: 40px; width: auto; height: auto; object-fit: contain;">
        </a>
    </div>
    <ul class="c-sidebar-nav">
        @include('layouts.menu')
    </ul>
    <button class="c-sidebar-minimizer c-class-toggler" type="button" data-target="_parent" data-class="c-sidebar-minimized"></button>
</div>
