<?php

namespace App\Http\Controllers;

use App\Models\Accidente;
use App\Models\Localizacion;
use App\Models\Clima;
use App\Models\Tiempo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalistaController extends Controller
{
    public function dashboard()
    {
        try {
        $metrics = DB::connection('oracle')
            ->table('HECHOS_T')
            ->selectRaw('
                COUNT(*) as total_accidentes,
                AVG(SEVERITY) as severidad_promedio,
                SUM(CASE WHEN dt.HORA_INICIO_KEY BETWEEN 18 AND 23 OR dt.HORA_INICIO_KEY BETWEEN 0 AND 5 THEN 1 ELSE 0 END) as accidentes_nocturnos,
                SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves
            ')
            ->leftJoin('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
            ->first();

            $topEstados = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->select(
                    'dl.STATE as estado', 
                    DB::raw('COUNT(*) as total'),
                    DB::raw('AVG(HECHOS_T.SEVERITY) as severidad_promedio') 
                )
                ->whereNotNull('dl.STATE')
                ->groupBy('dl.STATE')
                ->orderBy('total', 'DESC')
                ->limit(5)
                ->get();

             return view('dashboard.base', [
            'dashboardTitle' => 'Dashboard de Analista',
            'dashboardSubtitle' => 'Análisis avanzado de datos de accidentes viales',
            'roleBadgeColor' => 'success',
            'kpis' => [
                [
                    'icon' => 'fas fa-car-crash',
                    'value' => number_format($metrics->total_accidentes ?? 0),
                    'label' => 'Total de Accidentes',
                    'dataKpi' => 'total-accidentes',
                    'trend' => 'Live'
                ],
                [
                    'icon' => 'fas fa-exclamation-triangle',
                    'value' => round($metrics->severidad_promedio ?? 0, 1),
                    'label' => 'Severidad Promedio',
                    'dataKpi' => 'severidad-promedio'
                ],
                [
                    'icon' => 'fas fa-moon',
                    'value' => number_format($metrics->accidentes_nocturnos ?? 0),
                    'label' => 'Accidentes Nocturnos',
                    'dataKpi' => 'accidentes-nocturnos'
                ],
                [
                        'icon' => 'fas fa-calendar',
                        'value' => '2016-2023',
                        'label' => 'Período'
                    ]
            ],
                'quickActions' => [
                    [
                        'icon' => 'fas fa-database',
                        'title' => 'Procesar ETL',
                        'description' => 'Ejecutar transformación de datos',
                        'url' => '/etl'
                    ],
                    [
                        'icon' => 'fas fa-balance-scale me-1',
                        'title' => 'Comparadores',
                        'description' => 'Compara datos',
                        'url' => '/comparadores'
                    ]
                ],
                'topEstados' => $topEstados,
                'analistaMetrics' => [
                    'totalAccidentes' => $metrics->total_accidentes ?? 0,
                    'severidadPromedio' => $metrics->severidad_promedio ?? 0,
                    'accidentesClima' => $metrics->accidentes_clima ?? 0,
                    'accidentesNocturnos' => $metrics->accidentes_nocturnos ?? 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AnalistaController dashboard: ' . $e->getMessage());
            
            return view('dashboard.base', [
                'dashboardTitle' => 'Dashboard de Analista',
                'dashboardSubtitle' => 'Análisis avanzado de datos de accidentes viales',
                'roleBadgeColor' => 'success',
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
                        'icon' => 'fas fa-cloud-rain',
                        'value' => '0',
                        'label' => 'Con Mal Clima'
                    ],
                    [
                        'icon' => 'fas fa-moon',
                        'value' => '0',
                        'label' => 'Accidentes Nocturnos'
                    ]
                ],
                'quickActions' => [],
                'topEstados' => collect([]),
                'analistaMetrics' => [
                    'totalAccidentes' => 0,
                    'severidadPromedio' => 0,
                    'accidentesClima' => 0,
                    'accidentesNocturnos' => 0
                ]
            ]);
        }
    }
}