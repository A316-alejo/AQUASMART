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
}
