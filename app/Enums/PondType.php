<?php

namespace App\Enums;

enum PondType: string
{
    case Earthen = 'tierra';
    case Geomembrane = 'geomembrana';
    case Concrete = 'concreto';
    case Tank = 'tanque';
    case Ras = 'ras';
    case Biofloc = 'biofloc';
    case Cage = 'jaula';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Earthen => 'Estanque en tierra',
            self::Geomembrane => 'Geomembrana',
            self::Concrete => 'Concreto',
            self::Tank => 'Tanque',
            self::Ras => 'Recirculación (RAS)',
            self::Biofloc => 'Biofloc',
            self::Cage => 'Jaula flotante',
            self::Other => 'Otro sistema',
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
