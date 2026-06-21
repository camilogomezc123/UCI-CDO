<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'UCI Panel') — Clínica de Occidente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #2d2d2d;
            --sidebar-hover: #3d3d3d;
            --sidebar-active: #0d6efd;
            --sidebar-width: 250px;
            --topbar-height: 58px;
            --co-gris: #5a5a5a;
        }
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }

        /* Sidebar */
        #sidebar {
            position: fixed; top: 0; left: 0; height: 100vh;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            z-index: 1040; display: flex; flex-direction: column;
            transition: width 0.25s;
            overflow: hidden;
        }
        .sidebar-brand {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 0.75rem;
        }
        .sidebar-brand img { height: 38px; object-fit: contain; filter: brightness(0) invert(1); }
        .sidebar-brand span { color: #fff; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.5px; line-height: 1.3; }

        .sidebar-section {
            padding: 0.5rem 1rem 0.25rem;
            font-size: 0.65rem; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 1px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.65rem 1.25rem;
            color: rgba(255,255,255,0.75);
            text-decoration: none; font-size: 0.875rem;
            border-left: 3px solid transparent;
            transition: all 0.15s;
        }
        .sidebar-link:hover { background: var(--sidebar-hover); color: #fff; }
        .sidebar-link.active { background: rgba(13,110,253,0.15); color: #fff; border-left-color: var(--sidebar-active); }
        .sidebar-link i { font-size: 1rem; width: 20px; text-align: center; }
        .sidebar-badge {
            margin-left: auto;
            background: var(--sidebar-active);
            color: #fff; font-size: 0.65rem;
            padding: 2px 7px; border-radius: 10px;
        }

        /* Topbar */
        #topbar {
            position: fixed; top: 0;
            left: var(--sidebar-width); right: 0;
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e5e9f0;
            display: flex; align-items: center;
            padding: 0 1.5rem; gap: 1rem;
            z-index: 1030;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .topbar-title { font-weight: 700; font-size: 1rem; color: #2d2d2d; flex: 1; }

        /* Main content */
        #main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 1.5rem;
            min-height: calc(100vh - var(--topbar-height));
        }

        /* Cards */
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .card-header { background: transparent; border-bottom: 1px solid #f0f0f0; font-weight: 600; }

        /* KPI cards */
        .kpi-card { border-radius: 12px; padding: 1.25rem; color: #fff; position: relative; overflow: hidden; }
        .kpi-card .kpi-icon {
            position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
            font-size: 3rem; opacity: 0.2;
        }
        .kpi-card .kpi-number { font-size: 2rem; font-weight: 700; line-height: 1; }
        .kpi-card .kpi-label { font-size: 0.8rem; opacity: 0.85; margin-top: 0.25rem; }
        .kpi-card .kpi-sub { font-size: 0.75rem; opacity: 0.7; margin-top: 0.5rem; }

        /* Status badges */
        .badge-criterio { font-size: 0.7rem; padding: 0.3em 0.6em; border-radius: 6px; }
        .criterio-intensivo { background: #dc354520; color: #dc3545; }
        .criterio-intermedio { background: #fd7e1420; color: #e06000; }
        .criterio-otros { background: #6c757d20; color: #6c757d; }

        /* Tables */
        .table > thead > tr > th { background: #f8f9fa; font-size: 0.8rem; font-weight: 600; color: var(--co-gris); border-bottom: 2px solid #e5e9f0; }
        .table > tbody > tr > td { font-size: 0.875rem; vertical-align: middle; }
        .table-hover > tbody > tr:hover { background-color: #f0f4ff; }

        /* Time indicator */
        .tiempo-uci { font-weight: 600; color: #0d6efd; }
        .tiempo-espera { font-weight: 600; color: #dc3545; }

        /* Responsive */
        @media (max-width: 768px) {
            #sidebar { width: 0; }
            #topbar, #main-content { left: 0; margin-left: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('img/logo2-white.png') }}" alt="CO">
        <span>UCI Panel<br><small style="opacity:0.6;font-weight:400">Clínica de Occidente</small></span>
    </div>

    <div style="flex:1; overflow-y:auto; padding-top:0.5rem;">
        <div class="sidebar-section">Principal</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        @if(!auth()->user()->esVisual())
        <div class="sidebar-section">Pacientes</div>
        <a href="{{ route('pacientes.index') }}" class="sidebar-link {{ request()->routeIs('pacientes.index') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Pacientes activos
        </a>
        <a href="{{ route('estancias.index') }}" class="sidebar-link {{ request()->routeIs('estancias.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-x"></i> Estancias prolongadas
            @php $nEst = \App\Models\Paciente::where('activo',true)->whereNotNull('ingreso_uci')->where('ingreso_uci','<=',now()->subDays(5))->count(); @endphp
            @if($nEst > 0)<span class="sidebar-badge">{{ $nEst }}</span>@endif
        </a>
        <a href="{{ route('reingresos.index') }}" class="sidebar-link {{ request()->routeIs('reingresos.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-repeat"></i> Reingresos a UCI
            @php $nRei = \Illuminate\Support\Facades\Schema::hasColumn('pacientes','numero_ingresos') ? \App\Models\Paciente::where('activo',true)->where('numero_ingresos','>',1)->count() : 0; @endphp
            @if($nRei > 0)<span class="sidebar-badge" style="background:#dc3545;">{{ $nRei }}</span>@endif
        </a>

        <a href="{{ route('trazadores.index') }}" class="sidebar-link {{ request()->routeIs('trazadores.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-pulse"></i> Pacientes Trazadores
            @php $nTrazActivos = \App\Models\Trazador::activos()->count(); @endphp
            @if($nTrazActivos > 0)<span class="sidebar-badge">{{ $nTrazActivos }}</span>@endif
        </a>

        <div class="sidebar-section">Gestión</div>
        <a href="{{ route('carga.index') }}" class="sidebar-link {{ request()->routeIs('carga.index') ? 'active' : '' }}">
            <i class="bi bi-cloud-upload"></i> Cargar archivo
        </a>
        <a href="{{ route('carga.historial') }}" class="sidebar-link {{ request()->routeIs('carga.historial') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i> Historial cargas
        </a>

        <div class="sidebar-section">Análisis</div>
        <a href="{{ route('epidemiologia.index') }}" class="sidebar-link {{ request()->routeIs('epidemiologia.*') ? 'active' : '' }}">
            <i class="bi bi-heart-pulse"></i> Perfil epidemiológico
        </a>
        <a href="{{ route('indicadores.calidad') }}" class="sidebar-link {{ request()->routeIs('indicadores.*') ? 'active' : '' }}">
            <i class="bi bi-award"></i> Indicadores de calidad
        </a>
        <a href="{{ route('reportes.index') }}" class="sidebar-link {{ request()->routeIs('reportes.index') ? 'active' : '' }}">
            <i class="bi bi-bar-chart"></i> Reportes por subunidad
        </a>
        <a href="{{ route('reportes.periodicos') }}" class="sidebar-link {{ request()->routeIs('reportes.periodicos*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Reportes periódicos
        </a>
        <a href="{{ route('reportes.mortalidad') }}" class="sidebar-link {{ request()->routeIs('reportes.mortalidad') ? 'active' : '' }}">
            <i class="bi bi-person-x-fill"></i> Informe de mortalidad
        </a>
        <a href="{{ route('reportes.descargas') }}" class="sidebar-link {{ request()->routeIs('reportes.descargas*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-arrow-down"></i> Descargas Excel
        </a>
        <a href="{{ route('plantilla-diaria') }}" class="sidebar-link {{ request()->routeIs('plantilla-diaria') ? 'active' : '' }}" target="_blank">
            <i class="bi bi-clipboard2-pulse"></i> Registro diario
        </a>

        @if(auth()->user()->esMaster())
        <div class="sidebar-section">Administración</div>
        <a href="{{ route('usuarios.index') }}" class="sidebar-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
            <i class="bi bi-person-gear"></i> Usuarios
        </a>
        @endif
        @endif {{-- fin @if rol !== visual --}}
    </div>

    <div style="padding:1rem; border-top:1px solid rgba(255,255,255,0.08);">
        <div style="color:rgba(255,255,255,0.5); font-size:0.75rem; margin-bottom:0.5rem;">
            <i class="bi bi-person-circle me-1"></i>
            {{ auth()->user()->name }}<br>
            @php
                $badgeColor = auth()->user()->esVisual() ? '#0dcaf0' : (auth()->user()->esMaster() ? '#0d6efd' : '#6c757d');
            @endphp
            <span class="badge ms-0 mt-1" style="background:{{ $badgeColor }}; font-size:0.65rem; color:{{ auth()->user()->esVisual() ? '#000' : '#fff' }};">
                {{ auth()->user()->esVisual() ? 'VISUAL' : strtoupper(auth()->user()->rol) }}
            </span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="background:none;border:none;color:rgba(255,255,255,0.5);font-size:0.8rem;padding:0;cursor:pointer;">
                <i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión
            </button>
        </form>
    </div>
</nav>

<!-- Topbar -->
<div id="topbar">
    <div class="topbar-title">@yield('page-title', 'Dashboard')</div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted" style="font-size:0.8rem;">
            <i class="bi bi-calendar3 me-1"></i>{{ now()->format('d/m/Y') }}
        </span>
        @php
            $pacientesEsperandoEgreso = \App\Models\Paciente::whereNotNull('salida_hospitalizacion')
                ->whereNull('egreso_uci')->where('activo', true)->count();
        @endphp
        @if($pacientesEsperandoEgreso > 0)
        <a href="{{ route('pacientes.index', ['filtro' => 'pendiente_egreso']) }}" class="btn btn-sm btn-warning d-flex align-items-center gap-1">
            <i class="bi bi-exclamation-triangle-fill"></i>
            {{ $pacientesEsperandoEgreso }} pendiente{{ $pacientesEsperandoEgreso > 1 ? 's' : '' }} egreso
        </a>
        @endif
    </div>
</div>

<!-- Main -->
<main id="main-content">
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
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
