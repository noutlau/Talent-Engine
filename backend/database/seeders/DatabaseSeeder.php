<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SkillSeeder::class);

        User::updateOrCreate(
            ['email' => config('talent.admin.email')],
            ['name' => 'Responsable SKULLVI', 'password' => config('talent.admin.password')],
        );

        $this->call(DemoCandidateSeeder::class);
    }
}
