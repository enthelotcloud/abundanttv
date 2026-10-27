<?php

namespace Database\Seeders;

use App\Models\Presenter;
use App\Models\Schedule;
use App\Models\Show;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $presenters = Presenter::pluck('id', 'name');
        $shows      = Show::pluck('id', 'title');

        $slots = [
            // ── Morning ─────────────────────────────────────
            ['show' => 'Morning Devotion',    'start' => '06:00', 'end' => '08:00', 'presenters' => [['Bishop Gitaari', 'Minister']]],
            ['show' => 'Morning Praise',      'start' => '08:00', 'end' => '10:00', 'presenters' => [['Grace Akinyi', 'Host']]],
            ['show' => 'Reality Check',       'start' => '10:10', 'end' => '12:10', 'presenters' => [['Peter Mwangi', 'Host']]],   // 10 min gap after Morning Praise
            ['show' => 'Sunday Service',      'start' => '10:10', 'end' => '12:10', 'presenters' => [['Bishop Gitaari', 'Minister']]],

            // ── Midday (10 min gap before 3pm) ──────────────
            ['show' => 'News Block',          'start' => '12:20', 'end' => '14:20', 'presenters' => [['Jane Wanjiku', 'Anchor']]],   // 10 min gap
            ['show' => 'Midday Magazine',     'start' => '13:00', 'end' => '15:00', 'presenters' => [['Peter Mwangi', 'Host'], ['Grace Akinyi', 'Co-host']]],

            // ── THE 3-5 PM PRESENTATION WINDOW ─────────────
            ['show' => 'The Afternoon Drive', 'start' => '15:00', 'end' => '17:00', 'presenters' => [['DJ Katana', 'DJ'], ['Grace Akinyi', 'Co-host']]],
            ['show' => 'Weekend Vibes',       'start' => '15:00', 'end' => '17:00', 'presenters' => [['DJ Katana', 'DJ']]],

            // ── Evening (10 min gaps) ──────────────────────
            ['show' => 'Evening Gospel',      'start' => '17:10', 'end' => '19:10', 'presenters' => [['Bishop Gitaari', 'Minister'], ['Grace Akinyi', 'Host']]], // 10 min gap
            ['show' => 'Prime Time Talk',     'start' => '19:20', 'end' => '21:20', 'presenters' => [['Peter Mwangi', 'Host'], ['Jane Wanjiku', 'Co-host']]],   // 10 min gap
        ];

        foreach ($slots as $slot) {
            $showId = $shows[$slot['show']] ?? null;
            if (! $showId) continue;

            $schedule = Schedule::updateOrCreate(
                ['show_id' => $showId, 'start_time' => $slot['start'] . ':00'],
                ['end_time' => $slot['end'] . ':00', 'is_active' => true]
            );

            $sync = [];
            foreach ($slot['presenters'] as $i => [$name, $role]) {
                $presenterId = $presenters[$name] ?? null;
                if (! $presenterId) continue;
                $sync[$presenterId] = ['role' => $role, 'sort_order' => $i];
            }
            $schedule->presenters()->sync($sync);
        }
    }
}
