<?php

namespace App\Contracts;

interface IndicadoresTrazadorInterface
{
    /**
     * Recibe el array de datos del trazador (todas las secciones)
     * y devuelve el array de resultados calculados.
     */
    public function calcular(array $datos): array;
}
