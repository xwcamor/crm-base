<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoExtraPipelinesSeeder extends Seeder
{
    public function run(): void
    {
        $blueprints = [
            [
                'name'        => 'Renovaciones',
                'description' => 'Pipeline para gestionar renovaciones de clientes recurrentes',
                'color'       => '#13c2c2',
                'stages'      => [
                    ['name' => 'Aviso 60 dias', 'color' => '#bae7ff', 'probability_pct' => 10],
                    ['name' => 'Aviso 30 dias', 'color' => '#91d5ff', 'probability_pct' => 25],
                    ['name' => 'Conversacion',  'color' => '#69c0ff', 'probability_pct' => 50],
                    ['name' => 'Negociacion',   'color' => '#40a9ff', 'probability_pct' => 75],
                    ['name' => 'Renovado',      'color' => '#52c41a', 'probability_pct' => 100, 'is_won' => true],
                    ['name' => 'Caido',         'color' => '#ff4d4f', 'probability_pct' => 0,   'is_lost' => true],
                ],
            ],
            [
                'name'        => 'Soporte premium',
                'description' => 'Pipeline para tickets de clientes con contrato de soporte',
                'color'       => '#722ed1',
                'stages'      => [
                    ['name' => 'Ticket abierto', 'color' => '#d3adf7', 'probability_pct' => 5],
                    ['name' => 'Diagnostico',    'color' => '#b37feb', 'probability_pct' => 25],
                    ['name' => 'Cotizacion',     'color' => '#9254de', 'probability_pct' => 50],
                    ['name' => 'Aprobacion',     'color' => '#722ed1', 'probability_pct' => 75],
                    ['name' => 'Implementacion', 'color' => '#531dab', 'probability_pct' => 90],
                    ['name' => 'Cerrado',        'color' => '#52c41a', 'probability_pct' => 100, 'is_won' => true],
                    ['name' => 'Descartado',     'color' => '#ff4d4f', 'probability_pct' => 0,   'is_lost' => true],
                ],
            ],
            [
                'name'        => 'Onboarding cliente',
                'description' => 'Pipeline para la puesta en marcha de un cliente nuevo',
                'color'       => '#fa8c16',
                'stages'      => [
                    ['name' => 'Firma',         'color' => '#ffd591', 'probability_pct' => 10],
                    ['name' => 'Kickoff',       'color' => '#ffc069', 'probability_pct' => 25],
                    ['name' => 'Implementacion','color' => '#ffa940', 'probability_pct' => 50],
                    ['name' => 'Capacitacion',  'color' => '#fa8c16', 'probability_pct' => 75],
                    ['name' => 'Go-live',       'color' => '#52c41a', 'probability_pct' => 100, 'is_won' => true],
                    ['name' => 'Cancelado',     'color' => '#ff4d4f', 'probability_pct' => 0,   'is_lost' => true],
                ],
            ],
        ];

        Tenant::query()->get()->each(function (Tenant $tenant) use ($blueprints) {
            foreach ($blueprints as $bp) {
                $pipeline = Pipeline::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $bp['name']],
                    [
                        'description' => $bp['description'],
                        'color'       => $bp['color'],
                        'is_default'  => false,
                        'is_active'   => true,
                        'sort_order'  => 0,
                    ],
                );

                foreach ($bp['stages'] as $i => $s) {
                    PipelineStage::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'name' => $s['name']],
                        [
                            'color'           => $s['color'],
                            'sort_order'      => $i + 1,
                            'probability_pct' => $s['probability_pct'],
                            'is_won'          => $s['is_won'] ?? false,
                            'is_lost'         => $s['is_lost'] ?? false,
                            'is_active'       => true,
                        ],
                    );
                }
            }
        });
    }
}
