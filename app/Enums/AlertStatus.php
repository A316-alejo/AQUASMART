<?php

namespace App\Enums;

/**
 * Estado de gestión de una alerta. Independiente de su severidad (App\Enums\WaterQualityStatus),
 * que se conserva tal como se calculó al generarla.
 */
enum AlertStatus: string
{
    case Pending = 'pendiente';
    case Acknowledged = 'reconocida';
    case Resolved = 'resuelta';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Acknowledged => 'Reconocida',
            self::Resolved => 'Resuelta',
        };
    }

    /**
     * Clase de la etiqueta (.tag) con la que se muestra el estado.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Acknowledged => 'info',
            self::Resolved => 'mute',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
