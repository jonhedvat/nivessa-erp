<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 2026-11-16: a ".phtml" file was uploaded as a profile photo into
 * public/uploads/media. Move every uploaded file whose extension can run
 * as code out of the public folder into storage/app/quarantine (kept, not
 * deleted, so it can be inspected), and drop the media rows that point at
 * them so no page links them.
 */
class QuarantineScriptUploads extends Migration
{
    public function up()
    {
        $bad = '/\.(php\d*|phtml|pht|phar|phps|inc|cgi|pl|py|sh|asp|aspx|jsp)$/i';
        $dest = storage_path('app/quarantine/uploads-' . date('Ymd-His'));
        $moved = [];

        $root = public_path('uploads');
        if (is_dir($root)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile() || !preg_match($bad, $f->getFilename())) {
                    continue;
                }
                $rel = ltrim(substr($f->getPathname(), strlen($root)), '/');
                $target = $dest . '/' . $rel;
                if (!is_dir(dirname($target))) {
                    @mkdir(dirname($target), 0750, true);
                }
                if (@rename($f->getPathname(), $target)) {
                    $moved[] = $rel;
                }
            }
        }

        $rows = DB::table('media')->get(['id', 'file_name'])->filter(function ($m) use ($bad) {
            return preg_match($bad, (string) $m->file_name);
        });
        if ($rows->count()) {
            if (!is_dir($dest)) {
                @mkdir($dest, 0750, true);
            }
            file_put_contents($dest . '/media-rows.json', json_encode(DB::table('media')->whereIn('id', $rows->pluck('id'))->get(), JSON_PRETTY_PRINT));
            DB::table('media')->whereIn('id', $rows->pluck('id'))->delete();
        }

        Log::warning('quarantine_script_uploads: moved ' . count($moved) . ' file(s), removed ' . $rows->count() . ' media row(s): ' . implode(', ', $moved));
        echo 'quarantined files: ' . (count($moved) ? implode(', ', $moved) : 'none') . "\n";
        echo 'media rows removed: ' . $rows->count() . "\n";
    }

    public function down()
    {
        // Intentionally not restored: these files must not be public.
    }
}
