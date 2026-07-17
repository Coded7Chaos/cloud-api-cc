<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSeguimientoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $tasks = Tarea::query()
            ->with(['usuarios' => fn ($query) => $query->select('users.id', 'name', 'last_name', 'email')])
            ->latest()
            ->get()
            ->flatMap(fn (Tarea $task) => $task->usuarios->map(fn (User $user) => [
                'task_id' => $task->id,
                'titulo' => $task->titulo,
                'descripcion' => $task->descripcion,
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->name.' '.$user->last_name),
                    'email' => $user->email,
                ],
                'status' => $user->pivot->status,
                'assigned_at' => $user->pivot->created_at,
                'completed_at' => $user->pivot->completed_at,
            ]))->values();

        $agents = User::query()
            ->where('is_admin', false)
            ->withCount('conversations')
            ->withCount(['sentMessages as responses_count' => fn ($query) => $query->where('direction', 'outbound')])
            ->withMax(['sentMessages as last_response_at' => fn ($query) => $query->where('direction', 'outbound')], 'sent_at')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => trim($user->name.' '.$user->last_name),
                'email' => $user->email,
                'chats_count' => $user->conversations_count,
                'responses_count' => $user->responses_count,
                'last_response_at' => $user->last_response_at,
            ]);

        return response()->json(['data' => [
            'task_assignments' => $tasks,
            'agent_activity' => $agents,
        ]]);
    }
}
