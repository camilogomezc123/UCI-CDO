<?php

namespace App\Http\Controllers;

use App\Models\UnidadUci;
use App\Models\IndisponibilidadUci;
use Illuminate\Http\Request;

class UnidadUciController extends Controller
{
    public function index()
    {
        return view('unidades-uci.index', ['unidades' => UnidadUci::with('indisponibilidades')->orderBy('cama_desde')->get()]);
    }

    public function inhabilitar(Request $request, UnidadUci $unidad)
    {
        $data = $request->validate([
            'numero_cama' => ['nullable', 'integer', 'between:' . $unidad->cama_desde . ',' . $unidad->cama_hasta],
            'inhabilitada_desde' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:500'],
        ]);
        $existe = $unidad->indisponibilidades()->where('numero_cama', $data['numero_cama'] ?? null)->whereNull('habilitada_desde')->exists();
        if ($existe) return back()->with('warning', 'Esa unidad o cama ya está inhabilitada.');
        $unidad->indisponibilidades()->create($data + ['usuario_id' => auth()->id()]);
        return back()->with('success', ($data['numero_cama'] ? "Cama U{$data['numero_cama']}" : $unidad->nombre) . ' inhabilitada.');
    }

    public function habilitar(IndisponibilidadUci $indisponibilidad)
    {
        $indisponibilidad->update(['habilitada_desde' => today()]);
        return back()->with('success', 'Disponibilidad restaurada desde hoy.');
    }
}
