<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ScheduleVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgenteResumenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_admin, 403);

        /** @var ScheduleVersion|null $schedule */
        $schedule = $user->scheduleVersions()
            ->whereNull('effective_to')
            ->with(['shifts' => fn ($query) => $query->orderBy('weekday')->orderBy('start_time')])
            ->latest('effective_from')
            ->first();

        $tasks = $user->tareas()
            ->latest('tareas.created_at')
            ->get(['tareas.id', 'titulo', 'descripcion', 'tareas.created_at']);

        $chats = Conversation::query()
            ->with(['contact', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'contact' => $conversation->contact->profile_name
                    ?: $conversation->contact->phone
                    ?: $conversation->contact->wa_id,
                'status' => $conversation->status,
                'preview' => $conversation->latestMessage?->body,
                'last_message_at' => $conversation->last_message_at,
            ]);

        return response()->json(['data' => [
            'schedule' => $schedule ? [
                'effective_from' => $schedule->effective_from?->toDateString(),
                'shifts' => $schedule->shifts->map(fn ($shift) => [
                    'weekday' => $shift->weekday,
                    'start_time' => substr($shift->start_time, 0, 5),
                    'end_time' => substr($shift->end_time, 0, 5),
                ])->values(),
            ] : null,
            'tasks' => $tasks,
            'chats' => $chats,
        ]]);
    }
}
