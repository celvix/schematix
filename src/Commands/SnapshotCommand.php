<?php

namespace ModelRefactor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ModelRefactor\Services\ModelSnapshotService;

class SnapshotCommand extends Command
{
    protected $signature = 'model:refactor:snapshot 
                            {model : The fully-qualified class name of the model}';

    protected $description = 'Generates a snapshot of the given model\'s metadata for future change detection.';

    public function handle(ModelSnapshotService $snapshotService): void
    {
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Model class [$modelClass] does not exist.");
            return;
        }

        $snapshot = $snapshotService->generateSnapshot($modelClass);

        $snapshotDir = storage_path('snapshots'); // ✅ updated path
        $snapshotPath = $snapshotDir . '/' . str_replace('\\', '_', $modelClass) . '.json';

        File::put($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT));

        $this->info("Snapshot saved to: $snapshotPath");
    }
}
