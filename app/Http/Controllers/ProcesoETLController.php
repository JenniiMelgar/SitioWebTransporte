<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProcesoETLController extends Controller
{
    public function index()
    {
        return view('etl.index');
    }


    public function getSystemStatus()
    {
        try {
            $counts = DB::connection('oracle')
                ->select("
                    SELECT
                        (SELECT COUNT(*) FROM HECHOS_T) as total_hechos,
                        (SELECT COUNT(*) FROM DATOS) as total_datos,
                        (SELECT COUNT(*) FROM DIM_LOCALIZACION) as total_localizaciones,
                        (SELECT COUNT(*) FROM DIM_CLIMA) as total_clima,
                        (SELECT COUNT(*) FROM DIM_INFRAESTRUCTURA) as total_infraestructura,
                        (SELECT COUNT(*) FROM DIM_LUZ) as total_luz,
                        (SELECT COUNT(*) FROM DIM_FUENTE) as total_fuente,
                        (SELECT COUNT(*) FROM DIM_TIEMPO_ESTADO) as total_tiempo,
                        (SELECT COUNT(*) FROM NO_HECHOS_T) as total_no_hechos,
                        (SELECT MAX(VALOR) FROM CONTROL_NUMERACION WHERE NOMBRE_DIMENSION = 'DIM_LOCALIZACION') as max_loc_key
                    FROM dual
                ");

            $batchInfo = DB::connection('oracle')
                ->select("
                    SELECT 
                        CEIL(COUNT(*) / 200000) as total_batches,
                        COUNT(*) as total_registros
                    FROM DATOS
                ");

            return response()->json([
                'success' => true,
                'status' => $counts[0] ?? [],
                'batchInfo' => $batchInfo[0] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estado del sistema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado del sistema: ' . $e->getMessage()
            ]);
        }
    }

    public function executeETL(Request $request)
    {
        $process = $request->input('process');
        $batchSize = $request->input('batch_size', 200000);
        $batchNumber = $request->input('batch_number', 1);
        
        $userName = Auth::check() ? Auth::user()->name : 'Sistema';
        $userIp = $request->ip();

        try {
            Log::info("Ejecutando proceso ETL: {$process}", [
                'batch_size' => $batchSize,
                'batch_number' => $batchNumber,
                'user' => $userName,
                'ip' => $userIp
            ]);

            // Registrar inicio del proceso
            $this->logETLStart($process, 'general', $batchNumber, $batchSize, $userName, $userIp);

            $startTime = microtime(true);
            $result = [];
            
            switch ($process) {
                case 'limpiar_preparar':
                    $result = $this->executeLimpiezaPreparacion();
                    break;
                    
                case 'carga_dimensiones':
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_DIMENSIONES(); END;");
                    $result = [
                        'message' => 'Dimensiones cargadas correctamente',
                        'registros_procesados' => 0
                    ];
                    break;
                    
                case 'carga_hechos_batch':
                    $result = $this->executeCargaHechosBatch($batchSize, $batchNumber);
                    break;
                    
                case 'carga_hechos_fase2':
                    DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(2, 200000, 1); END;");
                    $result = [
                        'message' => 'Fase 2 de hechos completada',
                        'registros_procesados' => 0
                    ];
                    break;
                    
                case 'reprocesar_dimension':
                    $dimension = $request->input('dimension');
                    $result = $this->reprocesarDimension($dimension);
                    break;
                    
                case 'reprocesar_todo':
                    $result = $this->executeProcesoCompleto();
                    break;
                    
                default:
                    throw new \Exception('Proceso no reconocido: ' . $process);
            }

            // Calcular duración
            $duracionSegundos = round(microtime(true) - $startTime, 2);

            // Registrar finalización exitosa
            $this->logETLEnd(
                $process, 
                'completado', 
                $result['message'] ?? 'Proceso ejecutado correctamente',
                $result['registros_procesados'] ?? 0,
                0,
                $duracionSegundos,
                $userName,
                $userIp
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Proceso ejecutado correctamente',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error en proceso ETL: ' . $e->getMessage());
            
            // Registrar error en logs
            $this->logETLEnd(
                $process ?? 'desconocido', 
                'error', 
                'Error: ' . $e->getMessage(),
                0,
                0,
                0,
                $userName,
                $userIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Error en proceso ETL: ' . $e->getMessage()
            ], 500);
        }
    }

    private function executeLimpiezaPreparacion()
    {
        try {
            // Limpiar tablas
            DB::connection('oracle')->statement("TRUNCATE TABLE NO_HECHOS_T");
            DB::connection('oracle')->statement("TRUNCATE TABLE HECHOS_T");
            
            // Poblar valores default
            DB::connection('oracle')->statement("TRUNCATE TABLE VALORES_DEFAULT");
            $defaultValues = [
                ['SEVERITY', 0],
                ['DISTANCE_MI', 0],
                ['TEMPERATURE_F', 0],
                ['WIND_CHILL_F', 0],
                ['HUMIDITY', 0],
                ['PRESSURE_IN', 0],
                ['VISIBILITY_MI', 0],
                ['WIND_SPEED_MPH', 0],
                ['PRECIPITATION_IN', 0]
            ];
            
            foreach ($defaultValues as $value) {
                DB::connection('oracle')->table('VALORES_DEFAULT')->insert([
                    'NOMBRE_METRICA' => $value[0],
                    'VALOR' => $value[1]
                ]);
            }

            // Configurar dimensiones default
            DB::connection('oracle')->statement("TRUNCATE TABLE REPROCESA_DIMENSIONES_QA");
            $dimensionesDefault = [
                'DIM_TIEMPO_ESTADO', 'DIM_LOCALIZACION', 'DIM_CLIMA', 
                'DIM_INFRAESTRUCTURA', 'DIM_LUZ', 'DIM_FUENTE'
            ];
            
            foreach ($dimensionesDefault as $dimension) {
                DB::connection('oracle')->table('REPROCESA_DIMENSIONES_QA')->insert([
                    'NOMBRE_DIMENSION' => $dimension,
                    'FECHA' => now(),
                    'PARAMETRO' => 1
                ]);
            }

            return [
                'message' => 'Limpieza y preparación completadas correctamente',
                'registros_procesados' => count($defaultValues) + count($dimensionesDefault)
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error en limpieza y preparación: ' . $e->getMessage());
        }
    }

    private function executeCargaHechosBatch($batchSize, $batchNumber)
    {
        try {
            // Obtener estadísticas antes del proceso
            $statsBefore = DB::connection('oracle')
                ->select("
                    SELECT 
                        (SELECT COUNT(*) FROM HECHOS_T) as hechos_antes,
                        (SELECT COUNT(*) FROM NO_HECHOS_T) as no_hechos_antes
                    FROM dual
                ")[0];

            // Ejecutar fase 1 para el batch específico
            DB::connection('oracle')->statement("
                BEGIN 
                    PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(1, :batchSize, :batchNumber); 
                END;
            ", ['batchSize' => $batchSize, 'batchNumber' => $batchNumber]);

            // Ejecutar fase 2 para procesar NO_HECHOS_T
            DB::connection('oracle')->statement("
                BEGIN 
                    PKG_DWT_CARGA.PR_CARGA_HECHOS_FASE(2, :batchSize, :batchNumber); 
                END;
            ", ['batchSize' => $batchSize, 'batchNumber' => $batchNumber]);

            // Obtener estadísticas después del proceso
            $statsAfter = DB::connection('oracle')
                ->select("
                    SELECT 
                        (SELECT COUNT(*) FROM HECHOS_T) as hechos_despues,
                        (SELECT COUNT(*) FROM NO_HECHOS_T) as no_hechos_despues
                    FROM dual
                ")[0];

            $hechosProcesados = $statsAfter->hechos_despues - $statsBefore->hechos_antes;
            $noHechosProcesados = $statsAfter->no_hechos_despues - $statsBefore->no_hechos_antes;

            return [
                'message' => "Batch {$batchNumber} procesado correctamente",
                'registros_procesados' => $hechosProcesados + $noHechosProcesados,
                'stats' => [
                    'hechos_procesados' => $hechosProcesados,
                    'no_hechos_procesados' => $noHechosProcesados,
                    'total_hechos' => $statsAfter->hechos_despues,
                    'total_no_hechos' => $statsAfter->no_hechos_despues
                ]
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error en carga de hechos por batch: ' . $e->getMessage());
        }
    }

    private function reprocesarDimension($dimension)
    {
        try {
            $dimensionesValidas = [
                'DIM_LOCALIZACION', 'DIM_CLIMA', 'DIM_INFRAESTRUCTURA', 
                'DIM_LUZ', 'DIM_FUENTE', 'DIM_TIEMPO_ESTADO'
            ];

            if (!in_array($dimension, $dimensionesValidas)) {
                throw new \Exception('Dimensión no válida: ' . $dimension);
            }

            // Limpiar la dimensión específica
            DB::connection('oracle')->statement("TRUNCATE TABLE {$dimension}");

            $registrosInsertados = 0;

            // Insertar valor por defecto
            if ($dimension === 'DIM_LOCALIZACION') {
                DB::connection('oracle')->table($dimension)->insert([
                    'LOC_KEY' => 1,
                    'STREET' => 'UNKNOWN', 'CITY' => 'UNKNOWN', 'COUNTY' => 'UNKNOWN',
                    'STATE' => 'UNKNOWN', 'ZIPCODE' => 'UNKNOWN', 'COUNTRY' => 'UNKNOWN',
                    'TIMEZONE' => 'UNKNOWN', 'AIRPORT_CODE' => 'UNKNOWN',
                    'START_LAT' => null, 'START_LNG' => null, 'END_LAT' => null, 'END_LNG' => null
                ]);
                $registrosInsertados = 1;
            } elseif ($dimension === 'DIM_CLIMA') {
                DB::connection('oracle')->table($dimension)->insert([
                    'CLIMA_KEY' => 1,
                    'WEATHER_CONDITION' => 'UNKNOWN',
                    'WIND_DIRECTION' => 'UNKNOWN'
                ]);
                $registrosInsertados = 1;
            } elseif ($dimension === 'DIM_INFRAESTRUCTURA') {
                DB::connection('oracle')->table($dimension)->insert([
                    'INFRA_KEY' => 1,
                    'AMENITY' => 'UNK', 'BUMP' => 'UNK', 'CROSSING' => 'UNK',
                    'GIVE_WAY' => 'UNK', 'JUNCTION' => 'UNK', 'NO_EXIT' => 'UNK',
                    'RAILWAY' => 'UNK', 'ROUNDABOUT' => 'UNK', 'STATION' => 'UNK',
                    'STOP' => 'UNK', 'TRAFFIC_CALMING' => 'UNK', 'TRAFFIC_SIGNAL' => 'UNK',
                    'TURNING_LOOP' => 'UNK'
                ]);
                $registrosInsertados = 1;
            } elseif ($dimension === 'DIM_LUZ') {
                DB::connection('oracle')->table($dimension)->insert([
                    'LUZ_KEY' => 1,
                    'SUNRISE_SUNSET' => 'UNKNOWN',
                    'CIVIL_TWILIGHT' => 'UNKNOWN',
                    'NAUTICAL_TWILIGHT' => 'UNKNOWN',
                    'ASTRONOMICAL_TWILIGHT' => 'UNKNOWN'
                ]);
                $registrosInsertados = 1;
            } elseif ($dimension === 'DIM_FUENTE') {
                DB::connection('oracle')->table($dimension)->insert([
                    'FUENTE_KEY' => 1,
                    'SOURCE' => 'UNKNOWN'
                ]);
                $registrosInsertados = 1;
            } elseif ($dimension === 'DIM_TIEMPO_ESTADO') {
                DB::connection('oracle')->table($dimension)->insert([
                    'TIEMPO_KEY' => 1,
                    'FECHA_KEY' => 1,
                    'HORA_INICIO_KEY' => 1,
                    'HORA_FIN_KEY' => 1,
                    'HORA_WEATHER_KEY' => 1
                ]);
                $registrosInsertados = 1;
            }

            // Actualizar control de numeración
            DB::connection('oracle')->table('CONTROL_NUMERACION')
                ->where('NOMBRE_DIMENSION', $dimension)
                ->update(['VALOR' => 1]);

            // Registrar en reprocesa_dimensiones_qa
            DB::connection('oracle')->table('REPROCESA_DIMENSIONES_QA')
                ->where('NOMBRE_DIMENSION', $dimension)
                ->delete();
                
            DB::connection('oracle')->table('REPROCESA_DIMENSIONES_QA')->insert([
                'NOMBRE_DIMENSION' => $dimension,
                'FECHA' => now(),
                'PARAMETRO' => 1
            ]);

            return [
                'message' => "Dimensión {$dimension} reprocesada correctamente",
                'registros_procesados' => $registrosInsertados
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error reprocesando dimensión: ' . $e->getMessage());
        }
    }

    private function executeProcesoCompleto()
    {
        try {
            $totalRegistros = 0;

            // 1. Limpieza y preparación
            $cleanResult = $this->executeLimpiezaPreparacion();
            $totalRegistros += $cleanResult['registros_procesados'];
            
            // 2. Cargar staging
            DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_LLENA_STAGING(); END;");
            
            // 3. Cargar dimensiones
            DB::connection('oracle')->statement("BEGIN PKG_DWT_CARGA.PR_CARGA_DIMENSIONES(); END;");
            
            // 4. Obtener número total de batches necesarios
            $batchInfo = DB::connection('oracle')
                ->select("SELECT CEIL(COUNT(*) / 200000) as total_batches FROM DATOS");
            
            $totalBatches = $batchInfo[0]->total_batches ?? 1;
            
            // 5. Ejecutar batches de hechos
            for ($batch = 1; $batch <= $totalBatches; $batch++) {
                $batchResult = $this->executeCargaHechosBatch(200000, $batch);
                $totalRegistros += $batchResult['registros_procesados'];
            }

            return [
                'message' => "Proceso ETL completo ejecutado. {$totalBatches} batches procesados.",
                'registros_procesados' => $totalRegistros,
                'total_batches' => $totalBatches
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error en proceso completo ETL: ' . $e->getMessage());
        }
    }

    public function getLogs()
    {
        try {
            $logs = DB::connection('oracle')
                ->table('TABLE_ETL_LOGS')
                ->select([
                    'ID',
                    'PROCESO',
                    'TIPO_PROCESO',
                    'BATCH_NUM',
                    'BATCH_SIZE',
                    'ESTADO',
                    'REGISTROS_PROCESADOS',
                    'REGISTROS_PENDIENTES',
                    'DURACION_SEGUNDOS',
                    'DETALLES',
                    DB::raw("TO_CHAR(FECHA_INICIO, 'YYYY-MM-DD HH24:MI:SS') as FECHA_INICIO"),
                    DB::raw("TO_CHAR(FECHA_FIN, 'YYYY-MM-DD HH24:MI:SS') as FECHA_FIN"),
                    'USUARIO',
                    'IP_EJECUCION'
                ])
                ->orderBy('FECHA_INICIO', 'desc')
                ->limit(100)
                ->get();

            // Debug: Verificar estructura de datos
            if ($logs->isNotEmpty()) {
                Log::info('Estructura de logs ETL:', [
                    'count' => $logs->count(),
                    'first_record' => (array)$logs->first()
                ]);
            }

            return response()->json([
                'success' => true,
                'etlLogs' => $logs
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo logs ETL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo logs: ' . $e->getMessage(),
                'etlLogs' => []
            ]);
        }
    }

    public function getBatchProgress()
    {
        try {
            // Obtener información de progreso actual
            $progressInfo = DB::connection('oracle')
                ->select("
                    SELECT 
                        (SELECT COUNT(*) FROM HECHOS_T) as hechos_procesados,
                        (SELECT COUNT(*) FROM DATOS) as total_registros,
                        (SELECT COUNT(*) FROM NO_HECHOS_T) as registros_pendientes,
                        (SELECT CEIL(COUNT(*) / 200000) FROM DATOS) as total_batches,
                        (SELECT COUNT(DISTINCT BATCH_NUM) FROM TABLE_ETL_LOGS WHERE PROCESO = 'carga_hechos_batch' AND ESTADO = 'completado') as batches_completados
                    FROM dual
                ")[0];

            $hechosProcesados = $progressInfo->hechos_procesados ?? 0;
            $totalRegistros = $progressInfo->total_registros ?? 0;
            $registrosPendientes = $progressInfo->registros_pendientes ?? 0;
            $totalBatches = $progressInfo->total_batches ?? 1;
            $batchesCompletados = $progressInfo->batches_completados ?? 0;

            // Calcular porcentaje
            $porcentaje = $totalRegistros > 0 ? round(($hechosProcesados / $totalRegistros) * 100, 2) : 0;
            
            // Determinar siguiente batch recomendado
            $batchRecomendado = $batchesCompletados + 1;
            if ($batchRecomendado > $totalBatches) {
                $batchRecomendado = $totalBatches;
            }

            // Verificar si el proceso está completado
            $procesoCompletado = ($hechosProcesados >= $totalRegistros) && ($registrosPendientes == 0);

            return response()->json([
                'success' => true,
                'progress' => [
                    'hechos_procesados' => $hechosProcesados,
                    'total_registros' => $totalRegistros,
                    'registros_pendientes' => $registrosPendientes,
                    'batches_completados' => $batchesCompletados,
                    'total_batches' => $totalBatches
                ],
                'porcentaje' => $porcentaje,
                'batch_recomendado' => $batchRecomendado,
                'proceso_completado' => $procesoCompletado
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo progreso de batch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo progreso: ' . $e->getMessage()
            ]);
        }
    }

    private function logETLStart($proceso, $tipoProceso, $batchNumber, $batchSize, $usuario, $ipEjecucion)
    {
        try {
            DB::connection('oracle')->table('TABLE_ETL_LOGS')->insert([
                'PROCESO' => $proceso,
                'TIPO_PROCESO' => $tipoProceso,
                'BATCH_NUM' => $batchNumber,
                'BATCH_SIZE' => $batchSize,
                'ESTADO' => 'iniciado',
                'REGISTROS_PROCESADOS' => 0,
                'REGISTROS_PENDIENTES' => 0,
                'DETALLES' => "Inicio del proceso {$proceso}",
                'FECHA_INICIO' => now(),
                'USUARIO' => $usuario,
                'IP_EJECUCION' => $ipEjecucion
            ]);

            Log::info("Log ETL iniciado: {$proceso}");

        } catch (\Exception $e) {
            Log::error('Error guardando log ETL de inicio: ' . $e->getMessage());
        }
    }

    private function logETLEnd($proceso, $estado, $detalles, $registrosProcesados, $registrosPendientes, $duracionSegundos, $usuario, $ipEjecucion)
    {
        try {
            DB::connection('oracle')->table('TABLE_ETL_LOGS')
                ->where('PROCESO', $proceso)
                ->where('ESTADO', 'iniciado')
                ->orderBy('FECHA_INICIO', 'desc')
                ->limit(1)
                ->update([
                    'ESTADO' => $estado,
                    'REGISTROS_PROCESADOS' => $registrosProcesados,
                    'REGISTROS_PENDIENTES' => $registrosPendientes,
                    'DURACION_SEGUNDOS' => $duracionSegundos,
                    'DETALLES' => substr($detalles, 0, 4000),
                    'FECHA_FIN' => now(),
                    'USUARIO' => $usuario,
                    'IP_EJECUCION' => $ipEjecucion
                ]);

            Log::info(" Log ETL finalizado: {$proceso} - {$estado}");

        } catch (\Exception $e) {
            Log::error('Error guardando log ETL de fin: ' . $e->getMessage());
            
            // Fallback: insertar nuevo registro si la actualización falla
            try {
                DB::connection('oracle')->table('TABLE_ETL_LOGS')->insert([
                    'PROCESO' => $proceso,
                    'TIPO_PROCESO' => 'general',
                    'ESTADO' => $estado,
                    'REGISTROS_PROCESADOS' => $registrosProcesados,
                    'REGISTROS_PENDIENTES' => $registrosPendientes,
                    'DURACION_SEGUNDOS' => $duracionSegundos,
                    'DETALLES' => substr($detalles, 0, 4000),
                    'FECHA_INICIO' => now()->subSeconds($duracionSegundos),
                    'FECHA_FIN' => now(),
                    'USUARIO' => $usuario,
                    'IP_EJECUCION' => $ipEjecucion
                ]);
            } catch (\Exception $e2) {
                Log::error(' Error crítico en fallback de log ETL: ' . $e2->getMessage());
            }
        }
    }
}