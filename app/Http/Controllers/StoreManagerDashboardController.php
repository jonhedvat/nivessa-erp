<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manager Dashboard (Jon 2026-11-15): one read-only page for the two store
 * managers. Zakary sees Pico, Luis sees Hollywood, never the other store.
 * Admins and owners can open it too and switch stores.
 *
 * "Priced" = items put into stock at this store (purchase / opening-stock
 * lines, the same definition as the Employee Listing Report), credited to
 * whoever entered them and timed by when the line was saved.
 *
 * Every section is built on its own and failure-isolated: a section that
 * throws shows "couldn't load" instead of taking the page down.
 */
class StoreManagerDashboardController extends Controller
{
    const STORES = ['pico' => 'Pico', 'hollywood' => 'Hollywood'];
    const DAY_CHOICES = [7, 14, 30, 90];
    const LATE_MIN = 10;          // opened the ERP more than this many minutes after shift start
    const LOW_WEEKS = 8;          // under this many weeks of stock = low
    const LOW_MIN_SOLD = 13;      // needs ~1 sale a week over 90 days to be flagged
    const CACHE_SECONDS = 600;
    const DISCOGS_SHIPPER = 'nick'; // first name credited with Discogs orders shipped outside the ERP

    /** Admins and the owners (they may switch stores). */
    public static function isOwnerOrAdmin()
    {
        $u = auth()->user();
        if (!$u) {
            return false;
        }
        return $u->hasRole('Admin#' . $u->business_id)
            || in_array(strtolower(trim((string) $u->email)), TeamProgressController::OWNER_EMAILS, true);
    }

    /** Store key the logged-in manager is locked to, or null if they aren't a store manager. */
    public static function managerStore()
    {
        $key = ManagerChecklistController::currentManagerKey();
        if (!$key) {
            return null;
        }
        $store = strtolower(ManagerChecklistController::MANAGER_KEYS[$key]['store'] ?? '');
        return isset(self::STORES[$store]) ? $store : null;
    }

    public static function canView()
    {
        return self::managerStore() !== null || self::isOwnerOrAdmin();
    }

    public function index(Request $request)
    {
        if (!self::canView()) {
            abort(403, 'The Manager Dashboard is for store managers only.');
        }
        $business_id = (int) $request->session()->get('user.business_id');

        // A store manager only ever gets their own store, whatever the URL says.
        $locked = self::managerStore();
        $store = $locked ?: (string) $request->query('store', 'pico');
        if (!isset(self::STORES[$store])) {
            $store = 'pico';
        }
        $canSwitch = $locked === null;

        $days = (int) $request->query('days', 14);
        if (!in_array($days, self::DAY_CHOICES, true)) {
            $days = 14;
        }
        $today = Carbon::today();
        $from = $today->copy()->subDays($days - 1);
        $fromStr = $from->format('Y-m-d') . ' 00:00:00';

        $location = null;
        foreach (DB::table('business_locations')->where('business_id', $business_id)->get() as $l) {
            if (stripos($l->name, $store) !== false) {
                $location = $l;
                break;
            }
        }
        if (!$location) {
            abort(404, 'Store location not found.');
        }
        $loc = (int) $location->id;

        $errors = [];
        $safe = function ($name, $fn, $default = null) use (&$errors) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                \Log::warning('Manager dashboard section failed: ' . $name . ' - ' . $e->getMessage());
                $errors[$name] = true;
                return $default;
            }
        };

        $roster = $safe('roster', function () use ($business_id, $store, $loc, $days) {
            return $this->roster($business_id, $store, $loc, max(60, $days));
        }, []);
        $ids = array_keys($roster);

        $employeeId = (int) $request->query('employee', 0);
        if (!isset($roster[$employeeId])) {
            $employeeId = 0;
        }

        $ck = "mgr_dash:{$business_id}:{$loc}:";
        $ttl = self::CACHE_SECONDS;

        // 1. Priced all time, by person and format.
        $priced = $safe('priced', function () use ($ck, $ttl, $business_id, $loc, $roster) {
            return Cache::remember($ck . 'priced:' . md5(implode(',', array_keys($roster))), $ttl, function () use ($business_id, $loc, $roster) {
                return $this->pricedAllTime($business_id, $loc, $roster);
            });
        }, ['people' => [], 'formats' => [], 'total' => 0]);

        // 1b. Sold % and days to sell, by genre.
        $genres = $safe('genres', function () use ($ck, $ttl, $business_id, $loc, $employeeId) {
            return Cache::remember($ck . 'genres:' . $employeeId, $ttl, function () use ($business_id, $loc, $employeeId) {
                return $this->genreConversion($business_id, $loc, $employeeId);
            });
        }, []);

        // 2. Customer signups all time.
        $signups = $safe('signups', function () use ($ck, $ttl, $business_id, $ids) {
            return Cache::remember($ck . 'signups:' . md5(implode(',', $ids)), $ttl, function () use ($business_id, $ids) {
                return $this->signupsAllTime($business_id, $ids);
            });
        }, []);

        // 2 + 3. Shifts: length, priced and signups in each.
        $shiftData = $safe('shifts', function () use ($business_id, $loc, $roster, $fromStr) {
            return $this->shifts($business_id, $loc, $roster, $fromStr);
        }, ['rows' => [], 'by_user' => []]);

        // 4. Tasks done / in progress.
        $tasks = $safe('tasks', function () use ($business_id, $store, $roster, $from) {
            return $this->tasks($business_id, $store, $roster, $from);
        }, ['by_user' => [], 'done' => 0, 'in_progress' => 0, 'not_started' => 0, 'in_progress_list' => []]);

        // 5. Opened the ERP late.
        $late = $safe('late', function () use ($store, $roster, $from) {
            return $this->lateOpens($store, $roster, $from);
        }, ['by_user' => [], 'rows' => []]);

        // 6. "I did it" vs "I didn't do it" at register close.
        $checkins = $safe('checkins', function () use ($business_id, $loc, $ids, $fromStr) {
            return $this->checkins($business_id, $loc, $ids, $fromStr);
        }, ['by_user' => [], 'not_done' => []]);

        // 7. Website orders not shipped.
        $web = $safe('web', function () use ($store) {
            $all = Cache::remember('mgr_dash:web_unshipped', 300, function () {
                return app(WebsiteOrdersController::class)->unshippedByStore();
            });
            if ($all === null) {
                Cache::forget('mgr_dash:web_unshipped');
                return null;
            }
            return $all[$store] ?? null;
        });

        // 8. Items priced at the store each day.
        $daily = $safe('daily', function () use ($business_id, $loc, $roster, $from, $today) {
            return $this->dailyPriced($business_id, $loc, $roster, $from, $today);
        }, []);

        // 9. Categories running low.
        $low = $safe('low', function () use ($ck, $ttl, $business_id, $loc) {
            return Cache::remember($ck . 'low', $ttl, function () use ($business_id, $loc) {
                return $this->lowStock($business_id, $loc);
            });
        }, []);

        // 10. Items whose stock a shelf count changed, per person.
        $shelf = $safe('shelf', function () use ($business_id, $location, $roster, $fromStr) {
            return $this->shelfCountChanges($business_id, $location, $roster, $fromStr);
        }, []);

        // 11. Website + Discogs items shipped, per person.
        $shipped = $safe('shipped', function () use ($roster, $from) {
            $all = Cache::remember('mgr_dash:shipped_by:' . $from->format('Y-m-d'), 300, function () use ($from) {
                return app(WebsiteOrdersController::class)->shippedByEmployee($from->format('Y-m-d'));
            });
            if ($all === null) {
                Cache::forget('mgr_dash:shipped_by:' . $from->format('Y-m-d'));
                return null;
            }
            $byName = [];
            foreach ($all['people'] as $name => $row) {
                $byName[mb_strtolower(trim((string) $name))] = $row;
            }
            $out = [];
            foreach ($roster as $uid => $name) {
                $row = $byName[mb_strtolower(trim($name))] ?? null;
                if ($row) {
                    $out[$uid] = ['web' => (int) ($row['web_items'] ?? 0), 'discogs' => (int) ($row['discogs_items'] ?? 0)];
                }
            }
            // Discogs orders marked shipped on Discogs.com carry no name: they're Nick's (Jon 11/16).
            $unnamed = (int) ($all['unnamed']['discogs_items'] ?? 0);
            if ($unnamed > 0) {
                foreach ($roster as $uid => $name) {
                    if (mb_strtolower(strtok(trim($name), ' ')) === self::DISCOGS_SHIPPER) {
                        $out[$uid] = ($out[$uid] ?? ['web' => 0, 'discogs' => 0]);
                        $out[$uid]['discogs'] += $unnamed;
                        break;
                    }
                }
            }
            return $out;
        });

        // One row per employee for the top table.
        $people = [];
        foreach ($roster as $uid => $name) {
            $sh = $shiftData['by_user'][$uid] ?? null;
            $people[$uid] = [
                'name' => $name,
                'priced_all' => (int) ($priced['people'][$uid]['total'] ?? 0),
                'signups_all' => (int) ($signups[$uid] ?? 0),
                'shifts' => $sh ? $sh['shifts'] : 0,
                'hours' => $sh ? $sh['hours'] : 0,
                'priced' => $sh ? $sh['priced'] : 0,
                'signups' => $sh ? $sh['signups'] : 0,
                'tasks_done' => (int) ($tasks['by_user'][$uid]['done'] ?? 0),
                'tasks_in_progress' => (int) ($tasks['by_user'][$uid]['in_progress'] ?? 0),
                'late' => (int) ($late['by_user'][$uid]['late'] ?? 0),
                'scheduled' => (int) ($late['by_user'][$uid]['scheduled'] ?? 0),
                'said_done' => (int) ($checkins['by_user'][$uid]['done'] ?? 0),
                'said_not_done' => (int) ($checkins['by_user'][$uid]['not_done'] ?? 0),
                'shelf_changed' => (int) ($shelf[$uid] ?? 0),
                'shipped_web' => $shipped === null ? null : (int) ($shipped[$uid]['web'] ?? 0),
                'shipped_discogs' => $shipped === null ? null : (int) ($shipped[$uid]['discogs'] ?? 0),
            ];
        }
        uasort($people, function ($a, $b) {
            return [$b['shifts'] > 0, $b['priced'], $a['name']] <=> [$a['shifts'] > 0, $a['priced'], $b['name']];
        });

        return view('manager_dashboard.index', [
            'store' => $store,
            'storeLabel' => self::STORES[$store],
            'stores' => self::STORES,
            'canSwitch' => $canSwitch,
            'days' => $days,
            'dayChoices' => self::DAY_CHOICES,
            'from' => $from,
            'today' => $today,
            'roster' => $roster,
            'employeeId' => $employeeId,
            'people' => $people,
            'priced' => $priced,
            'genres' => $genres,
            'shiftRows' => $shiftData['rows'],
            'tasks' => $tasks,
            'late' => $late,
            'checkins' => $checkins,
            'web' => $web,
            'daily' => $daily,
            'low' => $low,
            'shippedOk' => $shipped !== null,
            'errors' => $errors,
            'lateMin' => self::LATE_MIN,
            'lowWeeks' => self::LOW_WEEKS,
        ]);
    }

    /* ---------- 10. shelf counts ---------- */

    /**
     * [user_id => items]: distinct products whose ERP stock at this store was
     * changed by a shelf / reorder count (ReorderService::applyCountToErp logs
     * "stock_changed" with "bin count: <store>: before -> after").
     */
    private function shelfCountChanges($business_id, $location, $roster, $fromStr)
    {
        if (!$roster) {
            return [];
        }
        $out = [];
        $rows = DB::table('activity_log')
            ->where('business_id', $business_id)
            ->where('description', 'stock_changed')
            ->where('created_at', '>=', $fromStr)
            ->whereIn('causer_id', array_keys($roster))
            ->where('properties', 'like', '%bin count: ' . str_replace(['%', '_'], ['\\%', '\\_'], $location->name) . ':%')
            ->groupBy('causer_id')
            ->select('causer_id', DB::raw('COUNT(DISTINCT subject_id) as n'))
            ->get();
        foreach ($rows as $r) {
            $out[(int) $r->causer_id] = (int) $r->n;
        }
        return $out;
    }

    /* ---------- who works at this store ---------- */

    /** [user_id => name]: active staff whose home store is this one, or who clocked in here lately. */
    private function roster($business_id, $store, $loc, $lookbackDays)
    {
        $ids = [];
        if (Schema::hasColumn('users', 'home_store')) {
            $ids = DB::table('users')->where('business_id', $business_id)
                ->where('home_store', $store)->pluck('id')->all();
        }
        if (Schema::hasTable('time_clock_punches')) {
            $ids = array_merge($ids, DB::table('time_clock_punches')
                ->where('business_id', $business_id)->where('location_id', $loc)
                ->whereNull('voided_at')
                ->where('clock_in_at', '>=', Carbon::today()->subDays($lookbackDays)->format('Y-m-d H:i:s'))
                ->distinct()->pluck('user_id')->all());
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $out = [];
        $users = DB::table('users')->where('business_id', $business_id)->whereIn('id', $ids)
            ->where('status', 'active')->whereNull('deleted_at')
            ->select('id', 'first_name', 'last_name')->orderBy('first_name')->get();
        foreach ($users as $u) {
            $out[(int) $u->id] = trim($u->first_name . ' ' . $u->last_name) ?: ('User #' . $u->id);
        }
        return $out;
    }

    /* ---------- 1. priced ---------- */

    /** Purchase / opening-stock lines put into stock at this store. */
    private function pricedBase($business_id, $loc)
    {
        return DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->where('t.business_id', $business_id)
            ->whereIn('t.type', ['purchase', 'opening_stock'])
            ->where('t.location_id', $loc);
    }

    private function pricedAllTime($business_id, $loc, array $roster)
    {
        $out = ['people' => [], 'formats' => [], 'total' => 0];
        if (!$roster) {
            return $out;
        }
        $rows = $this->pricedBase($business_id, $loc)
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereIn('t.created_by', array_keys($roster))
            ->groupBy('t.created_by', 'c.name')
            ->select('t.created_by', DB::raw("COALESCE(NULLIF(c.name, ''), 'Uncategorized') as format"), DB::raw('SUM(pl.quantity) as qty'))
            ->get();
        foreach ($rows as $r) {
            $uid = (int) $r->created_by;
            $qty = (int) round((float) $r->qty);
            if ($qty <= 0) {
                continue;
            }
            if (!isset($out['people'][$uid])) {
                $out['people'][$uid] = ['total' => 0, 'formats' => []];
            }
            $out['people'][$uid]['total'] += $qty;
            $out['people'][$uid]['formats'][$r->format] = ($out['people'][$uid]['formats'][$r->format] ?? 0) + $qty;
            $out['formats'][$r->format] = ($out['formats'][$r->format] ?? 0) + $qty;
            $out['total'] += $qty;
        }
        arsort($out['formats']);
        foreach ($out['people'] as &$p) {
            arsort($p['formats']);
        }
        unset($p);
        uasort($out['people'], function ($a, $b) { return $b['total'] <=> $a['total']; });
        return $out;
    }

    /** Per format + genre: priced, sold, % sold, average days to sell. */
    private function genreConversion($business_id, $loc, $employeeId)
    {
        $q = $this->pricedBase($business_id, $loc)
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('categories as sc', 'sc.id', '=', 'p.sub_category_id');
        if ($employeeId) {
            $q->where('t.created_by', $employeeId);
        }
        $rows = $q->groupBy('c.name', 'sc.name')
            ->select(
                DB::raw("COALESCE(NULLIF(c.name, ''), 'Uncategorized') as format"),
                DB::raw("COALESCE(NULLIF(sc.name, ''), '(no genre)') as genre"),
                DB::raw('SUM(pl.quantity) as priced'),
                DB::raw('SUM(LEAST(pl.quantity_sold, pl.quantity)) as sold')
            )
            ->havingRaw('SUM(pl.quantity) >= ?', [$employeeId ? 5 : 20])
            ->orderByRaw('SUM(pl.quantity) DESC')
            ->limit(80)
            ->get();

        $dq = DB::table('transaction_sell_lines_purchase_lines as tslp')
            ->join('purchase_lines as pl', 'pl.id', '=', 'tslp.purchase_line_id')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('transaction_sell_lines as tsl', 'tsl.id', '=', 'tslp.sell_line_id')
            ->join('transactions as sale', 'sale.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('categories as sc', 'sc.id', '=', 'p.sub_category_id')
            ->where('t.business_id', $business_id)
            ->whereIn('t.type', ['purchase', 'opening_stock'])
            ->where('t.location_id', $loc)
            ->where('sale.type', 'sell')
            ->where('sale.status', 'final')
            ->whereRaw('DATEDIFF(sale.transaction_date, t.transaction_date) >= 0');
        if ($employeeId) {
            $dq->where('t.created_by', $employeeId);
        }
        $dayRows = $dq->groupBy('c.name', 'sc.name')
            ->select(
                DB::raw("COALESCE(NULLIF(c.name, ''), 'Uncategorized') as format"),
                DB::raw("COALESCE(NULLIF(sc.name, ''), '(no genre)') as genre"),
                DB::raw('SUM(DATEDIFF(sale.transaction_date, t.transaction_date) * tslp.quantity) / NULLIF(SUM(tslp.quantity), 0) as avg_days')
            )
            ->get();
        $daysMap = [];
        foreach ($dayRows as $d) {
            $daysMap[$d->format . '|' . $d->genre] = $d->avg_days === null ? null : (float) $d->avg_days;
        }

        $out = [];
        foreach ($rows as $r) {
            $priced = (float) $r->priced;
            $sold = (float) $r->sold;
            $out[] = [
                'format' => $r->format,
                'genre' => $r->genre,
                'priced' => (int) round($priced),
                'sold' => (int) round($sold),
                'pct' => $priced > 0 ? round(100 * $sold / $priced) : 0,
                'days' => $daysMap[$r->format . '|' . $r->genre] ?? null,
            ];
        }
        return $out;
    }

    /* ---------- 2. signups ---------- */

    private function signupBase($business_id)
    {
        $q = DB::table('contacts')->where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both']);
        if (Schema::hasColumn('contacts', 'import_source')) {
            $q->whereNull('import_source');
        }
        return $q;
    }

    private function signupsAllTime($business_id, array $ids)
    {
        if (!$ids) {
            return [];
        }
        return $this->signupBase($business_id)->whereIn('created_by', $ids)
            ->groupBy('created_by')
            ->select('created_by', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'created_by')->all();
    }

    /* ---------- 2 + 3. shifts ---------- */

    /** Each clock-in at this store in the window, with what was priced and signed up during it. */
    private function shifts($business_id, $loc, array $roster, $fromStr)
    {
        $out = ['rows' => [], 'by_user' => []];
        if (!$roster || !Schema::hasTable('time_clock_punches')) {
            return $out;
        }
        $ids = array_keys($roster);
        $punches = DB::table('time_clock_punches')
            ->where('business_id', $business_id)->where('location_id', $loc)
            ->whereNull('voided_at')->whereIn('user_id', $ids)
            ->where('clock_in_at', '>=', $fromStr)
            ->orderBy('clock_in_at', 'desc')
            ->select('id', 'user_id', 'clock_in_at', 'clock_out_at')
            ->get();
        if ($punches->isEmpty()) {
            return $out;
        }

        // Everything these people priced / signed up since the window began, bucketed in PHP.
        $pricedByUser = [];
        $pl = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $business_id)
            ->whereIn('t.type', ['purchase', 'opening_stock'])
            ->whereIn('t.created_by', $ids)
            ->whereRaw('COALESCE(pl.created_at, t.created_at) >= ?', [$fromStr])
            ->select('t.created_by', DB::raw('COALESCE(pl.created_at, t.created_at) as at'), 'pl.quantity')
            ->get();
        foreach ($pl as $r) {
            $pricedByUser[(int) $r->created_by][] = [strtotime($r->at), (float) $r->quantity];
        }
        $signupsByUser = [];
        $sg = $this->signupBase($business_id)->whereIn('created_by', $ids)
            ->where('created_at', '>=', $fromStr)->select('created_by', 'created_at')->get();
        foreach ($sg as $r) {
            $signupsByUser[(int) $r->created_by][] = strtotime($r->created_at);
        }

        $now = time();
        foreach ($punches as $p) {
            $uid = (int) $p->user_id;
            $in = strtotime($p->clock_in_at);
            $open = empty($p->clock_out_at);
            $end = $open ? $now : strtotime($p->clock_out_at);
            // A punch left open for most of a day is a missed clock-out: no length.
            $missed = $open && ($now - $in) > 18 * 3600;
            $hours = $missed ? null : max(0, ($end - $in) / 3600);
            $upTo = $missed ? $in + 12 * 3600 : $end;

            $priced = 0.0;
            foreach ($pricedByUser[$uid] ?? [] as $x) {
                if ($x[0] >= $in && $x[0] <= $upTo) {
                    $priced += $x[1];
                }
            }
            $signups = 0;
            foreach ($signupsByUser[$uid] ?? [] as $ts) {
                if ($ts >= $in && $ts <= $upTo) {
                    $signups++;
                }
            }
            $priced = (int) round($priced);
            $out['rows'][] = [
                'user_id' => $uid,
                'name' => $roster[$uid] ?? ('User #' . $uid),
                'in' => $in,
                'out' => $open ? null : $end,
                'open' => $open && !$missed,
                'missed' => $missed,
                'hours' => $hours,
                'priced' => $priced,
                'per_hour' => ($hours !== null && $hours >= 0.5) ? $priced / $hours : null,
                'signups' => $signups,
            ];
            if (!isset($out['by_user'][$uid])) {
                $out['by_user'][$uid] = ['shifts' => 0, 'hours' => 0.0, 'priced' => 0, 'signups' => 0];
            }
            $out['by_user'][$uid]['shifts']++;
            $out['by_user'][$uid]['hours'] += (float) $hours;
            $out['by_user'][$uid]['priced'] += $priced;
            $out['by_user'][$uid]['signups'] += $signups;
        }
        return $out;
    }

    /* ---------- 4. tasks ---------- */

    private function tasks($business_id, $store, array $roster, Carbon $from)
    {
        $out = ['by_user' => [], 'done' => 0, 'in_progress' => 0, 'not_started' => 0, 'in_progress_list' => []];
        if (!Schema::hasTable('weekly_tasks')) {
            return $out;
        }
        $bump = function ($uid, $field) use (&$out, $roster) {
            $uid = (int) $uid;
            if (!isset($roster[$uid])) {
                return;
            }
            if (!isset($out['by_user'][$uid])) {
                $out['by_user'][$uid] = ['done' => 0, 'in_progress' => 0];
            }
            $out['by_user'][$uid][$field]++;
        };
        $scope = function ($q) use ($store) {
            $q->where('store', $store)->orWhereNull('store');
        };

        $tasks = DB::table('weekly_tasks')->where('business_id', $business_id)->where($scope)
            ->select('id', 'title', 'store', 'status', 'completed_by', 'completed_at', 'started_by')->get();
        $assignees = [];
        if (Schema::hasTable('task_assignees') && $tasks->isNotEmpty()) {
            foreach (DB::table('task_assignees')->whereIn('task_id', $tasks->pluck('id')->all())->get() as $a) {
                $assignees[(int) $a->task_id][] = (int) $a->user_id;
            }
        }
        $fromStr = $from->format('Y-m-d') . ' 00:00:00';
        foreach ($tasks as $t) {
            $who = $assignees[(int) $t->id] ?? [];
            // Company-wide tasks only count here when someone on this store's team is on them.
            $storeTask = $t->store === $store;
            $teamOn = (bool) array_filter($who, function ($u) use ($roster) { return isset($roster[$u]); });
            if ($t->status === 'complete') {
                if ($t->completed_at && $t->completed_at >= $fromStr) {
                    if ($storeTask || isset($roster[(int) $t->completed_by])) {
                        $out['done']++;
                    }
                    $bump($t->completed_by, 'done');
                }
            } elseif ($t->status === 'in_progress') {
                $owners = $who ?: ($t->started_by ? [(int) $t->started_by] : []);
                $onTeam = array_values(array_filter($owners, function ($u) use ($roster) { return isset($roster[$u]); }));
                if ($storeTask || $onTeam) {
                    $out['in_progress']++;
                    $out['in_progress_list'][] = [
                        'title' => $t->title,
                        'who' => implode(', ', array_map(function ($u) use ($roster) { return $roster[$u]; }, $onTeam)),
                    ];
                }
                foreach ($owners as $u) {
                    $bump($u, 'in_progress');
                }
            } elseif ($storeTask || $teamOn) {
                $out['not_started']++;
            }
        }

        // Past days of daily repeats.
        if (Schema::hasTable('task_completion_logs')) {
            $logs = DB::table('task_completion_logs')->where('business_id', $business_id)->where($scope)
                ->where('status', 'complete')->whereDate('log_date', '>=', $from->format('Y-m-d'))
                ->select('store', 'completed_by')->get();
            foreach ($logs as $l) {
                if ($l->store === $store || isset($roster[(int) $l->completed_by])) {
                    $out['done']++;
                }
                $bump($l->completed_by, 'done');
            }
        }
        return $out;
    }

    /* ---------- 5. opened the ERP late ---------- */

    /**
     * For every day someone was on the Sling schedule at this store: the first
     * time they touched the ERP that day (login or clock-in) against their
     * shift start. More than LATE_MIN minutes after = late. Days with no ERP
     * record at all are left out rather than guessed.
     */
    private function lateOpens($store, array $roster, Carbon $from)
    {
        $out = ['by_user' => [], 'rows' => []];
        if (!$roster || !Schema::hasTable('sling_shifts')) {
            return $out;
        }
        $ids = array_keys($roster);
        $fromStr = $from->format('Y-m-d') . ' 00:00:00';
        $nowStr = Carbon::now()->format('Y-m-d H:i:s');

        $shifts = DB::table('sling_shifts')
            ->where('event_type', 'shift')->where('published', 1)
            ->whereIn('erp_user_id', $ids)
            ->where('location_name', 'like', '%' . $store . '%')
            ->where('dtstart', '>=', $fromStr)->where('dtstart', '<=', $nowStr)
            ->select('erp_user_id', 'dtstart')->get();
        $starts = []; // uid|date => first shift start
        foreach ($shifts as $s) {
            $ts = strtotime($s->dtstart);
            $k = (int) $s->erp_user_id . '|' . date('Y-m-d', $ts);
            if (!isset($starts[$k]) || $ts < $starts[$k]) {
                $starts[$k] = $ts;
            }
        }
        if (!$starts) {
            return $out;
        }

        $first = []; // uid|date => first ERP touch
        $note = function ($uid, $at) use (&$first) {
            if (!$at) {
                return;
            }
            $ts = strtotime($at);
            $k = (int) $uid . '|' . date('Y-m-d', $ts);
            if (!isset($first[$k]) || $ts < $first[$k]) {
                $first[$k] = $ts;
            }
        };
        if (Schema::hasTable('login_activities')) {
            $logins = DB::table('login_activities')->whereIn('user_id', $ids)
                ->where('successful', 1)->where('created_at', '>=', $fromStr)
                ->groupBy('user_id', DB::raw('DATE(created_at)'))
                ->select('user_id', DB::raw('MIN(created_at) as at'))->get();
            foreach ($logins as $r) {
                $note($r->user_id, $r->at);
            }
        }
        if (Schema::hasTable('time_clock_punches')) {
            $punches = DB::table('time_clock_punches')->whereIn('user_id', $ids)
                ->whereNull('voided_at')->where('clock_in_at', '>=', $fromStr)
                ->groupBy('user_id', DB::raw('DATE(clock_in_at)'))
                ->select('user_id', DB::raw('MIN(clock_in_at) as at'))->get();
            foreach ($punches as $r) {
                $note($r->user_id, $r->at);
            }
        }

        // Anything they did in the ERP (sales, duty pick, edits) also counts as
        // having it open, so staying logged in overnight isn't read as late.
        if (Schema::hasTable('activity_log')) {
            $acts = DB::table('activity_log')->whereIn('causer_id', $ids)
                ->where('created_at', '>=', $fromStr)
                ->groupBy('causer_id', DB::raw('DATE(created_at)'))
                ->select('causer_id', DB::raw('MIN(created_at) as at'))->get();
            foreach ($acts as $r) {
                $note($r->causer_id, $r->at);
            }
        }

        foreach ($starts as $k => $start) {
            list($uid, $date) = explode('|', $k);
            $uid = (int) $uid;
            if (!isset($out['by_user'][$uid])) {
                $out['by_user'][$uid] = ['scheduled' => 0, 'late' => 0, 'no_record' => 0];
            }
            $out['by_user'][$uid]['scheduled']++;
            if (!isset($first[$k])) {
                // Today's shift that hasn't been opened yet counts once it's past the cutoff.
                $out['by_user'][$uid]['no_record']++;
                continue;
            }
            $min = (int) floor(($first[$k] - $start) / 60);
            if ($min > self::LATE_MIN) {
                $out['by_user'][$uid]['late']++;
                $out['rows'][] = [
                    'name' => $roster[$uid] ?? ('User #' . $uid),
                    'start' => $start,
                    'opened' => $first[$k],
                    'min' => $min,
                ];
            }
        }
        usort($out['rows'], function ($a, $b) { return $b['start'] <=> $a['start']; });
        return $out;
    }

    /* ---------- 6. register-close answers ---------- */

    private function checkins($business_id, $loc, array $ids, $fromStr)
    {
        $out = ['by_user' => [], 'not_done' => []];
        if (!$ids || !Schema::hasTable('task_shift_checkins')) {
            return $out;
        }
        $hasReason = Schema::hasColumn('task_shift_checkins', 'reason');
        $rows = DB::table('task_shift_checkins as k')
            ->leftJoin('cash_registers as cr', 'cr.id', '=', 'k.cash_register_id')
            ->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->where('k.business_id', $business_id)
            ->where('k.phase', 'end')
            ->whereIn('k.user_id', $ids)
            ->where('k.created_at', '>=', $fromStr)
            ->where(function ($q) use ($loc) {
                $q->where('cr.location_id', $loc)->orWhereNull('k.cash_register_id');
            })
            ->orderBy('k.created_at', 'desc')
            ->select('k.user_id', 'k.response', 'k.task_title', 'k.created_at', 'u.first_name', 'u.last_name',
                DB::raw(($hasReason ? 'k.reason' : 'NULL') . ' as reason'))
            ->get();
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            if (!isset($out['by_user'][$uid])) {
                $out['by_user'][$uid] = ['done' => 0, 'not_done' => 0];
            }
            if ($r->response === 'done') {
                $out['by_user'][$uid]['done']++;
            } elseif ($r->response === 'not_done') {
                $out['by_user'][$uid]['not_done']++;
                $out['not_done'][] = [
                    'name' => trim($r->first_name . ' ' . $r->last_name),
                    'task' => $r->task_title,
                    'reason' => $r->reason,
                    'at' => strtotime($r->created_at),
                ];
            }
        }
        return $out;
    }

    /* ---------- 8. priced per day ---------- */

    private function dailyPriced($business_id, $loc, array $roster, Carbon $from, Carbon $today)
    {
        $rows = $this->pricedBase($business_id, $loc)
            ->whereRaw('COALESCE(pl.created_at, t.created_at) >= ?', [$from->format('Y-m-d') . ' 00:00:00'])
            ->groupBy(DB::raw('DATE(COALESCE(pl.created_at, t.created_at))'), 't.created_by')
            ->select(DB::raw('DATE(COALESCE(pl.created_at, t.created_at)) as d'), 't.created_by', DB::raw('SUM(pl.quantity) as qty'))
            ->get();
        $out = [];
        for ($d = $today->copy(); $d->gte($from); $d->subDay()) {
            $out[$d->format('Y-m-d')] = ['total' => 0, 'who' => []];
        }
        foreach ($rows as $r) {
            if (!isset($out[$r->d])) {
                continue;
            }
            $qty = (int) round((float) $r->qty);
            $out[$r->d]['total'] += $qty;
            $uid = (int) $r->created_by;
            $name = $roster[$uid] ?? 'Others';
            $out[$r->d]['who'][$name] = ($out[$r->d]['who'][$name] ?? 0) + $qty;
        }
        foreach ($out as &$o) {
            arsort($o['who']);
        }
        unset($o);
        return $out;
    }

    /* ---------- 9. low stock ---------- */

    /**
     * Format + genre groups selling about once a week or more (last 90 days at
     * this store) with under LOW_WEEKS weeks of stock on hand.
     */
    private function lowStock($business_id, $loc)
    {
        $start = Carbon::now()->subDays(90)->toDateTimeString();
        $sales = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final')
            ->where('t.location_id', $loc)
            ->where('t.transaction_date', '>=', $start)
            ->whereNotNull('p.category_id')
            ->groupBy('p.category_id', 'p.sub_category_id')
            ->select('p.category_id', 'p.sub_category_id', DB::raw('SUM(tsl.quantity - tsl.quantity_returned) as qty'))
            ->get();
        $stock = DB::table('variation_location_details as vld')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->where('p.business_id', $business_id)
            ->where('vld.location_id', $loc)
            ->where('vld.qty_available', '>', 0)
            ->groupBy('p.category_id', 'p.sub_category_id')
            ->select('p.category_id', 'p.sub_category_id', DB::raw('SUM(vld.qty_available) as q'))
            ->get();
        $names = DB::table('categories')->where('business_id', $business_id)->pluck('name', 'id')->all();

        $groups = []; // "cat|sub" and "cat|*" => [sold, on_hand]
        $add = function ($cat, $sub, $field, $n) use (&$groups) {
            foreach ([$cat . '|' . (int) $sub, $cat . '|*'] as $k) {
                if (!isset($groups[$k])) {
                    $groups[$k] = ['sold' => 0.0, 'on_hand' => 0.0];
                }
                $groups[$k][$field] += (float) $n;
            }
        };
        foreach ($sales as $s) {
            $add((int) $s->category_id, $s->sub_category_id, 'sold', $s->qty);
        }
        foreach ($stock as $s) {
            if ($s->category_id) {
                $add((int) $s->category_id, $s->sub_category_id, 'on_hand', $s->q);
            }
        }

        $out = [];
        foreach ($groups as $k => $g) {
            if ($g['sold'] < self::LOW_MIN_SOLD) {
                continue;
            }
            $weekly = $g['sold'] / 13;
            $weeks = $g['on_hand'] / $weekly;
            if ($weeks >= self::LOW_WEEKS) {
                continue;
            }
            list($cat, $sub) = explode('|', $k);
            $whole = $sub === '*';
            if (!$whole && (int) $sub === 0) {
                continue; // no genre set: already covered by the whole-format row
            }
            $out[] = [
                'format' => $names[(int) $cat] ?? 'Uncategorized',
                'genre' => $whole ? null : ($names[(int) $sub] ?? '(no genre)'),
                'whole' => $whole,
                'sold' => (int) round($g['sold']),
                'weekly' => $weekly,
                'on_hand' => (int) round($g['on_hand']),
                'weeks' => $weeks,
            ];
        }
        usort($out, function ($a, $b) {
            return [$b['whole'], $a['weeks'], $b['sold']] <=> [$a['whole'], $b['weeks'], $a['sold']];
        });
        return array_slice($out, 0, 60);
    }
}
