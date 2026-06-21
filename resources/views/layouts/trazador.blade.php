<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Trazador UCI') — Clínica de Occidente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --tz-nav-h: 56px;
            --tz-primary: #1a3a5c;
            --tz-accent:  #2d6a9f;
        }
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }

        /* ── Barra de navegación propia del trazador ── */
        #tz-topbar {
            position: fixed; top: 0; left: 0; right: 0;
            height: var(--tz-nav-h);
            background: var(--tz-primary);
            display: flex; align-items: center;
            padding: 0 1.25rem; gap: 1rem;
            z-index: 1040;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .tz-brand {
            display: flex; align-items: center; gap: .6rem;
            color: #fff; font-size: .8rem; font-weight: 700;
            letter-spacing: .4px; text-decoration: none;
            white-space: nowrap;
        }
        .tz-brand img { height: 30px; filter: brightness(0) invert(1); }
        .tz-brand .tz-sep { opacity: .35; margin: 0 .1rem; }
        .tz-title {
            flex: 1;
            color: rgba(255,255,255,.92);
            font-size: .9rem; font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tz-badge-estado {
            font-size: .65rem; padding: .25em .6em;
            border-radius: 20px; font-weight: 700;
            white-space: nowrap;
        }
        .tz-btn-volver {
            background: rgba(255,255,255,.12);
            color: #fff; border: 1px solid rgba(255,255,255,.2);
            font-size: .8rem; padding: .3rem .8rem;
            border-radius: 6px; text-decoration: none;
            display: inline-flex; align-items: center; gap: .35rem;
            transition: background .15s;
        }
        .tz-btn-volver:hover { background: rgba(255,255,255,.22); color: #fff; }

        /* ── Contenido ── */
        #tz-content {
            margin-top: var(--tz-nav-h);
            padding: 1.25rem 1.5rem 2rem;
        }

        /* ── Alerts ── */
        .tz-alert-success { border-left: 4px solid #198754; }
        .tz-alert-error   { border-left: 4px solid #dc3545; }
        .tz-alert-warning { border-left: 4px solid #fd7e14; }

        /* ── Cards y tablas ── */
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
        .card-header { background: transparent; border-bottom: 1px solid #f0f0f0; font-weight: 600; }
        .table > thead > tr > th {
            background: #f8f9fa; font-size: .8rem; font-weight: 600;
            color: #5a5a5a; border-bottom: 2px solid #e5e9f0;
        }
        .table > tbody > tr > td { font-size: .875rem; vertical-align: middle; }

        /* ── Campos del formulario ── */
        .campo-amarillo { background: #fffde7 !important; border-color: #f0cc00 !important; }
        .campo-gris     { background: #f5f5f5 !important; color: #666 !important; border-color: #ddd !important; }
        .label-req      { font-size: .8rem; font-weight: 600; color: #444; }
        .seccion-titulo {
            background: #e8f0fe; border-left: 4px solid var(--tz-accent);
            padding: .5rem 1rem; border-radius: 4px;
            font-weight: 700; font-size: .92rem; margin-bottom: 1rem;
        }

        /* ── Semáforos ── */
        .sem-verde    { color: #198754; }
        .sem-amarillo { color: #d97706; }
        .sem-rojo     { color: #dc3545; }
        .sem-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 4px; }
        .dot-verde    { background: #198754; }
        .dot-amarillo { background: #d97706; }
        .dot-rojo     { background: #dc3545; }
        .dot-na       { background: #adb5bd; }
    </style>
    @stack('styles')
</head>
<body>

<!-- ── Topbar independiente del trazador ── -->
<nav id="tz-topbar">
    <a class="tz-brand" href="{{ route('trazadores.index') }}">
        <img src="{{ asset('img/logo2.webp') }}" alt="CO">
        <span class="tz-sep">|</span>
        <span>UCI Trazadores</span>
    </a>

    <div class="tz-title">
        @yield('page-title', 'Trazador')
    </div>

    @hasSection('trazador-estado')
        @yield('trazador-estado')
    @endif

    @hasSection('trazador-acciones')
        @yield('trazador-acciones')
    @endif

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('trazadores.index') }}"
       class="tz-btn-volver">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</nav>

<!-- ── Contenido principal ── -->
<main id="tz-content">
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show tz-alert-success" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show tz-alert-error" role="alert">
        <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show tz-alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
@stack('scripts')
</body>
</html>
