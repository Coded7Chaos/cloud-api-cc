<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TareaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'usuarios' => ['required', 'array', 'min:1'],
            'usuarios.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $tarea = DB::transaction(function () use ($data): Tarea {
            $tarea = Tarea::create([
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $tarea->usuarios()->attach($data['usuarios']);

            return $tarea;
        });

        return response()->json([
            'data' => $tarea->load('usuarios:id,name,last_name,email'),
        ], 201);
    }
}
