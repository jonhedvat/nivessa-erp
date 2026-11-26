<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use DB;
use Illuminate\Http\Request;

/**
 * Bulk Transfer (Jon, 2026-10-23): paste or scan a list of product ids / SKUs
 * and move them from one store to another in one go. Every move is a real
 * completed stock transfer (sell_transfer + purchase_transfer), made by the
 * same transfer() BinMoveController uses, so Stock History and the ledger
 * stay right. Direct qty edits would be reverted by Stock History.
 */
class BulkTransferController extends Controller
{
    const NOTE_PREFIX = 'Bulk transfer';

    protected function guard()
    {
        // Same permission StockTransferController@store checks.
        if (!auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();
        $business_id = (int) $request->session()->get('user.business_id');
        // Every store, not just the ones the user is assigned to: staff move
        // stock between Pico, Hollywood and the warehouse whichever store they work at.
        $locations = array_map('ucfirst', BusinessLocation::forDropdown($business_id, false, false, false, false)->toArray());

        // Default From = the store the user is on duty at, then their home location.
        $default_from = (int) $request->session()->get('pos_duty_location_id', 0);
        if (!$default_from) {
            $default_from = (int) $request->session()->get('user.business_location_id', 0);
        }
        if (!isset($locations[$default_from])) {
            $default_from = (int) (array_keys($locations)[0] ?? 0);
        }
        $default_to = 0;
        foreach (array_keys($locations) as $id) {
            if ((int) $id !== $default_from) { $default_to = (int) $id; break; }
        }

        $history = $this->history($business_id);

        // "Transfer Selected" on the Products page sends ?ids=1,2,3; the list
        // is filled as "ID 123" lines so each resolves to that exact product.
        $prefill = '';
        $ids = array_filter(array_map('intval', explode(',', (string) $request->input('ids', ''))));
        if ($ids) {
            $prefill = implode("\n", array_map(function ($id) { return 'ID ' . $id; }, \DB::table('products')
                ->where('business_id', $business_id)->whereIn('id', array_slice($ids, 0, 500))->pluck('id')->all()));
        }

        return view('bulk_transfer.index', compact('locations', 'default_from', 'default_to', 'history', 'prefill'));
    }

    /** Step 1: resolve the pasted list, nothing is written. */
    public function preview(Request $request)
    {
        $this->guard();
        $business_id = (int) $request->session()->get('user.business_id');
        [$from, $to, $error] = $this->locationsFromRequest($request, $business_id);
        if ($error) {
            return response()->json(['success' => false, 'msg' => $error]);
        }
        $rows = $this->resolve($business_id, (string) $request->input('list', ''), $from, $to);
        $ok = array_values(array_filter($rows, function ($r) { return $r['status'] === 'ok'; }));
        return response()->json([
            'success' => true,
            'rows' => $rows,
            'ok_lines' => count($ok),
            'ok_qty' => array_sum(array_column($ok, 'qty')),
        ]);
    }

    /** Step 2: re-resolve on the server (never trust the preview) and move the OK lines. */
    public function store(Request $request)
    {
        $this->guard();
        $business_id = (int) $request->session()->get('user.business_id');
        [$from, $to, $error] = $this->locationsFromRequest($request, $business_id);
        if ($error) {
            return response()->json(['success' => false, 'msg' => $error]);
        }
        $list = (string) $request->input('list', '');
        $user = auth()->user();
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'user');

        try {
            DB::beginTransaction();
            // Lock From stock rows so two people moving the same record can't both take it.
            $rows = $this->resolve($business_id, $list, $from, $to, true);
            $ok = array_values(array_filter($rows, function ($r) { return $r['status'] === 'ok'; }));
            if (!$ok) {
                DB::rollBack();
                return response()->json(['success' => false, 'msg' => 'Nothing to move. Every line was skipped.', 'rows' => $rows]);
            }
            $lines = array_map(function ($r) {
                return ['product_id' => $r['product_id'], 'variation_id' => $r['variation_id'], 'qty' => (float) $r['qty'], 'price' => (float) $r['price']];
            }, $ok);
            $t = app(BinMoveController::class)->transfer($business_id, $from, $to, $lines, self::NOTE_PREFIX . ' by ' . $name, (int) $user->id);
            // Acceptance ID the other store scans to mark the box received.
            $receipt = !\Schema::hasTable('transfer_receipts') ? ['id' => null, 'code' => null] : TransferReceiptController::createFor($business_id, $t, $from, $to, (float) array_sum(array_column($ok, 'qty')), (int) $user->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('BulkTransfer failed: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['success' => false, 'msg' => 'Move failed, nothing changed: ' . $e->getMessage()]);
        }

        // Website picks up the new store per product (it spaces bulk pushes itself).
        $ids = array_values(array_unique(array_column($ok, 'product_id')));
        try { (new \App\Services\NivessaStockNotifier())->pushProductChanged($ids); } catch (\Throwable $e) {}

        $skipped = array_values(array_filter($rows, function ($r) { return $r['status'] !== 'ok'; }));
        return response()->json([
            'success' => true,
            'moved_lines' => count($ok),
            'moved_qty' => array_sum(array_column($ok, 'qty')),
            'skipped' => $skipped,
            'ref_no' => $t->ref_no,
            'transfer_id' => $t->id,
            'print_url' => url('stock-transfers/print/' . $t->id),
            'list_url' => url('stock-transfers'),
            'code' => $receipt['code'],
            'slip_url' => $receipt['id'] ? url('transfers/slip/' . $receipt['id']) : null,
            'labels_url' => $receipt['id'] ? url('transfers/labels/' . $receipt['id']) : null,
            'history' => $this->history($business_id),
        ]);
    }

    /**
     * Find by artist / title, for records whose UPC is missing or out of date.
     * Every word typed must appear in the artist or title. Records with stock
     * at From come first. The page adds the picked one as a "VID 123" line.
     */
    public function search(Request $request)
    {
        $this->guard();
        $business_id = (int) $request->session()->get('user.business_id');
        $from = (int) $request->input('from_location_id');
        $to = (int) $request->input('to_location_id');
        $words = array_slice(array_filter(preg_split('/\s+/', trim((string) $request->input('q', '')))), 0, 6);
        $words = array_values(array_filter($words, function ($w) { return mb_strlen($w) >= 2 || ctype_digit($w); }));
        if (!$words) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $q = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.business_id', $business_id)
            ->where('p.is_inactive', 0)
            ->whereNull('v.deleted_at');
        foreach ($words as $w) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('p.name', 'like', $like)->orWhere('p.artist', 'like', $like);
            });
        }
        $stockAt = function (int $loc) {
            return '(SELECT COALESCE(SUM(qty_available), 0) FROM variation_location_details WHERE variation_id = v.id AND location_id = ' . $loc . ')';
        };
        $rows = $q->select('v.id as variation_id', 'v.product_id', 'v.name as version', 'v.sub_sku', 'v.sell_price_inc_tax', 'p.name', 'p.artist', 'p.type',
                'c.name as category', DB::raw($stockAt($from) . ' as qty_from'), DB::raw($stockAt($to) . ' as qty_to'))
            ->orderByRaw($stockAt($from) . ' > 0 DESC')
            ->orderBy('p.artist')->orderBy('p.name')
            ->limit(40)
            ->get();

        $results = $rows->map(function ($r) {
            $name = trim((string) $r->artist) !== '' ? trim($r->artist) . ' - ' . $r->name : $r->name;
            if ($r->type === 'variable' && trim((string) $r->version) !== '' && strtoupper($r->version) !== 'DUMMY') {
                $name .= ' (' . $r->version . ')';
            }
            return [
                'variation_id' => (int) $r->variation_id,
                'product_id' => (int) $r->product_id,
                'name' => $name,
                'category' => (string) $r->category,
                'sku' => (string) $r->sub_sku,
                'qty_from' => (float) $r->qty_from,
                'qty_to' => (float) $r->qty_to,
                'price' => (float) $r->sell_price_inc_tax,
            ];
        })->values();

        return response()->json(['success' => true, 'results' => $results]);
    }

    protected function locationsFromRequest(Request $request, int $business_id): array
    {
        $from = (int) $request->input('from_location_id');
        $to = (int) $request->input('to_location_id');
        $allowed = BusinessLocation::forDropdown($business_id, false, false, false, false)->toArray();
        if (!isset($allowed[$from]) || !isset($allowed[$to])) {
            return [$from, $to, 'Pick a From store and a To store.'];
        }
        if ($from === $to) {
            return [$from, $to, 'From and To are the same store.'];
        }
        return [$from, $to, null];
    }

    /**
     * Parse "SKU", "SKU x2", "SKU *2" or "SKU,2" lines into [line_no, code, qty].
     * The x needs a space before it so a SKU ending in "X2" stays whole.
     */
    protected function parseLines(string $list): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $list) as $i => $raw) {
            $line = trim($raw);
            if ($line === '') continue;
            $qty = 1;
            if (preg_match('/^(.+?)(?:\s+[xX\*]\s*|\s*\*\s*|\s*,\s*)(\d{1,4})$/', $line, $m)) {
                $line = trim($m[1]);
                $qty = max(1, (int) $m[2]);
            }
            $out[] = ['line' => $i + 1, 'code' => $line, 'qty' => $qty];
        }
        return $out;
    }

    /**
     * Resolve each line to a variation: product.sku, then variation sub_sku,
     * then the same code with a leading 0/00 added or removed, then numeric product id. Duplicate lines for the same variation are
     * merged into the first one.
     */
    protected function resolve(int $business_id, string $list, int $from, int $to, bool $lock = false): array
    {
        $rows = [];
        $first_by_variation = [];
        foreach ($this->parseLines($list) as $p) {
            $row = ['line' => $p['line'], 'code' => $p['code'], 'qty' => $p['qty'], 'status' => 'not_found',
                'status_text' => 'Not found', 'name' => '', 'sku' => '', 'product_id' => null, 'variation_id' => null,
                'qty_from' => 0, 'qty_to' => 0, 'price' => 0];

            $match = $this->findVariation($business_id, $p['code'], $from);
            if (is_string($match)) {
                $row['status_text'] = $match;
                $rows[] = $row;
                continue;
            }
            if (!$match) {
                $rows[] = $row;
                continue;
            }

            $row['product_id'] = (int) $match->product_id;
            $row['variation_id'] = (int) $match->variation_id;
            $row['name'] = trim((string) $match->artist) !== '' ? trim($match->artist) . ' - ' . $match->name : $match->name;
            $row['sku'] = $match->sub_sku ?: $match->sku;

            $vid = $row['variation_id'];
            if (isset($first_by_variation[$vid])) {
                // Fold the qty into the first line for this record, then re-check stock there.
                $rows[$first_by_variation[$vid]]['qty'] += $p['qty'];
                $this->checkStock($rows[$first_by_variation[$vid]], (int) $match->enable_stock);
                $row['qty'] = 0;
                $row['status'] = 'duplicate';
                $row['status_text'] = 'Duplicate line merged into line ' . $rows[$first_by_variation[$vid]]['line'];
                $rows[] = $row;
                continue;
            }

            $q = DB::table('variation_location_details')->where('variation_id', $vid)->where('location_id', $from);
            if ($lock) $q->lockForUpdate();
            $row['qty_from'] = (float) $q->sum('qty_available');
            $row['qty_to'] = (float) DB::table('variation_location_details')->where('variation_id', $vid)->where('location_id', $to)->sum('qty_available');
            $row['price'] = (float) ($match->last_purchased_price ?? $match->default_purchase_price ?? 0);

            $this->checkStock($row, (int) $match->enable_stock);
            $first_by_variation[$vid] = count($rows);
            $rows[] = $row;
        }
        return $rows;
    }

    protected function checkStock(array &$row, int $enable_stock)
    {
        if ($enable_stock !== 1) {
            $row['status'] = 'no_stock_tracking';
            $row['status_text'] = 'Stock not tracked for this product';
        } elseif ($row['qty_from'] <= 0 && $row['qty_to'] > 0) {
            $row['status'] = 'at_to_only';
            $row['status_text'] = 'Already at To only';
        } elseif ($row['qty_from'] < $row['qty']) {
            $row['status'] = 'not_enough';
            $row['status_text'] = 'Not enough stock at From (has ' . $this->fmt($row['qty_from']) . ')';
        } else {
            $row['status'] = 'ok';
            $row['status_text'] = 'OK';
        }
    }

    /** Returns a variation row, null when not found, or a reason string. */
    protected function findVariation(int $business_id, string $code, int $from)
    {
        $base = function () use ($business_id) {
            return DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('p.business_id', $business_id)
                ->whereNull('v.deleted_at')
                ->select('v.id as variation_id', 'v.product_id', 'v.sub_sku', 'v.default_purchase_price', 'p.sku', 'p.name', 'p.artist', 'p.enable_stock',
                    DB::raw('(SELECT purchase_price_inc_tax FROM purchase_lines WHERE variation_id = v.id ORDER BY id DESC LIMIT 1) as last_purchased_price'));
        };

        // "VID 123" (what Find by artist / title fills in) is that exact version.
        if (preg_match('/^vid\s*[:#]?\s*(\d+)$/i', $code, $m)) {
            return $base()->where('v.id', (int) $m[1])->first();
        }

        // "ID 123" (what Transfer Selected fills in) is that exact product.
        if (preg_match('/^id\s*[:#]?\s*(\d+)$/i', $code, $m)) {
            $cands = $base()->where('p.id', (int) $m[1])->get();
            return $cands->isEmpty() ? null : ($cands->count() === 1 ? $cands->first() : 'Has ' . $cands->count() . ' versions, scan the exact version SKU');
        }

        // 1) product SKU
        $cands = $base()->where('p.sku', $code)->get();
        // 2) variation sub_sku (multi-version products are matched this way)
        if ($cands->isEmpty()) {
            $cands = $base()->where('v.sub_sku', $code)->get();
        }
        // 3) our own improvised UPCs are sometimes saved with a leading 0 or 00
        //    the scan doesn't have (or the scan has zeros the saved code lacks).
        if ($cands->isEmpty() && ctype_digit($code)) {
            $alts = array_values(array_unique(array_filter([
                '0' . $code, '00' . $code, ltrim($code, '0'),
            ], function ($c) use ($code) { return $c !== '' && $c !== $code; })));
            if ($alts) {
                $cands = $base()->where(function ($q) use ($alts) {
                    $q->whereIn('p.sku', $alts)->orWhereIn('v.sub_sku', $alts);
                })->get();
            }
        }
        // 4) numeric product id
        if ($cands->isEmpty() && ctype_digit($code)) {
            $cands = $base()->where('p.id', (int) $code)->get();
        }
        if ($cands->isEmpty()) return null;
        if ($cands->count() === 1) return $cands->first();

        // Several matches (multi-version product, or a SKU shared by two
        // products): take the only one with stock at From, else ask for the exact SKU.
        $ids = $cands->pluck('variation_id')->all();
        $stocked = DB::table('variation_location_details')->whereIn('variation_id', $ids)
            ->where('location_id', $from)->where('qty_available', '>', 0)->pluck('variation_id')->unique()->values()->all();
        if (count($stocked) === 1) {
            return $cands->firstWhere('variation_id', $stocked[0]);
        }
        return 'Matches ' . $cands->count() . ' versions, scan the exact version SKU';
    }

    protected function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    /** Last 20 bulk transfers for the history list. */
    protected function history(int $business_id): array
    {
        $rows = DB::table('transactions as t')
            ->join('transactions as t2', 't2.transfer_parent_id', '=', 't.id')
            ->leftJoin('business_locations as l1', 'l1.id', '=', 't.location_id')
            ->leftJoin('business_locations as l2', 'l2.id', '=', 't2.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->when(\Schema::hasTable('transfer_receipts'), function ($q) {
                $q->leftJoin('transfer_receipts as r', 'r.transaction_id', '=', 't.id')->addSelect('r.id as receipt_id', 'r.code', 'r.status as receipt_status');
            })
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell_transfer')
            ->where('t.additional_notes', 'like', self::NOTE_PREFIX . '%')
            ->orderBy('t.id', 'desc')
            ->limit(20)
            ->addSelect('t.id', 't.ref_no', 't.transaction_date', 'l1.name as from_name', 'l2.name as to_name', 'u.first_name', 'u.last_name',
                DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM transaction_sell_lines WHERE transaction_id = t.id) as items'))
            ->get();
        return $rows->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'ref_no' => $r->ref_no,
                'date' => \Carbon::parse($r->transaction_date)->format('m/d/y'),
                'from' => $r->from_name,
                'to' => $r->to_name,
                'items' => $this->fmt($r->items),
                'by' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
                'print_url' => url('stock-transfers/print/' . $r->id),
                'code' => $r->code ?? null,
                'receipt_id' => !empty($r->receipt_id) ? (int) $r->receipt_id : null,
                'receipt_status' => $r->receipt_status ?? null,
                'slip_url' => !empty($r->receipt_id) ? url('transfers/slip/' . $r->receipt_id) : null,
                'labels_url' => !empty($r->receipt_id) ? url('transfers/labels/' . $r->receipt_id) : null,
            ];
        })->all();
    }
}
