<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo de permisos disponibles. Alimenta las casillas del formulario de
 * roles; el agrupado por categoría (el prefijo antes del punto: "usuarios.ver"
 * -> "usuarios") lo arma el front a partir del nombre.
 */
class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
        ]);
    }
}
