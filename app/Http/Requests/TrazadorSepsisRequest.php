<?php

namespace App\Http\Requests;

use App\Services\ModeloTrazadorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrazadorSepsisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ModeloTrazadorService $modelo */
        $modelo = app(ModeloTrazadorService::class);
        $cats   = $modelo->catalogos();

        // Helper: valores aceptados para un catálogo (string simple u objeto {code})
        $opciones = function (string $catId) use ($cats): array {
            $cat = $cats[$catId] ?? [];
            if (empty($cat)) return [];
            if (isset($cat[0]['code'])) {
                return array_map(fn($i) => (string)$i['code'], $cat);
            }
            return $cat;
        };

        // Validación suave: todos los campos son nullable.
        // Los selects aceptan exactamente las opciones del catálogo o null/vacío.
        return [
            'datos'                    => ['nullable', 'array'],

            // ── Datos del paciente ──────────────────────────────────────────
            'datos.datos_paciente.datos.sexo_B7'
                => ['nullable', Rule::in($opciones('SEXO'))],
            'datos.datos_paciente.datos.servicio_de_ingreso_B8'
                => ['nullable', Rule::in($opciones('SERVICIO'))],
            'datos.datos_paciente.datos.requirio_ventilacion_mecanica_al_ingre_B22'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.datos_paciente.datos.reintubacion_48_h_B26'
                => ['nullable', Rule::in($opciones('SI_NO_NA'))],
            'datos.datos_paciente.datos.reingreso_a_uci_72_h_B27'
                => ['nullable', Rule::in($opciones('SI_NO_NA'))],
            'datos.datos_paciente.datos.desenlace_crear_lista_desplegable_muer_B28'
                => ['nullable', Rule::in($opciones('DESENLACE'))],

            // ── Fase II ─────────────────────────────────────────────────────
            'datos.fase2_bundle_1h.fase2_bundle_1h.hemocultivos_tomados_B17'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.hemocultivos_solicitados_B18'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.liquidos_endovenosos_indicados_B21'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.evaluacion_de_respuesta_a_volumen_B23'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.metodo_de_evaluacion_de_volumen_B24'
                => ['nullable', Rule::in($opciones('METODO_VOLUMEN'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.formulacion_vasopresor_pam_65_pad_50_B25'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.farmaco_vasopresor_1_B27'
                => ['nullable', Rule::in($opciones('VASOPRESOR'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.farmaco_vasopresor_2_B29'
                => ['nullable', Rule::in($opciones('VASOPRESOR'))],
            'datos.fase2_bundle_1h.fase2_bundle_1h.uso_de_azul_de_metileno_B31'
                => ['nullable', Rule::in($opciones('SI_NO'))],

            // ── Fase III ────────────────────────────────────────────────────
            'datos.fase3_reeval.fase3_reeval.fenotipo_hemodinamico_cardiogenico_o_v_B37'
                => ['nullable', Rule::in($opciones('FENOTIPO'))],
            'datos.fase3_reeval.fase3_reeval.calculo_de_vti_B39'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase3_reeval.fase3_reeval.calculo_vexus_B41'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase3_reeval.fase3_reeval.ajuste_antimicrobiano_infectologia_B43'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase3_reeval.fase3_reeval.control_del_foco_identificado_B44'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.fase3_reeval.fase3_reeval.control_del_foco_realizado_menor_a_6_h_B45'
                => ['nullable', Rule::in($opciones('SI_NO_NA'))],

            // ── Metas de manejo (8 metas SI_NO_NE) ─────────────────────────
            'datos.metas_manejo.*'
                => ['nullable', Rule::in($opciones('SI_NO_NE'))],

            // ── ABCDEF cumplimiento_elemento ────────────────────────────────
            'datos.abcdef.cumplimiento.*'
                => ['nullable', Rule::in($opciones('CUMPLIMIENTO_ELEMENTO'))],
            'datos.abcdef.delirium_presencia'
                => ['nullable', Rule::in($opciones('SI_NO'))],
            'datos.abcdef.delirium_subtipo'
                => ['nullable', Rule::in($opciones('SUBTIPO_DELIRIUM'))],

            // ── Encuesta (numeradores son integers o nulls) ─────────────────
            'datos.encuesta_antes.preguntas.*'  => ['nullable'],
            'datos.encuesta_despues.preguntas.*' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.in' => 'El valor seleccionado no es válido para este campo.',
        ];
    }
}
