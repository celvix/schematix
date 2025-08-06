<?php

namespace ModelRefactor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ModelRefactor\Services\ModelSnapshotService;
use ModelRefactor\Services\ModelChangeDetector;
use ModelRefactor\Services\CodeRefactorService;
use ModelRefactor\Services\MigrationBuilderService;

class RefactorCommand extends Command
{
    protected $signature = 'model:refactor 
                            {old : Old model class path (e.g. App\\Models\\Student)} 
                            {--new= : New model class path (e.g. App\\Modules\\Students\\Entities\\Student)} 
                            {--dry-run : Don\'t apply changes, just show them}';

    protected $description = 'Refactor model, table, and column references project-wide and generate necessary migrations.';

    public function __construct(
        protected ModelSnapshotService $snapshotService,
        protected ModelChangeDetector $changeDetector,
        protected CodeRefactorService $refactorService,
        protected MigrationBuilderService $migrationBuilder
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $oldClass = $this->argument('old');
        $newClass = $this->option('new') ?? $oldClass;
        $dryRun   = $this->option('dry-run');

        // ✅ Validate new model exists
        if (!class_exists($newClass)) {
            $this->error("❌ New model class [$newClass] does not exist.");
            return;
        }

        // ✅ Load old snapshot from new location: storage/snapshots
        $snapshotFilename = str_replace('\\', '_', $oldClass) . '.json';
        $snapshotPath = storage_path('snapshots/' . $snapshotFilename); // ✅ updated

        if (!File::exists($snapshotPath)) {
            $this->error("❌ Snapshot for old model not found at: $snapshotPath");
            return;
        }

        $oldSnapshot = json_decode(File::get($snapshotPath), true);

        // ✅ Generate new snapshot dynamically
        $newSnapshot = $this->snapshotService->generateSnapshot($newClass);

        // ✅ Detect changes
        $changes = $this->changeDetector->detectChanges($oldSnapshot, $newSnapshot);

        if (empty($changes)) {
            $this->info("✅ No significant model, table, or column changes detected.");
            return;
        }

        $this->info("📦 Detected changes:");
        $this->line(json_encode($changes, JSON_PRETTY_PRINT));

        // ✅ Apply refactoring
        $modified = $this->refactorService->applyChanges($changes, base_path(), $dryRun);
        $this->info(($dryRun ? '🧪 Would modify' : '✏️ Modified') . ' files:');
        foreach ($modified as $file) {
            $this->line(" - " . $file);
        }

        // ✅ Generate migration if not dry-run
        if (!$dryRun) {
            $migrationPath = $this->migrationBuilder->generateMigration($changes);
            if ($migrationPath) {
                $this->info("🛠️  Migration file created: $migrationPath");
            }
        }

        $this->info($dryRun ? "✅ Dry run completed." : "✅ Refactor completed successfully.");
    }
}
