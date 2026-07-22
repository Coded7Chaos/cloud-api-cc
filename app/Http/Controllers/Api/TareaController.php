<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TareaController extends Controller
{
    /**
     * Lista las tareas visibles para el usuario actual.
     *
     * Los administradores reciben el seguimiento completo y los agentes que
     * pueden recibir nuevas asignaciones. Soporte recibe únicamente lo suyo.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('administrador');

        $tasks = Tarea::query()
            ->when(! $isAdmin, fn ($query) => $query->whereHas(
                'usuarios',
                fn ($query) => $query->whereKey($user->id),
            ))
            ->with(['usuarios' => fn ($query) => $query
                ->when(! $isAdmin, fn ($query) => $query->whereKey($user->id))
                ->select('users.id', 'name', 'last_name', 'email')])
            ->latest()
            ->get()
            ->map(fn (Tarea $task) => $this->taskData($task));

        $response = ['data' => $tasks];

        if ($isAdmin) {
            $response['assignable_users'] = User::query()
                ->whereHas('role', fn ($query) => $query->where('name', 'soporte'))
                ->orderBy('name')
                ->orderBy('last_name')
                ->get(['id', 'name', 'last_name', 'email']);
        }

        return response()->json($response);
    }

    /** Crea una tarea y la asigna a uno o más agentes de soporte. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'usuarios' => ['required', 'array', 'min:1'],
            'usuarios.*' => ['integer', 'distinct'],
        ]);

        $userIds = collect($data['usuarios'])->map(fn ($id) => (int) $id)->values();
        $assignableIds = User::query()
            ->whereKey($userIds)
            ->whereHas('role', fn ($query) => $query->where('name', 'soporte'))
            ->pluck('id');

        if ($assignableIds->count() !== $userIds->count()) {
            throw ValidationException::withMessages([
                'usuarios' => ['Sólo se pueden asignar tareas a usuarios activos con rol de soporte.'],
            ]);
        }

        $task = DB::transaction(function () use ($data, $assignableIds): Tarea {
            $task = Tarea::create([
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $task->usuarios()->attach($assignableIds);

            return $task;
        });

        $task->load(['usuarios' => fn ($query) => $query
            ->select('users.id', 'name', 'last_name', 'email')]);

        return response()->json([
            'data' => $this->taskData($task),
            'message' => 'Tarea creada y asignada.',
        ], 201);
    }

    /** @return array<string, mixed> */
    private function taskData(Tarea $task): array
    {
        return [
            'id' => $task->id,
            'titulo' => $task->titulo,
            'descripcion' => $task->descripcion,
            'created_at' => $task->created_at,
            'usuarios' => $task->usuarios->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->pivot->status,
                'assigned_at' => $user->pivot->created_at,
                'completed_at' => $user->pivot->completed_at,
            ])->values(),
        ];
    }
}
