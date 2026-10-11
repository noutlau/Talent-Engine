<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /** Compétences recherchées (rapportent des points) et autres (affichées au recruteur, sans points). */
    public const SKILLS = [
        'HTML/CSS' => true,
        'JavaScript' => true,
        'TypeScript' => true,
        'PHP' => true,
        'Laravel' => true,
        'React' => true,
        'Vue.js' => true,
        'Node.js' => true,
        'Python' => true,
        'SQL' => true,
        'Git' => true,
        'API REST' => true,
        'Tests automatisés' => true,
        'Docker' => true,
        'WordPress' => false,
        'Figma' => false,
        'Flutter' => false,
        'Excel / Power BI' => false,
    ];

    public function run(): void
    {
        foreach (self::SKILLS as $name => $isSought) {
            Skill::updateOrCreate(['name' => $name], ['is_sought' => $isSought]);
        }
    }
}
