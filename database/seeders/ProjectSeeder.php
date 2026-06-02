<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Support\ProjectCatalog;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ProjectCatalog::options() as $p) {
            Project::updateOrCreate(
                ['id' => $p['id']],
                [
                    'code' => $p['code'],
                    'name' => $p['name'],
                    'color' => $p['color'],
                    'is_active' => true,
                    'sort_order' => $p['id'],
                ],
            );
        }
    }
}
