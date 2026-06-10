<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Sign In | {{ config('app.name') }}</title>

    <link rel="icon" href="{{ settings()->site_logo ?: asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #f1f5f9;
        }

        /* ── Left brand panel ── */
        .login-brand {
            flex: 0 0 420px;
            background: linear-gradient(155deg, #1e293b 0%, #0f172a 60%, #172033 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            width: 380px; height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(249,115,22,0.12) 0%, transparent 70%);
            top: -80px; right: -80px;
        }
        .login-brand::after {
            content: '';
            position: absolute;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(249,115,22,0.08) 0%, transparent 70%);
            bottom: -50px; left: -50px;
        }

        .brand-icon-wrap {
            width: 88px; height: 88px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.6rem; color: #fff;
            box-shadow: 0 8px 32px rgba(249,115,22,0.4);
            margin-bottom: 1.75rem;
            position: relative; z-index: 1;
        }

        .brand-title {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
            position: relative; z-index: 1;
            text-align: center;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.5);
            margin-top: 0.4rem;
            margin-bottom: 2.5rem;
            text-align: center;
            position: relative; z-index: 1;
        }

        .brand-features {
            list-style: none;
            width: 100%;
            position: relative; z-index: 1;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            font-size: 0.875rem;
            color: rgba(255,255,255,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .brand-features li:last-child { border-bottom: none; }
        .brand-features li i {
            width: 28px; height: 28px;
            background: rgba(249,115,22,0.18);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fb923c;
            font-size: 13px;
            flex-shrink: 0;
        }

        .brand-divider {
            width: 40px; height: 3px;
            background: linear-gradient(90deg, #f97316, transparent);
            border-radius: 2px;
            margin: 1.5rem 0;
            position: relative; z-index: 1;
        }

        /* ── Right form panel ── */
        .login-form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f1f5f9;
        }

        .login-form-inner {
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo img {
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }

        .login-heading {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }
        .login-sub {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        .form-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 8px 24px -4px rgba(0,0,0,.10);
            padding: 2rem;
        }

        .field-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.4rem;
        }

        .field-wrap {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .field-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }

        .field-input {
            width: 100%;
            padding: 0.6rem 0.9rem 0.6rem 2.5rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.6rem;
            font-size: 0.9rem;
            font-family: inherit;
            color: #334155;
            background: #f8fafc;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            outline: none;
        }
        .field-input:focus {
            border-color: #f97316;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
        }
        .field-input.is-invalid { border-color: #ef4444; }
        .invalid-feedback { color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block; }

        .toggle-password {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            line-height: 1;
        }
        .toggle-password:hover { color: #f97316; }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .remember-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
            cursor: pointer;
        }
        .remember-check input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #f97316;
            cursor: pointer;
        }
        .forgot-link {
            font-size: 0.85rem;
            color: #f97316;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover { color: #ea580c; text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 0.7rem 1.5rem;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: #fff;
            border: none;
            border-radius: 0.6rem;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(249,115,22,0.35);
            transition: opacity 0.15s, transform 0.12s, box-shadow 0.15s;
        }
        .btn-login:hover:not(:disabled) {
            opacity: 0.92;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(249,115,22,0.42);
        }
        .btn-login:active:not(:disabled) { transform: translateY(0); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }

        .login-spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .alert-deactivated {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 0.6rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .login-brand {
                flex: none;
                padding: 2rem 1.5rem;
                border-radius: 0 0 1.5rem 1.5rem;
            }
            .brand-features { display: none; }
            .brand-divider { display: none; }
            .login-form-panel { padding: 1.5rem 1rem; }
        }
    </style>
</head>

<body>
    {{-- Left: Brand Panel --}}
    <div class="login-brand">
        <div class="brand-icon-wrap">
            <i class="bi bi-phone"></i>
        </div>
        <div class="brand-title">{{ config('app.name', 'Mobile Shop') }}</div>
        <div class="brand-subtitle">Inventory & POS Management</div>
        <div class="brand-divider"></div>
        <ul class="brand-features">
            <li>
                <i class="bi bi-cart-check-fill"></i>
                Sales &amp; Purchase Tracking
            </li>
            <li>
                <i class="bi bi-bell-fill"></i>
                Real-time Stock Alerts
            </li>
            <li>
                <i class="bi bi-graph-up-arrow"></i>
                Profit &amp; Loss Reports
            </li>
            <li>
                <i class="bi bi-people-fill"></i>
                Customer Credit Management
            </li>
            <li>
                <i class="bi bi-shield-fill-check"></i>
                Role-based Access Control
            </li>
        </ul>
    </div>

    {{-- Right: Form Panel --}}
    <div class="login-form-panel">
        <div class="login-form-inner">

            <div class="login-logo">
                <img src="{{ settings()->site_logo ?: asset('images/logo-dark.png') }}" alt="{{ config('app.name') }}">
            </div>

            <div class="login-heading">Welcome back</div>
            <div class="login-sub">Sign in to your account to continue</div>

            @if(Session::has('account_deactivated'))
                <div class="alert-deactivated">
                    <i class="bi bi-exclamation-triangle-fill mr-1"></i>
                    {{ Session::get('account_deactivated') }}
                </div>
            @endif

            <div class="form-card">
                <form id="login-form" method="post" action="{{ url('/login') }}">
                    @csrf

                    {{-- Email --}}
                    <label class="field-label" for="email">Email Address</label>
                    <div class="field-wrap">
                        <i class="bi bi-envelope field-icon"></i>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            class="field-input @error('email') is-invalid @enderror"
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <label class="field-label" for="password">Password</label>
                    <div class="field-wrap">
                        <i class="bi bi-lock field-icon"></i>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            class="field-input @error('password') is-invalid @enderror"
                        >
                        <button type="button" class="toggle-password" id="toggle-pw" title="Show/hide password">
                            <i class="bi bi-eye" id="pw-eye"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Remember + Forgot --}}
                    <div class="remember-row">
                        <label class="remember-check">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            Remember me
                        </label>
                        <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" class="btn-login" id="btn-login">
                        <span id="login-spinner" class="login-spinner"></span>
                        <span id="login-text">Sign In</span>
                        <i class="bi bi-arrow-right" id="login-arrow"></i>
                    </button>
                </form>
            </div>

            <div class="login-footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        // Show spinner on submit
        document.getElementById('login-form').addEventListener('submit', function () {
            var btn = document.getElementById('btn-login');
            btn.disabled = true;
            document.getElementById('login-spinner').style.display = 'block';
            document.getElementById('login-arrow').style.display = 'none';
            document.getElementById('login-text').textContent = 'Signing in...';
            setTimeout(function () { btn.disabled = false; document.getElementById('login-spinner').style.display = 'none'; document.getElementById('login-arrow').style.display = ''; document.getElementById('login-text').textContent = 'Sign In'; }, 5000);
        });

        // Password toggle
        document.getElementById('toggle-pw').addEventListener('click', function () {
            var pw = document.getElementById('password');
            var eye = document.getElementById('pw-eye');
            if (pw.type === 'password') {
                pw.type = 'text';
                eye.className = 'bi bi-eye-slash';
            } else {
                pw.type = 'password';
                eye.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>
