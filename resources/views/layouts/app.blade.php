<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AI Price & RAB Validator')</title>
    <meta name="description" content="Sistem validasi kewajaran harga material dan jasa pada RAB proyek renovasi berbasis AI.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-2: #eef3fa;
            --border: #d6e0ef;
            --accent: #0f2f5f;
            --accent-hover: #184889;
            --accent-glow: rgba(15, 47, 95, 0.16);
            --success: #1f7a55;
            --warning: #9a6b0a;
            --danger: #b4232f;
            --info: #235ca6;
            --text: #16253b;
            --text-muted: #5a6f8e;
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* NAV */
        nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text);
            text-decoration: none;
        }
        .nav-brand .logo-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--accent), #1c4b88);
            color: #fff;
            border-radius: var(--radius-sm);
            display: grid; place-items: center;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .nav-links { display: flex; gap: 1.5rem; }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--text); }

        /* LAYOUT */
        .container { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem; }

        /* ALERTS */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid;
        }
        .alert-success { background: rgba(16,185,129,0.1); border-color: var(--success); color: var(--success); }
        .alert-danger  { background: rgba(239,68,68,0.1); border-color: var(--danger); color: var(--danger); }

        /* CARDS */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.75rem;
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: var(--text);
        }

        /* FORM ELEMENTS */
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            padding: 0.6rem 0.85rem;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        select option { background: var(--surface-2); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-error { font-size: 0.78rem; color: var(--danger); margin-top: 0.3rem; }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 0.65rem 1.3rem;
            border-radius: var(--radius-sm);
            font-weight: 600; font-size: 0.875rem;
            cursor: pointer; border: none;
            transition: all 0.2s; text-decoration: none;
            font-family: 'Inter', sans-serif;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #1c4b88);
            color: #fff;
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        .btn-primary:hover { filter: brightness(1.15); transform: translateY(-1px); }
        .btn-danger { background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }
        .btn-secondary { background: var(--surface-2); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }

        /* TABLE */
        .table-wrapper { overflow-x: auto; border-radius: var(--radius-sm); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--surface-2); color: var(--text-muted); font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.75rem 1rem; text-align: left; }
        td { padding: 0.85rem 1rem; border-top: 1px solid var(--border); font-size: 0.875rem; vertical-align: middle; }
        tr:hover td { background: #f7faff; }

        /* BADGES */
        .badge {
            display: inline-flex; align-items: center;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-success  { background: rgba(16,185,129,0.15); color: var(--success); }
        .badge-danger   { background: rgba(239,68,68,0.15); color: var(--danger); }
        .badge-warning  { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-neutral  { background: rgba(100,116,139,0.2); color: #94a3b8; }
        .badge-indigo   { background: rgba(99,102,241,0.15); color: var(--accent-hover); }

        /* MISC */
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.35rem; }
        .page-header p { color: var(--text-muted); font-size: 0.95rem; }

        .divider { height: 1px; background: var(--border); margin: 2rem 0; }
        .text-muted { color: var(--text-muted); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .mt-1 { margin-top: 0.5rem; } .mt-2 { margin-top: 1rem; } .mt-3 { margin-top: 1.5rem; } .mt-4 { margin-top: 2rem; }
        .mb-2 { margin-bottom: 1rem; }
        .flex { display: flex; } .items-center { align-items: center; } .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.75rem; } .gap-3 { gap: 1rem; }
        .flex-wrap { flex-wrap: wrap; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; display: inline-block; }
    </style>

    @stack('styles')
</head>
<body>

<nav>
    <a href="{{ route('validator.index') }}" class="nav-brand">
        <span class="logo-icon">SV</span>
        RAB Validator
    </a>
    <div class="nav-links">
        <a href="{{ route('validator.index') }}" class="{{ request()->routeIs('validator.index') ? 'active' : '' }}">Form Validasi</a>
        <a href="{{ route('validator.history') }}" class="{{ request()->routeIs('validator.history') ? 'active' : '' }}">Riwayat</a>
    </div>
</nav>

<main class="container">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Validasi gagal:</strong>
            <ul style="margin-top:0.4rem;padding-left:1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
