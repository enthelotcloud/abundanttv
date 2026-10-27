<?php

namespace Database\Seeders;

use App\Models\Show;
use Illuminate\Database\Seeder;

class ShowSeeder extends Seeder
{
    public function run(): void
    {
        $shows = [
            [
                'title'            => 'Morning Devotion',
                'category'         => 'worship',
                'description'      => 'Start your Sunday with prayer and the Word.',
                'duration_minutes' => 120,
                'airing_days'      => ['sunday'],
            ],
            [
                'title'            => 'Morning Praise',
                'category'         => 'worship',
                'description'      => 'Gospel music to start your day.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday','saturday'],
            ],
            [
                'title'            => 'Reality Check',
                'category'         => 'talk',
                'description'      => 'Youth discussion on real-life topics.',
                'duration_minutes' => 120,
                'airing_days'      => ['saturday'],
            ],
            [
                'title'            => 'Sunday Service',
                'category'         => 'worship',
                'description'      => 'Live Sunday worship service.',
                'duration_minutes' => 120,
                'airing_days'      => ['sunday'],
            ],
            [
                'title'            => 'News Block',
                'category'         => 'news',
                'description'      => 'Headlines from Kenya and the world.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
            ],
            [
                'title'            => 'Midday Magazine',
                'category'         => 'talk',
                'description'      => 'Lifestyle, interviews, and community news.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday'],
            ],
            [
                'title'            => 'The Afternoon Drive',
                'category'         => 'music',
                'description'      => 'Your ride home with the best mix and lively talk.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday'],
            ],
            [
                'title'            => 'Weekend Vibes',
                'category'         => 'music',
                'description'      => 'Weekend playlist and chill conversations.',
                'duration_minutes' => 120,
                'airing_days'      => ['saturday','sunday'],
            ],
            [
                'title'            => 'Evening Gospel',
                'category'         => 'worship',
                'description'      => 'Praise and worship to close your day.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
            ],
            [
                'title'            => 'Prime Time Talk',
                'category'         => 'talk',
                'description'      => 'Late-night interviews and panel discussions.',
                'duration_minutes' => 120,
                'airing_days'      => ['monday','tuesday','wednesday','thursday','friday'],
            ],
        ];

        foreach ($shows as $s) {
            Show::updateOrCreate(
                ['title' => $s['title']],
                array_merge($s, ['is_active' => true])
            );
        }
    }
}
