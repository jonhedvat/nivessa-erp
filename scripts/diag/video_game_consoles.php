<?php
// File Video Games products under their console subcategory when the name or
// description says the console. Dry run unless COMMIT=1. Writes an undo log.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$commit = getenv('COMMIT') === '1';
$parent = DB::table('categories')->where('parent_id', 0)->where('name', 'Video Games')->whereNull('deleted_at')->first();
if (!$parent) { echo "NO_PARENT\n"; exit; }
$subs = DB::table('categories')->where('parent_id', $parent->id)->whereNull('deleted_at')->pluck('id', 'name');
echo "PARENT #{$parent->id} subs=" . count($subs) . "\n";

// Most specific first.
$rules = [
  'Xbox Series X' => '/xbox series|\bseries x\b/', 'Xbox One' => '/xbox\s*one|\bxb1\b/', 'Xbox 360' => '/xbox\s*360|\bx360\b/',
  'Xbox' => '/\bxbox\b/',
  'PS5' => '/\bps ?5\b|playstation ?5/', 'PS4' => '/\bps ?4\b|playstation ?4/', 'PS3' => '/\bps ?3\b|playstation ?3/',
  'PS2' => '/\bps ?2\b|playstation ?2/', 'PS Vita' => '/\bvita\b/', 'PSP' => '/\bpsp\b/',
  'PlayStation' => '/\bps ?1\b|\bpsx\b|\bplaystation\b/',
  'Nintendo Switch' => '/nintendo switch|\bswitch\)?\s*$/', 'Nintendo 3DS' => '/\b3ds\b/', 'Nintendo DS' => '/\bnds\b|nintendo ds\b|\bds\)?\s*$/',
  'Game Boy Advance' => '/\bgba\b|game ?boy advance/', 'Game Boy' => '/game ?boy|\bgbc\b/',
  'Wii U' => '/\bwii ?u\b/', 'Wii' => '/\bwii\b/', 'GameCube' => '/game ?cube|\bngc\b/',
  'Nintendo 64' => '/\bn64\b|nintendo 64/', 'Super Nintendo' => '/\bsnes\b|super nintendo/',
  'NES' => '/\bnes\b|nintendo entertainment system/',
  'Sega Genesis' => '/\bgenesis\b/', 'Sega Saturn' => '/\bsaturn\b/', 'Sega Dreamcast' => '/dreamcast/',
  'Atari' => '/\batari\b/', 'PC' => '/\bpc\b|cd-rom|\bwindows\b|\bpc game/',
];

$other = $subs['Other'] ?? null;
$rows = DB::table('products')->where('category_id', $parent->id)
  ->get(['id', 'name', 'sub_category_id', 'product_description', 'is_inactive']);
$undo = []; $counts = []; $left = [];
foreach ($rows as $r) {
  $hay = mb_strtolower($r->name . ' ' . strip_tags((string) $r->product_description));
  $hit = null;
  // 11/23 batch of NES-only classics entered without the console.
  if (in_array((int) $r->id, [170072, 170073, 170074, 170075, 170076, 170077, 170078, 170079, 170080], true)) $hit = 'NES';
  if (!$hit) foreach ($rules as $console => $re) { if (preg_match($re, $hay)) { $hit = $console; break; } }
  if (!$hit || empty($subs[$hit])) { $left[] = $r; continue; }
  if ((int) $r->sub_category_id === (int) $subs[$hit]) continue;
  // Only move games that are unfiled or in "Other".
  if (!empty($r->sub_category_id) && (int) $r->sub_category_id !== (int) $other) { $left[] = $r; continue; }
  $counts[$hit] = ($counts[$hit] ?? 0) + 1;
  echo "MOVE\t#{$r->id}\t" . mb_substr($r->name, 0, 60) . "\t-> {$hit}\n";
  $undo[] = ['id' => $r->id, 'from' => $r->sub_category_id, 'to' => $subs[$hit]];
  if ($commit) DB::table('products')->where('id', $r->id)->update(['sub_category_id' => $subs[$hit]]);
}
foreach ($left as $r) echo "LEFT\t#{$r->id}\t" . mb_substr($r->name, 0, 70) . ($r->is_inactive ? "\t(inactive)" : '') . "\n";
echo "COUNTS " . json_encode($counts) . "\n";
echo "MOVED " . count($undo) . " LEFT " . count($left) . " COMMIT " . ($commit ? 'yes' : 'no') . "\n";
if ($commit && $undo) {
  $f = storage_path('app/undo_video_game_consoles_' . date('Ymd_His') . '.json');
  file_put_contents($f, json_encode($undo));
  echo "UNDO $f\n";
}
