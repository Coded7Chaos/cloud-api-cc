<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScheduleService::class);
    }

    /** Patrón Lun–Sáb con la misma hora de fin. */
    private function weeklyPattern(string $end): array
    {
        return collect(range(1, 6))
            ->map(fn (int $weekday) => ['weekday' => $weekday, 'start_time' => '07:00', 'end_time' => $end])
            ->all();
    }

    public function test_create_schedule_persists_open_version_with_shifts(): void
    {
        $user = User::factory()->create();

        $version = $this->service->createSchedule($user, $this->weeklyPattern('22:00'), Carbon::create(2026, 6, 1));

        $this->assertNull($version->effective_to);          // versión abierta/vigente
        $this->assertCount(6, $version->shifts);            // Lun–Sáb, sin domingo
    }

    public function test_change_schedule_splits_the_timeline_without_touching_the_past(): void
    {
        $user = User::factory()->create();
        $this->service->createSchedule($user, $this->weeklyPattern('22:00'), Carbon::create(2026, 6, 1));

        // A mitad de mes cambia la hora de salida a las 18:00.
        $this->service->changeSchedule($user, $this->weeklyPattern('18:00'), Carbon::create(2026, 6, 15));

        // Quedan dos versiones: la vieja cortada el 15, y la nueva abierta.
        $versions = $user->scheduleVersions()->orderBy('effective_from')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('2026-06-15', $versions[0]->effective_to->toDateString());
        $this->assertNull($versions[1]->effective_to);

        // Fechas robustas independientes del calendario.
        $jun = fn (int $d) => Carbon::create(2026, 6, $d);
        $sunday = collect(range(1, 7))->map($jun)->first(fn (Carbon $d) => $d->isSunday());
        $workdayBefore = collect(range(1, 14))->map($jun)->first(fn (Carbon $d) => ! $d->isSunday());
        $workdayAfter = collect(range(15, 28))->map($jun)->first(fn (Carbon $d) => ! $d->isSunday());

        // Antes del cambio sigue el horario viejo; después, el nuevo.
        $this->assertSame('22:00', substr($this->service->shiftsForDate($user, $workdayBefore)->first()->end_time, 0, 5));
        $this->assertSame('18:00', substr($this->service->shiftsForDate($user, $workdayAfter)->first()->end_time, 0, 5));

        // Los domingos no tienen turnos.
        $this->assertTrue($this->service->shiftsForDate($user, $sunday)->isEmpty());
    }

    public function test_changing_on_the_same_start_date_rewrites_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $this->service->createSchedule($user, $this->weeklyPattern('22:00'), Carbon::create(2026, 6, 1));

        // Editar el mismo día en que empieza la versión no debe crear una nueva.
        $this->service->changeSchedule($user, $this->weeklyPattern('20:00'), Carbon::create(2026, 6, 1));

        $this->assertCount(1, $user->scheduleVersions()->get());
        $workday = collect(range(1, 7))->map(fn ($d) => Carbon::create(2026, 6, $d))
            ->first(fn (Carbon $d) => ! $d->isSunday());
        $this->assertSame('20:00', substr($this->service->shiftsForDate($user, $workday)->first()->end_time, 0, 5));
    }

    public function test_monthly_schedule_returns_a_collection_per_day(): void
    {
        $user = User::factory()->create();
        $this->service->createSchedule($user, $this->weeklyPattern('22:00'), Carbon::create(2026, 6, 1));

        $month = $this->service->monthlySchedule($user, 2026, 6);

        $this->assertCount(30, $month); // junio tiene 30 días
        $this->assertInstanceOf(Collection::class, $month['2026-06-01']);
    }
}
