<?php

namespace App\Http\Requests\Api;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Valida el lote de mediciones que envía un dispositivo ya autenticado por AuthenticateDevice.
 * Todo o nada: si una sola medición no es válida, no se guarda ninguna; cada error se reporta
 * con el índice de la medición a la que corresponde.
 */
class StoreReadingsRequest extends FormRequest
{
    /** @var Collection<string, Sensor> */
    private Collection $resolvedSensors;

    /** @var Collection<int, Batch> */
    private Collection $resolvedBatches;

    public function authorize(): bool
    {
        return $this->device() !== null;
    }

    /**
     * Acepta tanto {"readings": [...]} como una sola medición sin envolver, para un dispositivo
     * que solo reporta un valor a la vez.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('readings')) {
            $this->merge(['readings' => [$this->all()]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'min:1', 'max:50'],
            'readings.*.sensor_code' => ['required', 'string', 'max:30'],
            'readings.*.measured_at' => ['required', 'date'],
            'readings.*.value' => ['required', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->any()) {
                // Los campos básicos ya fallaron; no tiene sentido resolver sensores y lotes todavía.
                return;
            }

            $this->resolveAndValidateReadings($validator);
        });
    }

    /**
     * Resuelve sensor y lote activo de cada medición con dos consultas (no una por fila) y valida
     * lo que el rules() básico no puede expresar: pertenencia al dispositivo, lote activo y rango físico.
     */
    private function resolveAndValidateReadings(Validator $validator): void
    {
        $device = $this->device();
        $readings = $this->input('readings');
        $codes = collect($readings)->pluck('sensor_code')->unique()->all();

        $this->resolvedSensors = $device->sensors()
            ->whereIn('code', $codes)
            ->active()
            ->with('metric:id,code,name,unit,decimals,min_value,max_value')
            ->get()
            ->keyBy('code');

        $pondIds = $this->resolvedSensors->pluck('pond_id')->unique()->values()->all();

        $this->resolvedBatches = Batch::query()
            ->whereIn('pond_id', $pondIds)
            ->where('status', BatchStatus::Active->value)
            ->get()
            ->keyBy('pond_id');

        foreach ($readings as $index => $data) {
            $prefix = "readings.$index";
            $sensor = $this->resolvedSensors->get($data['sensor_code']);

            if (! $sensor) {
                $validator->errors()->add("$prefix.sensor_code", "El sensor '{$data['sensor_code']}' no existe, no pertenece a este dispositivo o está inactivo.");

                continue;
            }

            $batch = $this->resolvedBatches->get($sensor->pond_id);

            if (! $batch) {
                $validator->errors()->add("$prefix.sensor_code", "El estanque del sensor '{$sensor->code}' no tiene un lote activo; no se puede registrar la medición.");

                continue;
            }

            $measuredAt = Carbon::parse($data['measured_at']);

            if ($measuredAt->lt(Carbon::parse($batch->stocked_at))) {
                $validator->errors()->add("$prefix.measured_at", 'La fecha no puede ser anterior a la siembra del lote.');
            } elseif ($measuredAt->gt(now()->addMinutes(5))) {
                $validator->errors()->add("$prefix.measured_at", 'La fecha no puede ser posterior al momento actual.');
            }

            if ($error = $sensor->metric->physicalRangeError((float) $data['value'])) {
                $validator->errors()->add("$prefix.value", $error);
            }
        }
    }

    public function device(): ?Device
    {
        /** @var Device|null $device */
        $device = $this->attributes->get('device');

        return $device;
    }

    /**
     * Mediciones ya resueltas (sensor y lote activo incluidos), listas para crear.
     * Solo es válido llamarla después de pasar la validación.
     *
     * @return list<array{sensor: Sensor, batch: Batch, measured_at: Carbon, value: float}>
     */
    public function resolvedReadings(): array
    {
        return collect($this->validated('readings'))
            ->map(function (array $data) {
                $sensor = $this->resolvedSensors->get($data['sensor_code']);

                return [
                    'sensor' => $sensor,
                    'batch' => $this->resolvedBatches->get($sensor->pond_id),
                    'measured_at' => Carbon::parse($data['measured_at']),
                    'value' => (float) $data['value'],
                ];
            })
            ->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
