<div class="c-sidebar c-sidebar-dark c-sidebar-fixed c-sidebar-lg-show {{ request()->routeIs('app.pos.*') ? 'c-sidebar-minimized' : '' }}" id="sidebar">
    <div class="c-sidebar-brand d-md-down-none">
        @php
            $siteLogo = settings()->site_logo ?: asset('images/logo.png');
        @endphp
        <a href="{{ route('home') }}" style="text-decoration:none; display:flex; align-items:center; gap:10px; width:100%; overflow:hidden;">

            {{-- Minimized sidebar: centered logo — CoreUI toggles visibility via c-sidebar-minimized --}}
            <div class="c-sidebar-brand-minimized" style="width:100%; text-align:center;">
                <div style="background:#fff; border-radius:8px; padding:5px 7px; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.3);">
                    <img src="{{ $siteLogo }}" alt="Logo"
                         style="width:32px; height:32px; object-fit:contain; display:block;">
                </div>
            </div>

            {{-- Full sidebar: logo pill + app name --}}
            <div class="c-sidebar-brand-full" style="display:flex; align-items:center; gap:10px; overflow:hidden; width:100%;">
                <div style="background:#fff; border-radius:9px; padding:4px 10px; flex-shrink:0; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                    <img src="{{ $siteLogo }}" alt="Logo"
                         style="max-height:28px; max-width:88px; width:auto; object-fit:contain; display:block;">
                </div>
                <div style="line-height:1.25; overflow:hidden;">
                    <div style="font-size:12px; font-weight:700; color:#fff; white-space:nowrap; letter-spacing:0.01em;">
                        {{ config('app.name') }}
                    </div>
                    <div style="font-size:9px; color:rgba(255,255,255,0.38); white-space:nowrap; letter-spacing:0.06em; text-transform:uppercase;">
                        Inventory &amp; POS
                    </div>
                </div>
            </div>

        </a>
    </div>
    <ul class="c-sidebar-nav">
        @include('layouts.menu')
    </ul>
    <div class="c-sidebar-nav-item" style="padding:12px 16px;border-top:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:10px;margin-top:auto;">
        <div style="width:32px;height:32px;border-radius:50%;background:#f97316;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;">
            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
        </div>
        <div class="c-sidebar-brand-full">
            <div style="font-size:12.5px;font-weight:700;color:#fff;line-height:1.2;">{{ auth()->user()->name ?? 'User' }}</div>
            <div style="font-size:10.5px;color:rgba(255,255,255,0.4);">{{ auth()->user()->roles->first()->name ?? 'User' }}</div>
        </div>
    </div>
    <button class="c-sidebar-minimizer c-class-toggler" type="button" data-target="_parent" data-class="c-sidebar-minimized"></button>
</div>
