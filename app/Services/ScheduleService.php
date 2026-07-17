<?php

namespace App\Services;

use App\Models\ScheduleShift;
use App\Models\ScheduleVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Versionado temporal de horarios.
 *
 * Cada ScheduleVersion vale durante el intervalo medio abierto
 * [effective_from, effective_to). Editar NUNCA muta el pasado: corta la
 * versión vigente y abre una nueva a partir de la fecha del cambio.
 *
 * El "patrón semanal" es un array de turnos:
 *   [
 *     ['weekday' => 1, 'start_time' => '07:00', 'end_time' => '12:00'],
 *     ['weekday' => 1, 'start_time' => '14:00', 'end_time' => '22:00'],
 *     ...
 *   ]
 */
class ScheduleService
{
    /**
     * Crea el primer horario de un usuario (o el inicial de un nuevo periodo).
     *
     * @param  array<int, array{weekday:int, start_time:string, end_time:string}>  $weeklyPattern
     */
    public function createSchedule(User $user, array $weeklyPattern, Carbon $from): ScheduleVersion
    {
        return DB::transaction(function () use ($user, $weeklyPattern, $from) {
            $version = $user->scheduleVersions()->create([
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
            ]);

            $this->syncShifts($version, $weeklyPattern);

            return $version->load('shifts');
        });
    }

    /**
     * Cambia el horario a partir de $effectiveDate sin tocar el pasado.
     *
     * 1. Encuentra la versión vigente en $effectiveDate.
     * 2. La corta: effective_to = $effectiveDate (queda congelada como historial).
     * 3. Crea una versión nueva [$effectiveDate, null) con el patrón nuevo.
     *
     * @param  array<int, array{weekday:int, start_time:string, end_time:string}>  $weeklyPattern
     */
    public function changeSchedule(User $user, array $weeklyPattern, Carbon $effectiveDate): ScheduleVersion
    {
        return DB::transaction(function () use ($user, $weeklyPattern, $effectiveDate) {
            $current = $this->versionForDate($user, $effectiveDate);

            // Si ya hay una versión que empieza exactamente ese día, la reescribimos
            // en vez de crear un duplicado de longitud cero.
            if ($current && $current->effective_from->isSameDay($effectiveDate)) {
                $current->shifts()->delete();
                $this->syncShifts($current, $weeklyPattern);

                return $current->load('shifts');
            }

            // Cortar la versión vigente el día del cambio (exclusivo).
            $current?->update(['effective_to' => $effectiveDate->toDateString()]);

            $version = $user->scheduleVersions()->create([
                'effective_from' => $effectiveDate->toDateString(),
                'effective_to' => null,
            ]);

            $this->syncShifts($version, $weeklyPattern);

            return $version->load('shifts');
        });
    }

    /**
     * Devuelve la versión que aplicaba en una fecha dada (o null si no había).
     */
    public function versionForDate(User $user, Carbon $date): ?ScheduleVersion
    {
        $d = $date->toDateString();

        // whereDate compara SOLO la parte de fecha. Es necesario porque el cast
        // "date" de Eloquent guarda la columna como 'Y-m-d 00:00:00', y una
        // comparación de texto fallaría en el borde (fecha == effective_from)
        // sobre todo en SQLite. Así funciona igual en MySQL y SQLite.
        return $user->scheduleVersions()
            ->whereDate('effective_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>', $d);
            })
            ->with('shifts')
            ->first();
    }

    /**
     * Los turnos reales de un día concreto, según la versión vigente ese día.
     *
     * @return Collection<int, ScheduleShift>
     */
    public function shiftsForDate(User $user, Carbon $date)
    {
        $version = $this->versionForDate($user, $date);

        if (! $version) {
            return collect();
        }

        return $version->shifts
            ->where('weekday', $date->dayOfWeekIso) // 1=Lun ... 7=Dom
            ->values();
    }

    /** Indica si el usuario ya tiene al menos un horario configurado. */
    public function hasConfiguredSchedule(User $user): bool
    {
        return $user->scheduleVersions()->exists();
    }

    /** True si $at cae dentro de alguno de los turnos del usuario para ese día. */
    public function isWithinWorkingHoursAt(User $user, ?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->shiftsForDate($user, $at)->contains(function ($shift) use ($at) {
            $start = $at->copy()->setTimeFromTimeString(substr((string) $shift->start_time, 0, 5));
            $end = $at->copy()->setTimeFromTimeString(substr((string) $shift->end_time, 0, 5));

            return $at->greaterThanOrEqualTo($start) && $at->lessThan($end);
        });
    }

    /**
     * Regla de acceso al panel. Los usuarios sin horario aún pueden entrar
     * para no bloquear cuentas existentes durante la carga inicial de turnos.
     */
    public function canAccessPlatformAt(User $user, ?Carbon $at = null): bool
    {
        return true;
    }

    /** Regla estricta para asignar chats nuevos a agentes de soporte. */
    public function canReceiveNewChatsAt(User $user, ?Carbon $at = null): bool
    {
        return $user->hasRole('soporte')
            && $this->hasConfiguredSchedule($user)
            && $this->isWithinWorkingHoursAt($user, $at);
    }

    /** Alias de negocio: las notificaciones de chats nuevos siguen la asignación. */
    public function canReceiveWorkNotificationsAt(User $user, ?Carbon $at = null): bool
    {
        return $this->canReceiveNewChatsAt($user, $at);
    }

    /** @return Collection<int, User> */
    public function supportAgentsAvailableAt(?Carbon $at = null): Collection
    {
        $at ??= now();

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'soporte'))
            ->with(['role', 'scheduleVersions.shifts'])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user) => $this->canReceiveNewChatsAt($user, $at))
            ->values();
    }

    /**
     * Reconstruye el mes real: cada día con los turnos que de verdad aplicaron,
     * aunque a mitad de mes haya habido un cambio de versión.
     *
     * @return array<string, Collection>
     */
    public function monthlySchedule(User $user, int $year, int $month): array
    {
        $cursor = Carbon::create($year, $month, 1)->startOfDay();
        $end = $cursor->copy()->endOfMonth();

        $days = [];
        for (; $cursor <= $end; $cursor->addDay()) {
            $days[$cursor->toDateString()] = $this->shiftsForDate($user, $cursor->copy());
        }

        return $days;
    }

    /**
     * @param  array<int, array{weekday:int, start_time:string, end_time:string}>  $weeklyPattern
     */
    private function syncShifts(ScheduleVersion $version, array $weeklyPattern): void
    {
        $version->shifts()->createMany($weeklyPattern);
    }
}
