<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Producer = 'productor';
    case Technician = 'tecnico';
    case Viewer = 'consulta';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Producer => 'Productor',
            self::Technician => 'Técnico',
            self::Viewer => 'Consulta',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Administra la plataforma completa, incluidos los usuarios.',
            self::Producer => 'Gestiona sus propias fincas, estanques y lotes.',
            self::Technician => 'Registra datos en las fincas que tiene asignadas.',
            self::Viewer => 'Solo puede consultar información.',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Admin => 4,
            self::Producer => 3,
            self::Technician => 2,
            self::Viewer => 1,
        };
    }

    /**
     * @return list<self>
     */
    public static function assignableBy(self $role): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case->level() <= $role->level()));
    }
}
