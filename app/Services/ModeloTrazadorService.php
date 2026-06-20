<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ModeloTrazadorService
{
    private array $modelo;

    public function __construct()
    {
        // Carga el JSON una vez por request (o desde cache en producción)
        $ruta = base_path('modelo_trazador_sepsis.json');
        $this->modelo = json_decode(file_get_contents($ruta), true);
    }

    // ─── Acceso completo al modelo ────────────────────────────────────────────

    public function modelo(): array
    {
        return $this->modelo;
    }

    // ─── Catálogos ────────────────────────────────────────────────────────────

    public function catalogos(): array
    {
        return $this->modelo['catalogos'] ?? [];
    }

    public function catalogo(string $id): array
    {
        return $this->catalogos()[$id] ?? [];
    }

    /** Devuelve solo los valores string (para catalogos simples como SI_NO, SEXO, etc.) */
    public function valoresCatalogo(string $id): array
    {
        $cat = $this->catalogo($id);
        if (empty($cat)) return [];
        // Si es un catálogo con objetos {code, label} devuelve los codes como string
        if (isset($cat[0]['code'])) {
            return array_map(fn($item) => (string)$item['code'], $cat);
        }
        return $cat;
    }

    // ─── Secciones y campos ───────────────────────────────────────────────────

    public function secciones(): array
    {
        return $this->modelo['secciones'] ?? [];
    }

    public function seccion(string $id): ?array
    {
        foreach ($this->secciones() as $s) {
            if ($s['id'] === $id) return $s;
        }
        return null;
    }

    // ─── Indicadores ─────────────────────────────────────────────────────────

    public function indicadoresSepsis(): array
    {
        return $this->modelo['indicadores_sepsis'] ?? [];
    }

    public function indicadoresAbcdef(): array
    {
        $sec = $this->seccion('abcdef');
        return $sec['indicadores'] ?? [];
    }

    public function camposElementoAbcdef(): array
    {
        $sec = $this->seccion('abcdef');
        return $sec['campos_elemento'] ?? [];
    }

    // ─── Encuesta ─────────────────────────────────────────────────────────────

    public function preguntasEncuesta(): array
    {
        $sec = $this->seccion('encuesta');
        return $sec['preguntas'] ?? [];
    }

    public function datosEncuestado(): array
    {
        $sec = $this->seccion('encuesta');
        return $sec['datos_encuestado'] ?? [];
    }

    // ─── Escalas ─────────────────────────────────────────────────────────────

    public function escalasEncuesta(): array
    {
        return $this->modelo['escalas_encuesta'] ?? [];
    }

    // ─── Semáforo ─────────────────────────────────────────────────────────────

    public function semaforo(): array
    {
        return $this->modelo['semaforo'] ?? [];
    }

    // ─── Metas de manejo ─────────────────────────────────────────────────────

    public function metasManejo(): array
    {
        $sec = $this->seccion('metas_manejo');
        return $sec['metas'] ?? [];
    }

    // ─── Prellenado: mapeo Paciente → campos del trazador ────────────────────

    /**
     * Toma un modelo Paciente y devuelve el array 'datos' para prellenar.
     * Usa los mismos ids de campo del JSON; si el paciente no tiene el dato → null.
     */
    public function prellenarDesdePaciente(\App\Models\Paciente $paciente): array
    {
        return [
            'datos_paciente' => [
                'datos.identificador_iniciales_B5'             => $paciente->documento ?? $paciente->nombre ?? null,
                'datos.edad_anos_B6'                           => $paciente->edad ?? null,
                'datos.sexo_B7'                                => $paciente->sexo ?? null,
                'datos.fecha_y_hora_de_ingreso_a_uci_B17'     => $paciente->ingreso_uci?->format('Y-m-d\TH:i'),
                'datos.fecha_y_hora_de_egreso_de_uci_B18'     => $paciente->egreso_uci?->format('Y-m-d\TH:i'),
                'datos.desenlace_crear_lista_desplegable_muer_B28' => $this->mapearDesenlace($paciente->tipo_egreso ?? null),
            ],
            'fase1_activacion' => [],
            'fase2_bundle_1h'  => [],
            'fase3_reeval'     => [],
            'metas_manejo'     => [],
            'abcdef'           => [],
            'encuesta_antes'   => [],
            'encuesta_despues' => [],
        ];
    }

    private function mapearDesenlace(?string $tipoEgreso): ?string
    {
        return match($tipoEgreso) {
            'mejoria'    => 'Vivo - egreso UCI',
            'traslado'   => 'Vivo - traslado',
            'fallecimiento' => 'Fallecido',
            'alta_casa'  => 'Vivo - egreso UCI',
            default      => null,
        };
    }
}
