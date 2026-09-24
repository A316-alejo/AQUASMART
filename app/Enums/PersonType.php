<?php

namespace App\Enums;

enum PersonType: string
{
    case Natural = 'natural';
    case Juridical = 'juridica';

    public function label(): string
    {
        return match ($this) {
            self::Natural => 'Persona natural',
            self::Juridical => 'Persona jurídica',
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
