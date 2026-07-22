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
        $assignment = $user->tareas()->whereKey($tarea->id)->first();

        // Una tarea ajena no debe revelar ni siquiera que existe.
        abort_unless($assignment, 404);

        if ($assignment->pivot->status !== 'completed') {
            $user->tareas()->updateExistingPivot($tarea->id, [
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Tarea marcada como realizada.']);
    }
}
