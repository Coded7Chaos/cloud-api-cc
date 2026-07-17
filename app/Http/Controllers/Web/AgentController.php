<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Tarea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $schedule = $user->scheduleVersions()->whereNull('effective_to')->with(['shifts' => fn ($q) => $q->orderBy('weekday')])->latest('effective_from')->first();
        $tasks = $user->tareas()->latest('tareas.created_at')->get();
        $chats = Conversation::with(['contact', 'latestMessage'])->orderByDesc('last_message_at')->get();

        return view('agent.dashboard', compact('schedule', 'tasks', 'chats'));
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->where(fn ($q) => $q->where('assigned_user_id', $user->id)->orWhereHas('messages', fn ($m) => $m->where('direction', 'outbound')->where('sender_user_id', $user->id)))
            ->with('contact')->withCount(['messages', 'messages as sent_messages_count' => fn ($q) => $q->where('direction', 'outbound')->where('sender_user_id', $user->id), 'messages as received_messages_count' => fn ($q) => $q->where('direction', 'inbound')])
            ->orderByDesc('last_message_at')->get();

        return view('agent.history', compact('conversations'));
    }

    public function completeTask(Request $request, Tarea $tarea): RedirectResponse
    {
        abort_unless($request->user()->tareas()->whereKey($tarea->id)->exists(), 404);
        $request->user()->tareas()->updateExistingPivot($tarea->id, ['status' => 'completed', 'completed_at' => now()]);

        return back()->with('success', 'Tarea marcada como realizada.');
    }
}
