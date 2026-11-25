<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Undo what the scanner bot (user 60 "pHqghUme", business 10) did on
 * 11/15-11/16/2026 through pages that weren't locked to our business yet
 * (Sarah, 2026-11-25).
 *
 * 1. Commission payout ledgers: the bot replaced both files with 765 fake $1
 *    payouts, so every real payout was gone and May-October showed as owed
 *    again. Everything through 10/28 was paid on the 10/29 payroll (all
 *    10/15-10/28 commissions were recorded then, and older months earlier),
 *    so each person gets one "paid through 10/28" entry: listing = every
 *    sold line up to 10/28, sales = the daily sales bonus 6/15-10/28.
 *    Bot entries are dropped. Party payouts don't matter: unpaid parties
 *    only carry from 11/01.
 * 2. supplies.json / supply_requests.json: bot junk removed.
 * 3. lfl-store-hours.json: the bot's store 11 removed.
 * 4. Help article renamed "@@r7lbl" -> its real title.
 * 5. Whatnot seller settings: business set back to 1, and a new private link
 *    for Golden (the bot cleared the old one).
 * 6. Unsent "task assigned" texts addressed to the bot account deleted.
 *
 * Every file is copied to erp-data/backups/bot-restore-20261125/ first.
 */
class RestoreDataWipedByScannerBot extends Migration
{
    const PAID_THRU = '2026-10-28';
    const BOT_IDS = [60, 730];

    public function up()
    {
        $disk = Storage::disk('local');
        $bk = 'backups/bot-restore-20261125/';
        foreach (['listing-commission-payouts.json', 'sales-commission-payouts.json', 'supplies.json',
                  'supply_requests.json', 'lfl-store-hours.json', 'help_custom_articles.json',
                  'whatnot-seller/settings.json'] as $f) {
            if ($disk->exists($f) && !$disk->exists($bk . str_replace('/', '__', $f))) {
                $disk->put($bk . str_replace('/', '__', $f), $disk->get($f));
            }
        }
        $read = function ($f) use ($disk) {
            if (!$disk->exists($f)) { return []; }
            $d = json_decode($disk->get($f), true);
            return is_array($d) ? $d : [];
        };
        $write = function ($f, $d) use ($disk) {
            $disk->put($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        };
        $isBot = function ($p) {
            return in_array((int) ($p['marked_by'] ?? 0), self::BOT_IDS, true)
                || in_array((int) ($p['user_id'] ?? 0), self::BOT_IDS, true);
        };
        $businessId = 1;
        $names = DB::table('users')->where('business_id', $businessId)->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
        $nm = function ($uid) use ($names) {
            $u = $names[$uid] ?? null;
            return $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) : ('User #' . $uid);
        };
        $now = now()->toDateTimeString();
        $note = 'Restored 11/25/26: paid through 10/28/26 on the 10/29 payroll (scanner bot wiped the ledger 11/16)';

        // ---- 1a. Listing ledger ------------------------------------------
        $lc = app(\App\Http\Controllers\ListingCommissionController::class);
        $owned = new ReflectionMethod($lc, 'ownedSoldLines');
        $owned->setAccessible(true);
        $listing = array_values(array_filter($read('listing-commission-payouts.json'), function ($p) use ($isBot) { return !$isBot($p); }));
        $already = [];
        foreach ($listing as $p) { foreach (($p['line_ids'] ?? []) as $lid) { $already[(int) $lid] = true; } }
        $byUser = [];
        foreach ($owned->invoke($lc, $businessId, $lc::DEFAULT_FROM, []) as $r) {
            if (substr((string) $r->transaction_date, 0, 10) > self::PAID_THRU) { continue; }
            if (isset($already[(int) $r->line_id])) { continue; }
            $uid = (int) $r->user_id;
            $byUser[$uid]['ids'][] = (int) $r->line_id;
            $byUser[$uid]['amt'] = ($byUser[$uid]['amt'] ?? 0) + (float) $r->sale_amount * $lc::RATE;
        }
        $lTotal = 0;
        foreach ($byUser as $uid => $b) {
            $amt = round($b['amt'], 2);
            if ($amt <= 0) { continue; }
            $listing[] = [
                'id' => bin2hex(random_bytes(8)), 'user_id' => $uid, 'name' => $nm($uid),
                'count' => count($b['ids']), 'amount' => $amt, 'line_ids' => $b['ids'],
                'from_date' => $lc::DEFAULT_FROM, 'to_date' => self::PAID_THRU,
                'manual' => false, 'note' => $note, 'marked_by' => 8, 'marked_at' => $now,
            ];
            $lTotal += $amt;
            echo "listing paid thru 10/28: " . $nm($uid) . " $" . number_format($amt, 2) . "\n";
        }
        $write('listing-commission-payouts.json', $listing);

        // ---- 1b. Sales ledger --------------------------------------------
        $sales = array_values(array_filter($read('sales-commission-payouts.json'), function ($p) use ($isBot) { return !$isBot($p); }));
        $data = app(\App\Http\Controllers\ReportController::class)->buildDailyEarnings(
            $businessId, $lc::SALES_BONUS_FROM . ' 00:00:00', self::PAID_THRU . ' 23:59:59', null, true);
        $sb = [];
        foreach (($data['days'] ?? []) as $date => $rows) {
            foreach ($rows as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) { $sb[$uid] = ($sb[$uid] ?? 0) + (float) ($r['sales_bonus'] ?? 0); }
            }
        }
        $sTotal = 0;
        foreach ($sb as $uid => $amt) {
            $amt = round($amt, 2);
            $sales[] = [
                'id' => bin2hex(random_bytes(8)), 'user_id' => $uid, 'name' => $nm($uid),
                'amount' => $amt, 'from_date' => $lc::SALES_BONUS_FROM, 'to_date' => self::PAID_THRU,
                'manual' => true, 'note' => $note, 'marked_by' => 8, 'marked_at' => $now,
            ];
            $sTotal += $amt;
            echo "sales paid thru 10/28: " . $nm($uid) . " $" . number_format($amt, 2) . "\n";
        }
        $write('sales-commission-payouts.json', $sales);
        echo "listing restored $" . number_format($lTotal, 2) . ", sales restored $" . number_format($sTotal, 2) . "\n";

        // ---- 2. Supplies ---------------------------------------------------
        $clean = function ($rows) {
            return array_values(array_filter($rows, function ($r) {
                $s = json_encode($r);
                return stripos($s, 'pHqghUme') === false && (int) ($r['requested_by_id'] ?? 0) !== 60
                    && (string) ($r['location_id'] ?? '') !== '11';
            }));
        };
        $write('supplies.json', $clean($read('supplies.json')));
        $write('supply_requests.json', $clean($read('supply_requests.json')));

        // ---- 3. LFL store hours ---------------------------------------------
        $h = $read('lfl-store-hours.json');
        unset($h['11'], $h[11]);
        $write('lfl-store-hours.json', (object) $h);

        // ---- 4. Help article title ---------------------------------------------
        $arts = $read('help_custom_articles.json');
        foreach ($arts as &$a) {
            if (($a['title'] ?? '') === '@@r7lbl') { $a['title'] = 'Grading Records and CDs (Goldmine Standard)'; }
        }
        unset($a);
        $write('help_custom_articles.json', $arts);

        // ---- 5. Whatnot seller settings ------------------------------------------
        $ws = $read('whatnot-seller/settings.json');
        if ($ws) {
            $ws['business_id'] = $businessId;
            if (empty($ws['token'])) { $ws['token'] = bin2hex(random_bytes(24)); }
            $write('whatnot-seller/settings.json', $ws);
            echo "whatnot seller link: " . url('/whatnot-seller/' . $ws['token']) . "\n";
        }

        // ---- 6. Unsent texts to the bot ---------------------------------------
        $outside = DB::table('users')->where('business_id', '<>', $businessId)->pluck('id')->all();
        $n = DB::table('pending_assignment_texts')->whereIn('user_id', $outside)->whereNull('sent_at')->delete();
        echo "deleted $n unsent texts to outside accounts\n";
    }

    public function down()
    {
        // Backups are in erp-data/backups/bot-restore-20261125/.
    }
}
