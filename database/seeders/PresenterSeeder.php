<?php

namespace Database\Seeders;

use App\Models\Presenter;
use Illuminate\Database\Seeder;

class PresenterSeeder extends Seeder
{
    public function run(): void
    {
        $presenters = [
            ['name' => 'Bishop Gitaari', 'role' => 'Minister',     'bio' => 'Senior pastor and spiritual leader.', 'email' => 'gitaari@abundanttv.test', 'phone' => '+254712345001'],
            ['name' => 'Jane Wanjiku',   'role' => 'News Anchor',  'bio' => 'Award-winning journalist.',            'email' => 'jane@abundanttv.test',    'phone' => '+254712345002'],
            ['name' => 'DJ Katana',      'role' => 'DJ',           'bio' => 'Mix master of the afternoon drive.',   'email' => 'katana@abundanttv.test',  'phone' => '+254712345003'],
            ['name' => 'Peter Mwangi',   'role' => 'Host',         'bio' => 'Talk show host and producer.',         'email' => 'peter@abundanttv.test',   'phone' => '+254712345004'],
            ['name' => 'Grace Akinyi',   'role' => 'Presenter',    'bio' => 'Gospel show hostess.',                 'email' => 'grace@abundanttv.test',   'phone' => '+254712345005'],
        ];

        foreach ($presenters as $p) {
            Presenter::updateOrCreate(
                ['name' => $p['name']],
                array_merge($p, ['is_active' => true])
            );
        }
    }
}
