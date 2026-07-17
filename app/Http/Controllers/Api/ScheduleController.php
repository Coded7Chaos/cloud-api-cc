<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Horarios de los agentes.
 *
 * Cada usuario tiene "versiones" de horario con vigencia [from, to). La versión
 * abierta (effective_to = null) es la vigente. Cada versión tiene turnos
 * semanales (weekday 1=Lun … 7=Dom, con start_time/end_time).
 */
class ScheduleController extends Controller
{
    /** Todos los usuarios con su horario vigente y sus turnos. */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $users = User::query()
            ->orderBy('name')
            ->with(['scheduleVersions' => function ($q) {
                $q->whereNull('effective_to')->with(['shifts' => fn ($s) => $s->orderBy('weekday')->orderBy('start_time')]);
            }])
            ->get();

        $data = $users->map(function (User $user) {
            /** @var ScheduleVersion|null $version */
            $version = $user->scheduleVersions->first();

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->name.' '.$user->last_name),
                ],
                'version' => $version ? [
                    'id' => $version->id,
                    'effective_from' => $version->effective_from?->toDateString(),
                ] : null,
                'shifts' => $version
                    ? $version->shifts->map(fn ($s) => [
                        'id' => $s->id,
                        'weekday' => $s->weekday,
                        'start_time' => substr((string) $s->start_time, 0, 5),
                        'end_time' => substr((string) $s->end_time, 0, 5),
                    ])->values()
                    : [],
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Reemplaza los turnos del horario vigente de un usuario. */
    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $data = $request->validate([
            'shifts' => ['present', 'array'],
            'shifts.*.weekday' => ['required', 'integer', 'between:1,7'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i', 'after:shifts.*.start_time'],
        ]);

        DB::transaction(function () use ($user, $data) {
            // Versión vigente, o crear una nueva abierta a partir de hoy.
            $version = $user->scheduleVersions()->whereNull('effective_to')->first()
                ?? $user->scheduleVersions()->create(['effective_from' => now()->toDateString()]);

            // Reemplazo total de los turnos de la versión vigente.
            $version->shifts()->delete();
            foreach ($data['shifts'] as $shift) {
                $version->shifts()->create($shift);
            }
        });

        return $this->index($request);
    }
}
