<?php

namespace App\Http\Controllers;

use App\Enums\JsonResponse;
use App\Models\Countries;
use App\Models\States;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Marcas;
use App\Models\ClaseVehiculos;
use App\Models\TipoVehiculos;
use App\Models\ColorVehiculos;
use App\Models\TipoPago;
use App\Models\TipoServicio;

class CatalogController extends Controller
{
    // Método para obtener listado de paises
    public function listCountries(Request $request) {
        $validateData = Validator::make($request->all(), [
            'code' => 'nullable|string',
            'name' => 'nullable|string'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }
        $query = Countries::query();

        if ($request->has('code')) {
            $query->where('code', '=', $request->code);
            if ($request->code == 'MX') {
                $query->orderByRaw("(code, '$request->code') DESC");
            }
        }

        if ($request->has('name')) {
            $query->orWhere('name', 'like', '%'.$request->name.'%');
        }

        $countries = $query
        ->select('id', 'name', 'code', 'currency_id', 'phone_code')
        ->orderBy('name', 'ASC')
        ->get();

        return response()->json([
            'ok' => true,
            'countries' => $countries
        ], JsonResponse::OK);
    }

    // Obtener listado de estados por country_id
    public function listStates(Request $request) {
        $validateData = Validator::make($request->all(), [
            'country_id' => 'required|numeric',
            'name' => 'nullable|string'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $query = States::query();

        $query->where('country_id', '=', $request->country_id);

        if ($request->has('name') && $request->name != null) {
            $query->orWhere('name', 'like', '%' . $request->name . '%');
        }

        $states = $query
            ->select('id', 'country_id', 'name', 'code')
            ->orderBy('name', 'ASC')
            ->get();


        return response()->json([
            'ok' => true,
            'states' => $states
        ], JsonResponse::OK);
    }

    //obtener listado de marcas de auto
    public function listMarcasVehiculo() {
        $marcasVehiculo = Marcas::select('id', 'name')->orderBy('name', 'ASC')->get();


        return response()->json([
            'ok' => true,
            'marcas_vehiculo' => $marcasVehiculo
        ], JsonResponse::OK);
    }

    // obtener listado de claseVehiculos
    public function listClasesVehiculos(Request $request) {
        $validateData = Validator::make($request->all(), [
            'marca_id' => 'required|numeric|exists:cms_marcas,id'
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validateData->errors()->all()
            ], JsonResponse::BAD_REQUEST);
        }

        $clases_vehiculo = ClaseVehiculos::select('id', 'name', 'marca_id')
        ->where('marca_id', '=', $request->marca_id)
        ->where('active', '=', true)
        ->orderBy('name', 'ASC')
        ->get();

        return response()->json([
            'ok' => true,
            'clases_vehiculo' => $clases_vehiculo
        ], JsonResponse::OK);
    }

    // obtener listado de tipo de vehiculo
    public function listTipoVehiculos(Request $request) {
        $tipoVehiculos = TipoVehiculos::select('id', 'name', 'icon_name')
        ->orderBy('name', 'ASC')
        ->get();

        return response()->json([
            'ok' => true,
            'tipo_vehiculos' => $tipoVehiculos
        ], JsonResponse::OK);
    }

    // Obtener listado de tipo color vehiculo
    public function listColorVehiculo(Request $request) {
        $colorVehiculos = ColorVehiculos::select('id', 'name')
        ->orderBy('name', 'ASC')->get();

        return response()->json([
            'ok' => true,
            'color_vehiculos' => $colorVehiculos
        ], JsonResponse::OK);
    }

    // obtener listado de tipo se servicio
    public function listTipoServicios(Request $request) {
        $tiposervicios = TipoServicio::select('id', 'name')
        ->orderBy('id', 'ASC')->get();

        return response()->json([
            'ok' => true,
            'tipo_servicios' => $tiposervicios
        ], JsonResponse::OK);
    }

    // obtener lsitado tipo pagos
    public function listTiposPagos(Request $request) {
        $tipospagos = TipoPago::select('id', 'name', 'banco')
        ->where('disponible_app', '=', true)
        ->orderBy('name', 'ASC')->get();

        return response()->json([
            'ok' => true,
            'tipo_pagos' => $tipospagos
        ], JsonResponse::OK);
    }

}
