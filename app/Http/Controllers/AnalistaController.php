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
            // Obtener métricas para analistas
            $metrics = DB::connection('oracle')
                ->table('HECHOS_T')
                ->selectRaw('
                    COUNT(*) as total_accidentes,
                    AVG(SEVERITY) as severidad_promedio,
                    SUM(CASE WHEN dc.WEATHER_CONDITION LIKE \'%Rain%\' OR dc.WEATHER_CONDITION LIKE \'%Snow%\' OR dc.WEATHER_CONDITION LIKE \'%Fog%\' THEN 1 ELSE 0 END) as accidentes_clima,
                    SUM(CASE WHEN dt.HORA_INICIO_KEY >= 18 OR dt.HORA_INICIO_KEY < 6 THEN 1 ELSE 0 END) as accidentes_nocturnos
                ')
                ->leftJoin('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
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
                        'dataKpi' => 'total-accidentes', // NUEVO
                        'trend' => 'Live'
                    ],
                    [
                        'icon' => 'fas fa-exclamation-triangle',
                        'value' => round($metrics->severidad_promedio ?? 0, 1),
                        'label' => 'Severidad Promedio',
                        'dataKpi' => 'severidad-promedio' // NUEVO
                    ],
                    [
                        'icon' => 'fas fa-cloud-rain',
                        'value' => number_format($metrics->accidentes_clima ?? 0),
                        'label' => 'Con Mal Clima',
                        'dataKpi' => 'con-mal-clima' // NUEVO
                    ],
                    [
                        'icon' => 'fas fa-moon',
                        'value' => number_format($metrics->accidentes_nocturnos ?? 0),
                        'label' => 'Accidentes Nocturnos',
                        'dataKpi' => 'accidentes-nocturnos' // NUEVO
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
                        'icon' => 'fas fa-chart-pie',
                        'title' => 'Generar Reportes',
                        'description' => 'Crear reportes personalizados',
                        'url' => '/reportes'
                    ],
                    [
                        'icon' => 'fas fa-file-export',
                        'title' => 'Exportar Datos',
                        'description' => 'Descargar datos en varios formatos',
                        'url' => '/exportar'
                    ],
                    [
                        'icon' => 'fas fa-chart-bar',
                        'title' => 'Análisis Avanzado',
                        'description' => 'Herramientas de análisis detallado',
                        'url' => '/analisis'
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