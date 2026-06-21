<?php

namespace App\Http\Controllers;

use App\Models\UnidadUci;
use Illuminate\Http\Request;

class UnidadUciController extends Controller
{
    public function index() { return view('unidades-uci.index', ['unidades' => UnidadUci::orderBy('cama_desde')->get()]); }

    public function update(Request $request, UnidadUci $unidad)
    {
        $data = $request->validate([
            'habilitada_desde' => ['nullable', 'date'],
            'inhabilitada_desde' => ['nullable', 'date', 'after_or_equal:habilitada_desde'],
        ]);
        $unidad->update($data);
        return back()->with('success', "Configuración de {$unidad->nombre} actualizada.");
    }
}
