<?php

namespace App\Enums;

enum DeviceType: string
{
    case Esp32 = 'esp32';
    case Gateway = 'gateway';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Esp32 => 'ESP32',
            self::Gateway => 'Gateway',
            self::Other => 'Otro',
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
