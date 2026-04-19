<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationSafetyTest extends TestCase
{
    /**
     * Scan migration files for destructive operations.
     * Dangerous operations must explicitly include "// SAFETY: approved" on the same line or line before
     * to prevent accidental drops on production.
     */
    public function test_migrations_are_safe_from_accidental_drops(): void
    {
        $migrationPaths = [
            database_path('migrations/public'),
            database_path('migrations/tenant'),
        ];

        $dangerousPatterns = [
            '->dropColumn(',
            'Schema::drop(',
            'Schema::dropIfExists(',
            '->renameColumn(',
        ];

        $violations = [];

        foreach ($migrationPaths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            $files = File::files($path);

            foreach ($files as $file) {
                // Ignore initial creation migrations which are mostly safe setup
                if (Str::contains($file->getFilename(), 'create_')) {
                    continue;
                }

                $content = File::get($file->getPathname());
                $lines = explode("\n", $content);

                foreach ($lines as $index => $line) {
                    foreach ($dangerousPatterns as $pattern) {
                        if (Str::contains($line, $pattern)) {
                            // Check for safety approval on the same line
                            if (Str::contains(strtolower($line), 'safety: approved')) {
                                continue; // Approved inline
                            }

                            // Check previous line for approval
                            if ($index > 0 && Str::contains(strtolower($lines[$index - 1]), 'safety: approved')) {
                                continue; // Approved on previous line
                            }

                            // Exclude down() methods because drops are expected there
                            if ($this->isInDownMethod($lines, $index)) {
                                continue;
                            }

                            $violations[] = sprintf(
                                "File: %s | Line %d: Found dangerous operation '%s' without '// SAFETY: approved' comment. If this is intentional, add the comment to bypass this check.",
                                $file->getFilename(),
                                $index + 1,
                                trim($line)
                            );
                        }
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Potentially unsafe database migrations found:\n".implode("\n", $violations));
    }

    private function isInDownMethod(array $lines, int $targetIndex): bool
    {
        $downStart = -1;
        $upStart = -1;

        for ($i = 0; $i < count($lines); $i++) {
            if (Str::contains($lines[$i], 'public function down()')) {
                $downStart = $i;
            } elseif (Str::contains($lines[$i], 'public function up()')) {
                $upStart = $i;
            }
        }

        if ($downStart === -1) {
            return false;
        }

        // If 'down' comes after 'up', everything after 'down' is in down (usually).
        if ($downStart > $upStart && $targetIndex > $downStart) {
            return true;
        }

        // If target is between down and up
        if ($downStart < $upStart && $targetIndex > $downStart && $targetIndex < $upStart) {
            return true;
        }

        return false;
    }
}
