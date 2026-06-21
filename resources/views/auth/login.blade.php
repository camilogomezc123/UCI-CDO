<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCI Panel — Clínica de Occidente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --co-gris: #5a5a5a;
            --co-gris-dark: #2d2d2d;
            --co-azul: #0d6efd;
        }
        body {
            background: linear-gradient(135deg, #2d2d2d 0%, #4a4a4a 50%, #1a4a7a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            background: var(--co-gris-dark);
            padding: 2rem;
            text-align: center;
        }
        .login-header img { height: 60px; object-fit: contain; }
        .login-header h5 {
            color: #fff;
            margin-top: 1rem;
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.8;
        }
        .login-body { padding: 2rem 2.5rem; }
        .btn-login {
            background: var(--co-gris-dark);
            border: none;
            color: #fff;
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: background 0.2s;
        }
        .btn-login:hover { background: var(--co-azul); color: #fff; }
        .form-control:focus {
            border-color: var(--co-gris);
            box-shadow: 0 0 0 0.2rem rgba(90,90,90,0.2);
        }
        .footer-text {
            text-align: center;
            font-size: 0.75rem;
            color: #999;
            padding: 1rem;
            border-top: 1px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="{{ asset('img/logo1-white.webp') }}" alt="Clínica de Occidente">
            <h5>Panel UCI — Control de Pacientes</h5>
        </div>
        <div class="login-body">
            <h4 class="mb-1 fw-bold" style="color:#2d2d2d">Iniciar Sesión</h4>
            <p class="text-muted mb-4" style="font-size:0.85rem">Ingrese sus credenciales para continuar</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2" style="font-size:0.875rem">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.875rem">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-envelope text-muted"></i>
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control border-start-0 @error('email') is-invalid @enderror"
                               placeholder="correo@clinica.com" autofocus required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.875rem">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password" name="password"
                               class="form-control border-start-0"
                               placeholder="••••••••" required>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label text-muted" for="remember" style="font-size:0.85rem">
                            Mantener sesión activa
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                </button>
            </form>
        </div>
        <div class="footer-text">
            Clínica de Occidente &copy; {{ date('Y') }} — Uso interno UCI
        </div>
    </div>
</body>
</html>
