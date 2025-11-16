<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ETLController extends Controller
{
    public function index()
    {
        return view('etl.index');
    }

    public function executeETL(Request $request)
    {
        try {
            $process = $request->input('process');
           
            Log::info("Ejecutando proceso ETL: {$process}");
           
            switch ($process) {
                case 'carga_dimensiones':
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_LLENA_STAGING(); END;");
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_DIMENSIONES(); END;");
                    break;
                   
                case 'carga_hechos':
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(1, 100000, 1); END;");
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(2, 100000, 1); END;");
                    break;
                   
                case 'reprocesar_todo':
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_LLENA_STAGING(); END;");
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_DIMENSIONES(); END;");
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(1, 100000, 1); END;");
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(2, 100000, 1); END;");
                    break;
            }
           
            Log::info("Proceso ETL {$process} ejecutado correctamente");
           
            return response()->json([
                'success' => true,
                'message' => 'Proceso ETL ejecutado correctamente'
            ]);
           
        } catch (\Exception $e) {
            Log::error('Error en proceso ETL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en proceso ETL: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLogs()
    {
        try {
            $logs = DB::connection('oracle')
                ->table('REPROCESA_DIMENSIONES_QA')
                ->orderBy('FECHA', 'desc')
                ->limit(50)
                ->get();
               
            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
           
        } catch (\Exception $e) {
            Log::error('Error al obtener logs ETL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener logs: ' . $e->getMessage()
            ]);
        }
    }

    public function getSystemStatus()
    {
        try {
            $counts = DB::connection('oracle')
                ->select("
                    SELECT 
                        (SELECT COUNT(*) FROM HECHOS_T) as total_hechos,
                        (SELECT COUNT(*) FROM DIM_LOCALIZACION) as total_localizaciones,
                        (SELECT COUNT(*) FROM DIM_CLIMA) as total_clima,
                        (SELECT COUNT(*) FROM REPROCESA_DIMENSIONES_QA) as total_logs
                    FROM dual
                ");

            return response()->json([
                'success' => true,
                'status' => $counts[0] ?? []
            ]);
           
        } catch (\Exception $e) {
            Log::error('Error al obtener estado del sistema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado del sistema'
            ]);
        }
    }
}