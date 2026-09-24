<?php

namespace App\Enums;

enum DocumentType: string
{
    case CitizenId = 'CC';
    case ForeignerId = 'CE';
    case TaxId = 'NIT';
    case Passport = 'PAS';
    case TemporaryPermit = 'PPT';

    public function label(): string
    {
        return match ($this) {
            self::CitizenId => 'Cédula de ciudadanía',
            self::ForeignerId => 'Cédula de extranjería',
            self::TaxId => 'NIT',
            self::Passport => 'Pasaporte',
            self::TemporaryPermit => 'Permiso por protección temporal',
        };
    }

    /**
     * Formato esperado del número, ya normalizado (sin puntos ni espacios, en mayúsculas).
     */
    public function pattern(): string
    {
        return match ($this) {
            self::CitizenId => '/^[0-9]{5,10}$/',
            self::TaxId => '/^[0-9]{6,12}(-[0-9])?$/',
            self::ForeignerId, self::Passport, self::TemporaryPermit => '/^[A-Z0-9]{5,20}$/',
        };
    }

    public function formatHint(): string
    {
        return match ($this) {
            self::CitizenId => 'entre 5 y 10 dígitos',
            self::TaxId => 'entre 6 y 12 dígitos, con dígito de verificación opcional (900123456-7)',
            self::ForeignerId, self::Passport, self::TemporaryPermit => 'entre 5 y 20 letras o números',
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
