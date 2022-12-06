<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Models\FleetVehicle;
use App\Models\IrConfigParameter;
use App\Models\PreguntasSolicitud;
use Illuminate\Http\Request;

class ConfigParamsController extends Controller
{
    public function getConfigParams(Request $request) {
        $keys = ['costo_local',
                'costo_foráneo',
                'costo_terracería',
                'tiempo_arribo_foráneo',
                'tiempo_arribo_local',
                'tiempo_arribo_terracería',
                'app_tel_soporte',
                'bank_name',
                'bank_beneficiary',
                'bank_important_note',
                'bank_clabe',
                'bank_card_number'
        ];

        $configParams = IrConfigParameter::
                        select('id', 'key', 'value')
                        ->whereIn('key', $keys)->get();

        return response()->json([
            'ok' => true,
            'config_params' => $configParams
        ], JsonResponse::OK);
    }

    public function getValidateQuestions(Request $request) {
        $questions = PreguntasSolicitud::orderBy('id', 'ASC')->get();

        return response()->json([
            'ok' => true,
            'validate_questions' => $questions
        ], JsonResponse::OK);
    }

    public function checkAvailabeFleets(Request $request) {
        $fleetCount = FleetVehicle::where('disponible_op', '=', true)->where('bloqueado_x_op', '=', false)->count();
        return response()->json([
            'ok' => true,
            'total_available' => $fleetCount
        ], JsonResponse::OK);
    }
}
