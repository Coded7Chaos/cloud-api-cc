<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompletarTareaController extends Controller
{
    public function __invoke(Request $request, Tarea $tarea): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_admin, 403);
        abort_unless($user->tareas()->whereKey($tarea->id)->exists(), 404);

        $user->tareas()->updateExistingPivot($tarea->id, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Tarea marcada como realizada.']);
    }
}
