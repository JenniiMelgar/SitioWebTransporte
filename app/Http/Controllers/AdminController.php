<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function dashboard()
    {
        try {
            $metrics = DB::connection('oracle')
                ->select("
                    SELECT 
                        (SELECT COUNT(*) FROM HECHOS_T) as total_accidentes,
                        (SELECT AVG(SEVERITY) FROM HECHOS_T) as severidad_promedio,
                        (SELECT COUNT(*) FROM USERS) as total_usuarios,
                        (SELECT COUNT(*) FROM REPROCESA_DIMENSIONES_QA WHERE FECHA >= SYSDATE - 7) as procesos_recientes
                    FROM dual
                ");

            $metric = $metrics[0] ?? null;

            $recentLogs = DB::connection('oracle')
                ->table('REPROCESA_DIMENSIONES_QA')
                ->orderBy('FECHA', 'desc')
                ->limit(5)
                ->get();

            return view('dashboard.base', [
                'dashboardTitle' => 'Panel de Administración',
                'dashboardSubtitle' => 'Gestión completa del sistema y análisis de datos',
                'roleBadgeColor' => 'warning',
                'kpis' => [
                    [
                        'icon' => 'fas fa-car-crash',
                        'value' => number_format($metric->total_accidentes ?? 0),
                        'label' => 'Total Accidentes',
                        'textClass' => 'text-white',
                        'trend' => 'Live'
                    ],
                    [
                        'icon' => 'fas fa-exclamation-triangle',
                        'value' => round($metric->severidad_promedio ?? 0, 1),
                        'label' => 'Severidad Promedio',
                        'textClass' => 'text-white'
                    ],
                    [
                        'icon' => 'fas fa-users',
                        'value' => number_format($metric->total_usuarios ?? 0),
                        'label' => 'Usuarios Registrados',
                        'textClass' => 'text-white'
                    ],
                    [
                        'icon' => 'fas fa-calendar',
                        'value' => '2016-2023',
                        'label' => 'Período'
                    ]
                ],
                'quickActions' => [
                    [
                        'icon' => 'fas fa-users-cog',
                        'title' => 'Usuarios',
                        'description' => 'Usuarios del sistema',
                        'url' => '/admin/users'
                    ],
                    [
                        'icon' => 'fas fa-database',
                        'title' => 'Procesos ETL',
                        'description' => 'Ejecutar transformación de datos',
                        'url' => '/etl'
                    ],
                    [
                        'icon' => 'fas fa-file-csv',
                        'title' => 'Carga de Archivos',
                        'description' => 'Importar archivos CSV/Excel',
                        'url' => '/carga'
                    ],
                    [
                        'icon' => 'fas fa-balance-scale me-1',
                        'title' => 'Comparadores',
                        'description' => 'Compara datos',
                        'url' => '/comparadores'
                    ]
                ],
                'recentLogs' => $recentLogs
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AdminController dashboard: ' . $e->getMessage());
            
            return view('dashboard.base', [
                'dashboardTitle' => 'Panel de Administración',
                'dashboardSubtitle' => 'Gestión completa del sistema y análisis de datos',
                'roleBadgeColor' => 'warning',
                'kpis' => [
                    [
                        'icon' => 'fas fa-users',
                        'value' => '0',
                        'label' => 'Usuarios Registrados',
                        'bgClass' => 'bg-primary',
                        'textClass' => 'text-white'
                    ],
                    [
                        'icon' => 'fas fa-car-crash',
                        'value' => '0',
                        'label' => 'Total Accidentes',
                        'bgClass' => 'bg-success',
                        'textClass' => 'text-white'
                    ],
                    [
                        'icon' => 'fas fa-exclamation-triangle',
                        'value' => '0.0',
                        'label' => 'Severidad Promedio',
                        'bgClass' => 'bg-info',
                        'textClass' => 'text-white'
                    ],
                    [
                        'icon' => 'fas fa-database',
                        'value' => '0',
                        'label' => 'Procesos ETL',
                        'bgClass' => 'bg-warning',
                        'textClass' => 'text-dark'
                    ]
                ],
                'quickActions' => []
            ]);
        }
    }

    public function users()
    {
        $users = User::all();
        return view('admin.users', compact('users'));
    }

    public function system()
    {
        return view('admin.system');
    }
}