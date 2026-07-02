<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\Paciente;
use Illuminate\Http\Request;

class DispositivoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->input('filtro', 'activos');
        $tipo   = $request->input('tipo');

        $query = Dispositivo::with('paciente')
            ->when($filtro === 'activos', fn($q) => $q->where('activo', true))
            ->when($filtro === 'iaas',    fn($q) => $q->where('evento_iaas', true))
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->orderByDesc('fecha_inicio');

        $dispositivos = $query->paginate(30)->withQueryString();

        // KPIs
        $activos = Dispositivo::where('activo', true);
        $kpis = [
            'cvc'       => (clone $activos)->where('tipo', 'cvc')->count(),
            'vm'        => (clone $activos)->where('tipo', 'vm')->count(),
            'sv'        => (clone $activos)->where('tipo', 'sonda_vesical')->count(),
            'iaas_30d'  => Dispositivo::where('evento_iaas', true)
                            ->where('fecha_inicio', '>=', now()->subDays(30))->count(),
        ];

        // Días-dispositivo (denominadores para tasas IAAS)
        // Aritmética de fechas compatible con PostgreSQL y MySQL
        $diasCvc = $this->sumaDiasDispositivo('cvc');
        $diasVm  = $this->sumaDiasDispositivo('vm');
        $diasSv  = $this->sumaDiasDispositivo('sonda_vesical');

        // Tasas IAAS por 1000 días-dispositivo
        $tasas = [
            'clabsi' => $diasCvc > 0 ? round(
                Dispositivo::where('tipo', 'cvc')->where('tipo_iaas', 'CLABSI')->count() / $diasCvc * 1000, 2
            ) : 0,
            'cauti'  => $diasSv > 0 ? round(
                Dispositivo::where('tipo', 'sonda_vesical')->where('tipo_iaas', 'CAUTI')->count() / $diasSv * 1000, 2
            ) : 0,
            'vap'    => $diasVm > 0 ? round(
                Dispositivo::where('tipo', 'vm')->where('tipo_iaas', 'VAP')->count() / $diasVm * 1000, 2
            ) : 0,
        ];

        $pacientes = Paciente::where('activo', true)->orderBy('nombre')->get();

        return view('dispositivos.index', compact(
            'dispositivos', 'kpis', 'tasas', 'filtro', 'tipo', 'pacientes'
        ));
    }

    public function store(Request $request)
    {
        Dispositivo::create([
            'paciente_id'    => $request->paciente_id,
            'usuario_id'     => auth()->id(),
            'tipo'           => $request->tipo,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_retiro'   => $request->fecha_retiro ?: null,
            'sitio_insercion'=> $request->sitio_insercion,
            'via_acceso'     => $request->via_acceso,
            'activo'         => empty($request->fecha_retiro),
            'evento_iaas'    => false,
            'observaciones'  => $request->observaciones,
        ]);

        return redirect()->route('dispositivos.index')
            ->with('success', 'Dispositivo registrado correctamente.');
    }

    public function retirar(Dispositivo $dispositivo, Request $request)
    {
        $dispositivo->update([
            'fecha_retiro' => $request->input('fecha_retiro', today()->toDateString()),
            'activo'       => false,
        ]);
        return redirect()->route('dispositivos.index')
            ->with('success', 'Dispositivo retirado.');
    }

    public function registrarIaas(Dispositivo $dispositivo, Request $request)
    {
        $dispositivo->update([
            'evento_iaas'  => true,
            'tipo_iaas'    => $request->tipo_iaas,
            'organismo'    => $request->organismo,
            'sensibilidad' => $request->sensibilidad,
            'observaciones'=> $request->observaciones,
        ]);
        return redirect()->route('dispositivos.index')
            ->with('success', 'Evento IAAS registrado.');
    }

    // Suma de días-dispositivo compatible con PostgreSQL y MySQL
    private function sumaDiasDispositivo(string $tipo): int
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'pgsql') {
            $sql = "SUM(COALESCE(fecha_retiro, CURRENT_DATE) - fecha_inicio + 1)";
        } else {
            // MySQL / MariaDB
            $sql = "SUM(DATEDIFF(COALESCE(fecha_retiro, CURDATE()), fecha_inicio) + 1)";
        }

        return (int) (Dispositivo::where('tipo', $tipo)->selectRaw("$sql as dias")->value('dias') ?? 0);
    }
}
