<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación JSON de un estanque para la API (/api/v1/ponds).
 *
 * No duplica reglas de negocio: solo da forma a los atributos y casts que ya tiene el modelo
 * Pond. Las relaciones se exponen con whenLoaded() porque Model::shouldBeStrict() está activo
 * fuera de producción y el acceso a una relación no cargada lanzaría una excepción; así el
 * Resource se adapta a lo que el controlador haya decidido cargar con with(...), sin forzar
 * una consulta adicional.
 *
 * @mixin \App\Models\Pond
 */
class PondResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'surface_m2' => $this->surface_m2,
            'volume_m3' => $this->volume_m3,
            'depth_m' => $this->depth_m,
            'is_active' => $this->is_active,
            'farm' => $this->whenLoaded('farm', fn () => [
                'id' => $this->farm->id,
                'name' => $this->farm->name,
                'is_active' => $this->farm->is_active,
                // whenLoaded() es un método del Resource, no del modelo: Farm no lo tiene,
                // por eso la comprobación de "producer" se hace con relationLoaded() + when().
                'producer' => $this->when($this->farm->relationLoaded('producer'), fn () => [
                    'id' => $this->farm->producer->id,
                    'name' => $this->farm->producer->name,
                ]),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
