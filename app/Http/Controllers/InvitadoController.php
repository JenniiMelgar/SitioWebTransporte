<?php

namespace App\Http\Controllers;

use App\Models\Accidente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvitadoController extends Controller
{
    public function dashboard()
    {
        try {
            // Métricas básicas para invitados
            $metrics = DB::connection('oracle')
                ->table('HECHOS_T')
                ->selectRaw('
                    COUNT(*) as total_accidentes,
                    AVG(SEVERITY) as severidad_promedio
                ')
                ->first();

            return view('dashboard.base', [
                'dashboardTitle' => 'Vista de Invitado',
                'dashboardSubtitle' => 'Resumen básico de datos de accidentes viales',
                'roleBadgeColor' => 'secondary',
                'kpis' => [
                    [
                        'icon' => 'fas fa-car-crash',
                        'value' => number_format($metrics->total_accidentes ?? 0),
                        'label' => 'Total de Accidentes'
                    ],
                    [
                        'icon' => 'fas fa-exclamation-triangle',
                        'value' => round($metrics->severidad_promedio ?? 0, 1),
                        'label' => 'Severidad Promedio'
                    ],
                    [
                        'icon' => 'fas fa-database',
                        'value' => 'Oracle',
                        'label' => 'Base de Datos'
                    ],
                    [
                        'icon' => 'fas fa-calendar',
                        'value' => '2016-2023',
                        'label' => 'Período'
                    ]
                ],
                'quickActions' => [] // Invitados no tienen acciones rápidas
            ]);

        } catch (\Exception $e) {
            Log::error('Error en InvitadoController dashboard: ' . $e->getMessage());
            
            return view('dashboard.base', [
                'dashboardTitle' => 'Vista de Invitado',
                'dashboardSubtitle' => 'Resumen básico de datos de accidentes viales',
                'roleBadgeColor' => 'secondary',
                'kpis' => [
                    [
                        'icon' => 'fas fa-car-crash',
                        'value' => '0',
                        'label' => 'Total de Accidentes'
                    ],
                    [
                        'icon' => 'fas fa-exclamation-triangle',
                        'value' => '0.0',
                        'label' => 'Severidad Promedio'
                    ],
                    [
                        'icon' => 'fas fa-database',
                        'value' => 'Oracle',
                        'label' => 'Base de Datos'
                    ],
                    [
                        'icon' => 'fas fa-calendar',
                        'value' => '2016-2023',
                        'label' => 'Período'
                    ]
                ],
                'quickActions' => []
            ]);
        }
    }
}