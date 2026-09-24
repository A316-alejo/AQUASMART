<?php

namespace App\Enums;

use App\Models\FeedType;
use App\Models\Metric;
use App\Models\MortalityCause;
use App\Models\Species;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogos administrables. El valor es el segmento de la URL (/catalogos/{catalog}).
 */
enum Catalog: string
{
    case Species = 'especies';
    case FeedTypes = 'alimentos';
    case MortalityCauses = 'causas-mortalidad';
    case Metrics = 'parametros';

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Species => Species::class,
            self::FeedTypes => FeedType::class,
            self::MortalityCauses => MortalityCause::class,
            self::Metrics => Metric::class,
        };
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * Nombre usado para las vistas parciales de tabla y formulario.
     */
    public function key(): string
    {
        return match ($this) {
            self::Species => 'species',
            self::FeedTypes => 'feed_types',
            self::MortalityCauses => 'mortality_causes',
            self::Metrics => 'metrics',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Species => 'Especies',
            self::FeedTypes => 'Tipos de alimento',
            self::MortalityCauses => 'Causas de mortalidad',
            self::Metrics => 'Parámetros del agua',
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::Species => 'especie',
            self::FeedTypes => 'tipo de alimento',
            self::MortalityCauses => 'causa de mortalidad',
            self::Metrics => 'parámetro',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Species => 'Especies que se cultivan en los lotes de producción.',
            self::FeedTypes => 'Alimentos balanceados usados en los registros de alimentación.',
            self::MortalityCauses => 'Causas con las que se clasifican los registros de mortalidad.',
            self::Metrics => 'Parámetros de calidad del agua que se miden de forma manual o con sensores.',
        };
    }

    public function isFeminine(): bool
    {
        return in_array($this, [self::Species, self::MortalityCauses], true);
    }

    /**
     * Elige la forma masculina o femenina según el género del catálogo ("la especie", "el parámetro").
     */
    public function gender(string $masculine, string $feminine): string
    {
        return $this->isFeminine() ? $feminine : $masculine;
    }
}
