<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Jobs\ScoreProject;
use App\Models\Project;
use Illuminate\Console\Command;

/** Chaque nuit (routes/console.php) et bouton « Recalculer » : score de priorité des projets non terminés. */
class ScoreProjects extends Command
{
    protected $signature = 'projects:score {--sync : calcule tout de suite au lieu de passer par la queue}';

    protected $description = 'Calcule le score de priorité (0-100) et son explication pour chaque projet';

    public function handle(): int
    {
        $projects = Project::query()->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Failed->value])->get();

        foreach ($projects as $project) {
            $this->option('sync') ? ScoreProject::dispatchSync($project) : ScoreProject::dispatch($project);
        }

        $this->info("{$projects->count()} projet(s) ".($this->option('sync') ? 'évalués.' : 'mis en file d\'attente.'));

        return self::SUCCESS;
    }
}
