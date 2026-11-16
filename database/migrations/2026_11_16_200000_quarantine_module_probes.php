<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * 2026-11-16: files written into Modules/ on 8/11/26 3-4am PT through the
 * unprotected upload-module endpoint (now disabled). Move them (and the
 * stray bootstrap cache file) to storage/app/quarantine; nothing is deleted.
 */
class QuarantineModuleProbes extends Migration
{
    public function up()
    {
        $dest = storage_path('app/quarantine/modules-' . date('Ymd-His'));
        @mkdir($dest, 0750, true);
        $moved = [];
        foreach (['Modules/ttB731.php', 'Modules/ttC2.php', 'Modules/VinylImportHelper', 'bootstrap/cache/vinyl_import_helper_module.php'] as $rel) {
            $src = base_path($rel);
            if (file_exists($src) && @rename($src, $dest . '/' . str_replace('/', '__', $rel))) {
                $moved[] = $rel;
            }
        }
        Log::warning('quarantine_module_probes moved: ' . implode(', ', $moved));
        echo 'quarantined: ' . ($moved ? implode(', ', $moved) : 'none') . "\n";
    }

    public function down()
    {
    }
}
