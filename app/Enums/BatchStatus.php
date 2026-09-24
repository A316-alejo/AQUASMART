<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Active = 'activo';
    case Closed = 'cerrado';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Closed => 'Cerrado',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Clase de la etiqueta (.tag) con la que se muestra el estado.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Closed => 'mute',
            self::Cancelled => 'crit',
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
