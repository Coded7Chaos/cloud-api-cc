<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ScheduleVersion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = match ($user->role?->name) {
            'administrador' => $this->adminData(),
            'soporte' => $this->supportData($user),
            default => abort(403, 'Este rol no tiene un dashboard disponible.'),
        };

        return response()->json(['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function supportData(User $user): array
    {
        /** @var ScheduleVersion|null $schedule */
        $schedule = $user->scheduleVersions()
            ->whereNull('effective_to')
            ->with(['shifts' => fn ($query) => $query
                ->orderBy('weekday')
                ->orderBy('start_time')])
            ->latest('effective_from')
            ->first();

        $tasks = $user->tareas()
            ->orderByDesc('tareas.created_at')
            ->limit(6)
            ->get(['tareas.id', 'titulo', 'descripcion', 'tareas.created_at'])
            ->map(fn (Tarea $task) => [
                'id' => $task->id,
                'titulo' => $task->titulo,
                'descripcion' => $task->descripcion,
                'status' => $task->pivot->status,
                'assigned_at' => $task->pivot->created_at,
                'completed_at' => $task->pivot->completed_at,
            ]);

        $recentConversations = Conversation::query()
            ->where('assigned_user_id', $user->id)
            ->with(['contact', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'contact' => $conversation->contact->profile_name
                    ?: $conversation->contact->phone
                    ?: $conversation->contact->wa_id,
                'status' => $conversation->status,
                'preview' => $conversation->latestMessage?->body,
                'last_message_at' => $conversation->last_message_at,
            ]);

        return [
            'role' => 'soporte',
            'summary' => [
                'assigned_chats' => $user->assignedConversations()->count(),
                'active_chats' => $user->assignedConversations()->where('status', '!=', 'closed')->count(),
                'pending_tasks' => $user->tareas()->wherePivot('status', 'pending')->count(),
                'completed_tasks' => $user->tareas()->wherePivot('status', 'completed')->count(),
            ],
            'schedule' => $schedule ? [
                'effective_from' => $schedule->effective_from?->toDateString(),
                'shifts' => $schedule->shifts->map(fn ($shift) => [
                    'weekday' => $shift->weekday,
                    'start_time' => substr((string) $shift->start_time, 0, 5),
                    'end_time' => substr((string) $shift->end_time, 0, 5),
                ])->values(),
            ] : null,
            'tasks' => $tasks,
            'recent_conversations' => $recentConversations,
        ];
    }

    /** @return array<string, mixed> */
    private function adminData(): array
    {
        $agents = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'soporte'))
            ->withCount(['assignedConversations as chats_count'])
            ->withCount(['assignedConversations as active_chats_count' => fn ($query) => $query
                ->where('status', '!=', 'closed')])
            ->withCount(['sentMessages as responses_count'])
            ->withCount(['tareas as pending_tasks_count' => fn ($query) => $query
                ->where('tarea_user.status', 'pending')])
            ->withCount(['tareas as completed_tasks_count' => fn ($query) => $query
                ->where('tarea_user.status', 'completed')])
            ->withMax('sentMessages as last_response_at', 'sent_at')
            ->orderBy('name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $agent) => [
                'id' => $agent->id,
                'name' => trim($agent->name.' '.$agent->last_name),
                'email' => $agent->email,
                'chats_count' => $agent->chats_count,
                'active_chats_count' => $agent->active_chats_count,
                'responses_count' => $agent->responses_count,
                'pending_tasks_count' => $agent->pending_tasks_count,
                'completed_tasks_count' => $agent->completed_tasks_count,
                'last_response_at' => $agent->last_response_at,
            ]);

        $recentAssignments = Tarea::query()
            ->with(['usuarios' => fn ($query) => $query
                ->select('users.id', 'name', 'last_name', 'email')])
            ->latest()
            ->limit(8)
            ->get()
            ->flatMap(fn (Tarea $task) => $task->usuarios->map(fn (User $user) => [
                'task_id' => $task->id,
                'titulo' => $task->titulo,
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->name.' '.$user->last_name),
                    'email' => $user->email,
                ],
                'status' => $user->pivot->status,
                'assigned_at' => $user->pivot->created_at,
                'completed_at' => $user->pivot->completed_at,
            ]))->values();

        return [
            'role' => 'administrador',
            'summary' => [
                'agents' => $agents->count(),
                'total_chats' => Conversation::query()->count(),
                'active_chats' => Conversation::query()->where('status', '!=', 'closed')->count(),
                'unassigned_chats' => Conversation::query()->whereNull('assigned_user_id')->count(),
                'pending_tasks' => DB::table('tarea_user')->where('status', 'pending')->count(),
                'completed_tasks' => DB::table('tarea_user')->where('status', 'completed')->count(),
            ],
            'agent_activity' => $agents,
            'recent_task_assignments' => $recentAssignments,
        ];
    }
}
