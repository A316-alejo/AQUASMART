<?php

namespace App\Enums;

/**
 * Clasificación de una lectura de calidad del agua frente a los umbrales de alerta configurados.
 * No confundir con el rango físico válido de App\Models\Metric, que decide si la lectura es posible.
 */
enum WaterQualityStatus: string
{
    case Normal = 'normal';
    case Warning = 'advertencia';
    case Critical = 'critico';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Warning => 'Advertencia',
            self::Critical => 'Crítico',
        };
    }

    /**
     * Clase de la etiqueta (.tag) con la que se muestra el estado.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'ok',
            self::Warning => 'warn',
            self::Critical => 'crit',
        };
    }
}
