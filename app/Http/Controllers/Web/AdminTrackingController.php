<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\View\View;

class AdminTrackingController extends Controller
{
    public function __invoke(): View
    {
        $assignments = Tarea::with('usuarios')
            ->latest()
            ->get()
            ->flatMap(fn (Tarea $task) => $task->usuarios->map(fn (User $user) => [
                'task' => $task,
                'user' => $user,
            ]));
        $agents = User::where('is_admin', false)->withCount('conversations')->withCount(['sentMessages as responses_count' => fn ($q) => $q->where('direction', 'outbound')])->withMax(['sentMessages as last_response_at' => fn ($q) => $q->where('direction', 'outbound')], 'sent_at')->orderBy('name')->get();

        return view('admin.tracking.index', compact('assignments', 'agents'));
    }
}
