<?php

namespace App\Http\Requests\Production;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Base de los registros productivos de un lote (alimentación, mortalidad, biometría).
 *
 * Los tres formularios conviven en el detalle del lote, por eso cada uno usa campos con prefijo
 * propio (feeding_, mortality_, sampling_) y los errores u old() de uno no aparecen en los otros.
 */
abstract class ProductionRecordRequest extends FormRequest
{
    /**
     * Prefijo de los campos y ancla de la sección en el detalle del lote.
     */
    abstract protected function prefix(): string;

    /**
     * Datos validados con los nombres de columna del modelo.
     *
     * @return array<string, mixed>
     */
    abstract public function record(): array;

    public function authorize(): bool
    {
        return $this->user()->can('record', $this->batch());
    }

    public function batch(): Batch
    {
        return $this->route('batch');
    }

    /**
     * Las fechas van entre la siembra y hoy.
     *
     * @return array<int, string>
     */
    protected function dateRules(): array
    {
        return [
            'required', 'date_format:Y-m-d',
            'after_or_equal:'.$this->batch()->stocked_at->toDateString(),
            'before_or_equal:today',
        ];
    }

    /**
     * Fecha y hora entre la siembra (00:00) y el momento actual. Usada por registros con
     * precisión horaria (calidad del agua), a diferencia de dateRules() que solo exige un día.
     *
     * @return array<int, string>
     */
    protected function dateTimeRules(): array
    {
        return [
            'required', 'date_format:Y-m-d\TH:i',
            'after_or_equal:'.$this->batch()->stocked_at->toDateString(),
            'before_or_equal:now',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $date = $this->prefix().'_date';

        return [
            $date.'.date_format' => 'La fecha no es válida.',
            $date.'.after_or_equal' => 'La fecha no puede ser anterior a la siembra del lote ('.$this->batch()->stocked_at->format('d/m/Y').').',
            $date.'.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
        ];
    }

    /**
     * Acepta coma decimal en los campos indicados y recorta las observaciones.
     *
     * @param  list<string>  $decimalFields
     */
    protected function normalize(array $decimalFields): void
    {
        $normalized = [];

        foreach ($decimalFields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = str_replace(',', '.', trim($this->input($field)));
            }
        }

        $notes = $this->prefix().'_notes';

        if (is_string($this->input($notes))) {
            $normalized[$notes] = trim($this->input($notes));
        }

        $this->merge($normalized);
    }

    protected function getRedirectUrl(): string
    {
        return route('batches.show', $this->batch()).'#'.Str::slug($this->prefix());
    }
}
