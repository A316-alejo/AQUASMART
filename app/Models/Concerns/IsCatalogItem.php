<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comportamiento común de los catálogos: estado activo/inactivo, búsqueda y orden.
 * Cada modelo declara SEARCHABLE; la primera columna define el orden del listado.
 */
trait IsCatalogItem
{
    /**
     * Nombre con el que se muestra el registro en mensajes y confirmaciones.
     */
    abstract public function catalogLabel(): string;

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy(static::SEARCHABLE[0]);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            foreach (static::SEARCHABLE as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }
}
