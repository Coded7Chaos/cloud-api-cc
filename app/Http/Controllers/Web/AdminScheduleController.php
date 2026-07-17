<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::where('is_admin', false)->orderBy('name')->get();
        $selected = $users->firstWhere('id', $request->integer('user')) ?: $users->first();
        $schedule = $selected?->scheduleVersions()->whereNull('effective_to')->with('shifts')->latest('effective_from')->first();

        return view('admin.schedules.index', compact('users', 'selected', 'schedule'));
    }

    public function update(Request $request, User $user, ScheduleService $service): RedirectResponse
    {
        $data = $request->validate(['shifts' => ['nullable', 'array'], 'shifts.*.weekday' => ['required', 'integer', 'between:1,7'], 'shifts.*.start_time' => ['nullable', 'date_format:H:i'], 'shifts.*.end_time' => ['nullable', 'date_format:H:i']]);
        $pattern = collect($data['shifts'] ?? [])->filter(fn ($shift) => filled($shift['start_time']) && filled($shift['end_time']) && $shift['start_time'] < $shift['end_time'])->values()->all();
        $service->changeSchedule($user, $pattern, Carbon::today());

        return redirect()->route('admin.schedules.index', ['user' => $user])->with('success', 'Horario actualizado.');
    }
}
