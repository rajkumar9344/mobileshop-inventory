<div class="c-sidebar c-sidebar-dark c-sidebar-fixed c-sidebar-lg-show {{ request()->routeIs('app.pos.*') ? 'c-sidebar-minimized' : '' }}" id="sidebar">
    <div class="c-sidebar-brand d-md-down-none">
        @php
            $siteLogo = settings()->site_logo ?: asset('images/logo.png');
        @endphp
        <a href="{{ route('home') }}" class="d-flex align-items-center" style="gap:10px; text-decoration:none;">
            <div style="width:34px;height:34px;background:#f97316;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-phone" style="color:#fff;font-size:17px;"></i>
            </div>
            <img class="c-sidebar-brand-full" src="{{ $siteLogo }}" alt="Site Logo" style="max-height:32px; width:auto; object-fit:contain;">
            <img class="c-sidebar-brand-minimized" src="{{ $siteLogo }}" alt="Site Logo" style="max-width:34px; width:auto; object-fit:contain;">
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
