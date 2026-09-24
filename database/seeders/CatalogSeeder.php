<?php

namespace Database\Seeders;

use App\Models\FeedType;
use App\Models\Metric;
use App\Models\MortalityCause;
use App\Models\Species;
use Illuminate\Database\Seeder;

/**
 * Datos iniciales de los catálogos, orientados a tilapia.
 * Solo crea lo que falta: volver a ejecutarlo no duplica registros ni sobrescribe cambios del administrador.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $species = [
            ['common_name' => 'Tilapia roja', 'scientific_name' => 'Oreochromis sp.'],
            ['common_name' => 'Tilapia nilótica', 'scientific_name' => 'Oreochromis niloticus'],
            ['common_name' => 'Cachama blanca', 'scientific_name' => 'Piaractus brachypomus'],
            ['common_name' => 'Cachama negra', 'scientific_name' => 'Colossoma macropomum'],
            ['common_name' => 'Trucha arcoíris', 'scientific_name' => 'Oncorhynchus mykiss'],
            ['common_name' => 'Bocachico', 'scientific_name' => 'Prochilodus magdalenae'],
        ];

        foreach ($species as $row) {
            Species::firstOrCreate(['common_name' => $row['common_name']], $row);
        }

        // Etapas típicas de alimento balanceado para tilapia (sin marca comercial).
        $feedTypes = [
            ['name' => 'Preiniciación', 'protein_pct' => 45, 'pellet_mm' => 0.8],
            ['name' => 'Iniciación', 'protein_pct' => 40, 'pellet_mm' => 1.5],
            ['name' => 'Levante', 'protein_pct' => 34, 'pellet_mm' => 3],
            ['name' => 'Engorde', 'protein_pct' => 30, 'pellet_mm' => 4.5],
            ['name' => 'Finalización', 'protein_pct' => 24, 'pellet_mm' => 6],
        ];

        foreach ($feedTypes as $row) {
            FeedType::firstOrCreate(['name' => $row['name'], 'brand' => null], $row);
        }

        $causes = [
            'Baja de oxígeno',
            'Enfermedad',
            'Parásitos',
            'Mala calidad del agua',
            'Estrés por manejo',
            'Transporte y siembra',
            'Depredación',
            'Desconocida',
        ];

        foreach ($causes as $name) {
            MortalityCause::firstOrCreate(['name' => $name]);
        }

        $metrics = [
            ['code' => 'temperature', 'name' => 'Temperatura', 'unit' => '°C', 'decimals' => 1, 'min_value' => 0, 'max_value' => 50],
            ['code' => 'ph', 'name' => 'pH', 'unit' => null, 'decimals' => 2, 'min_value' => 0, 'max_value' => 14],
            ['code' => 'dissolved_oxygen', 'name' => 'Oxígeno disuelto', 'unit' => 'mg/L', 'decimals' => 2, 'min_value' => 0, 'max_value' => 25],
            ['code' => 'ammonia', 'name' => 'Amonio total', 'unit' => 'mg/L', 'decimals' => 2, 'min_value' => 0, 'max_value' => 50],
            ['code' => 'nitrite', 'name' => 'Nitritos', 'unit' => 'mg/L', 'decimals' => 2, 'min_value' => 0, 'max_value' => 20],
            ['code' => 'water_level', 'name' => 'Nivel del agua', 'unit' => 'cm', 'decimals' => 0, 'min_value' => 0, 'max_value' => 1000],
        ];

        foreach ($metrics as $row) {
            Metric::firstOrCreate(['code' => $row['code']], $row);
        }
    }
}
