<?php

namespace App\Services;

use App\Contact;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Shared logic for the distributor invoice importers (AMS, Alliance).
//
// The browser parses the invoice PDF with pdf.js and posts the lines. Here we
// match each UPC to a product, flag lines that are already on a purchase from
// the same supplier/store (Jon's pre-logged Alliance orders, floor-staff
// receipts) so they aren't doubled, and propose a new product for every UPC we
// don't carry yet: name "ARTIST - TITLE", SKU = the UPC, category from the
// invoice format, cost = invoice unit price, sell price by the sealed pricing
// convention. Apply creates those products and ONE purchase (status=ordered,
// no stock bump) holding every ticked line, snapshotted for one-click undo.
class DistributorInvoiceImport
{
    // key => settings. 'ref' prefixes the purchase ref_no, 'dir' holds the
    // already-imported sidecars, 'action' is the admin-snapshot action name.
    const DISTRIBUTORS = [
        'ams' => [
            'label' => 'AMS',
            'long' => 'AMS (All Media Supply)',
            'ref' => 'AMS-',
            'dir' => 'ams-imports',
            'action' => 'ams-invoice-import',
            'supplier_like' => ['AMS', 'All Media Supply'],
            // Account # on the invoice => store name.
            'accounts' => ['131507' => 'pico', '131715' => 'hollywood'],
        ],
        'alliance' => [
            'label' => 'Alliance',
            'long' => 'Alliance Entertainment',
            'ref' => 'ALLIANCE-',
            'dir' => 'alliance-imports',
            'action' => 'alliance-invoice-import',
            'supplier_like' => ['Alliance Entertainment'],
            'accounts' => ['048452' => 'hollywood', '065706' => 'pico'],
        ],
        // Redeye sends no PDF invoice we receive; orders are logged from the
        // order confirmation email (lines posted to the import endpoints).
        'redeye' => [
            'label' => 'Redeye',
            'long' => 'Redeye Worldwide',
            'ref' => 'REDEYE-',
            'dir' => 'redeye-imports',
            'action' => 'redeye-invoice-import',
            'supplier_like' => ['Redeye Worldwide'],
            'accounts' => ['54497' => 'hollywood'],
        ],
        // deejay.de's dealer arm (vinylfuture.com). Invoices list a label
        // catalog number ("SW10 - Chris Pascoe- Darkside"), not a barcode, and
        // are in euros: lines are matched by catalog number (products this
        // importer created carry it in their description) or artist + title,
        // and costs are converted to dollars (+ postage/fees spread in).
        'vinylfuture' => [
            'label' => 'Vinyl Future',
            'long' => 'Vinyl Future (deejay.de)',
            'ref' => 'VF-',
            'dir' => 'vinylfuture-imports',
            'action' => 'vinylfuture-invoice-import',
            'supplier_like' => ['deejay vinylfuture', 'Vinyl Future', 'Vinylfuture', 'deejay.de', 'deejay'],
            'accounts' => [],
            'catalog' => true,
            // Sell price = landed cost (tariff, shipping, fees, other costs) x 1.5.
            'markup' => 1.5,
            // Never suggest less than this (what the store charges for deejay 12"s).
            'min_price' => 23.00,
        ],
        // Matador Direct (Matador, XL, 4AD, Young, Rough Trade, Beggars).
        // Sales orders / invoices print the label catalog # ("XL-529-1"), not a
        // barcode. The controller maps it to the UPC through the Matador price
        // feed (same sheet MatadorFetcher reads nightly), so most lines go the
        // normal UPC route; a catalog # the sheet doesn't know falls back to
        // catalog # / artist + title matching like Vinyl Future.
        'matador' => [
            'label' => 'Matador',
            'long' => 'Matador Direct',
            'ref' => 'MATADOR-',
            'dir' => 'matador-imports',
            'action' => 'matador-invoice-import',
            'supplier_like' => ['Matador Records', 'Matador Direct', 'Matador'],
            'accounts' => [],
            'catalog' => true,
            // New titles: no suggested sell price, the buyer types it in.
            'suggest_price' => false,
        ],
    ];

    // Category names new products can land in (top-level, product type).
    const CATEGORY_NAMES = ['CD - Sealed', 'Vinyl - Sealed', 'Cassettes - Sealed', 'Toys'];

    public static function config($distributor)
    {
        return self::DISTRIBUTORS[$distributor] ?? null;
    }

    // AMS invoice numbers are digits ("0625013"); Alliance are "PLS97956676".
    public static function invoiceKey($distributor, $invoice)
    {
        $invoice = strtoupper(trim((string) $invoice));
        if ($distributor === 'ams') {
            return preg_replace('/[^0-9]/', '', $invoice);
        }
        if ($distributor === 'vinylfuture') {
            return preg_replace('/[^0-9-]/', '', $invoice); // "0022-333274"
        }
        return preg_replace('/[^A-Z0-9]/', '', $invoice);
    }

    public static function sidecarPath($distributor, $invoiceKey)
    {
        return self::config($distributor)['dir'] . '/' . $invoiceKey . '.json';
    }

    public static function defaultSupplierId($business_id, $distributor)
    {
        $likes = self::config($distributor)['supplier_like'];
        $c = Contact::where('business_id', $business_id)
            ->where('type', '!=', 'customer')
            ->where(function ($q) use ($likes) {
                foreach ($likes as $like) {
                    $q->orWhere('name', $like)->orWhere('supplier_business_name', $like);
                }
            })
            ->orderBy('id')
            ->first();
        return $c ? $c->id : null;
    }

    // Store id for the account number printed on the invoice, if we know it.
    public static function locationForAccount($business_id, $distributor, $account)
    {
        $account = preg_replace('/\D/', '', (string) $account);
        $map = self::config($distributor)['accounts'];
        if ($account === '' || !isset($map[$account])) {
            return null;
        }
        $id = DB::table('business_locations')->where('business_id', $business_id)
            ->where('name', $map[$account])->value('id');
        return $id ? (int) $id : null;
    }

    public static function categoryIds($business_id)
    {
        return DB::table('categories')->where('business_id', $business_id)
            ->whereNull('deleted_at')->where('parent_id', 0)->where('category_type', 'product')
            ->whereIn('name', self::CATEGORY_NAMES)
            ->pluck('id', 'name')->all(); // name => id
    }

    // Candidate sub_sku forms for a printed UPC: as printed, without leading
    // zeros, 12-digit UPC-A from a 13-digit EAN with a leading zero, and the
    // 13-digit EAN form of a 12-digit UPC.
    public static function upcCandidates($upc)
    {
        $upc = preg_replace('/\D/', '', (string) $upc);
        if ($upc === '') return [];
        $c = [$upc];
        if (strlen($upc) === 13 && $upc[0] === '0') {
            $c[] = substr($upc, 1);
        }
        if (strlen($upc) === 12) {
            $c[] = '0' . $upc;
        }
        $c[] = ltrim($upc, '0');
        return array_values(array_unique(array_filter($c, function ($x) { return $x !== ''; })));
    }

    // The SKU a new product gets: the 12-digit UPC-A when the invoice prints a
    // 13-digit code with a leading zero (same as the sealed-barcode backfill),
    // otherwise the code as printed. Never a catalog number.
    public static function skuForUpc($upc)
    {
        $upc = preg_replace('/\D/', '', (string) $upc);
        if (strlen($upc) === 13 && $upc[0] === '0') {
            return substr($upc, 1);
        }
        return $upc;
    }

    // Category from the invoice's format code (AMS "Prod Type": LP / CD / CDS /
    // MC / SIN (7-inch) / TEN (10-inch) / TIN (12-inch);
    // Alliance "CF": 01 = LP, 02 = CD, 04 = cassette, 07 = vinyl (RSD/special, e.g. Tori Amos
    // "Black Is The New Black" RSD BF26, 11/16/26), TO = toys), falling back to words in
    // the description. Null = unknown, the reviewer picks.
    public static function guessCategory($format, $description)
    {
        $f = strtoupper(trim((string) $format));
        $d = strtoupper((string) $description);
        if ($f === '02' || preg_match('/^\d?CD|SACD/', $f)) return 'CD - Sealed';
        // AMS: TIN = 12-inch, TEN = 10-inch, SIN = 7-inch single (Fugazi "7 Songs" 12" came as TIN).
        if ($f === '01' || $f === '07' || preg_match('/^(\d?LP|VIN|VINYL|7|10|12|7IN|10IN|12IN|EP|SIN|TIN|TEN|45)$/', $f)) return 'Vinyl - Sealed';
        if ($f === 'TO' || $f === 'TOY') return 'Toys';
        if ($f === '04' || preg_match('/^(CS|CAS|CASS|MC)$/', $f)) return 'Cassettes - Sealed';
        if (preg_match('/\bCASSETTE\b/', $d)) return 'Cassettes - Sealed';
        if (preg_match('/\b(\d?LP|VINYL)\b/', $d)) return 'Vinyl - Sealed';
        if (preg_match('/\b\d?CD\b/', $d)) return 'CD - Sealed';
        return null;
    }

    // Sealed pricing convention, read off how the purchases desk prices new
    // sealed titles (median ~1.6-1.7x cost; CDs end in .49, LPs in .99):
    // 1.6x cost, rounded UP to the next .49 for CDs and .99 for everything else.
    public static function suggestPrice($cost, $categoryName, $markup = 1.6, $minPrice = 0)
    {
        $cost = (float) $cost;
        if ($cost <= 0) return null;
        $raw = $cost * $markup;
        $ending = $categoryName === 'CD - Sealed' ? 0.49 : 0.99;
        $p = floor($raw) + $ending;
        if ($p < $raw - 0.0001) $p += 1;
        if ($minPrice > 0 && $p < $minPrice) $p = (float) $minPrice;
        return round($p, 2);
    }

    // "ARTIST / TITLE" (both distributors) => ["Artist", "ARTIST - Title"].
    // Artist goes through the catalog's artist cleanup ("GUARALDI,VINCE" =>
    // "Vince Guaraldi"), title is Title Cased. No slash = no artist.
    public static function nameFromDescription($description)
    {
        $d = trim(preg_replace('/\s+/', ' ', (string) $description));
        $d = str_replace("\u{00AD}", '-', $d);
        // AMS cuts long titles mid-parenthesis ("(MA", "(RANDOM -"): drop the stub.
        $d = trim(preg_replace('/\s*\([^)]*$/', '', $d));
        // Alliance Hot Wheels lines: "HW PREM JAGUAR XJS".
        if (preg_match('/^HW (PREM )?(.+)$/', $d, $m)) {
            return ['artist' => 'Hot Wheels', 'name' => 'Hot Wheels' . ($m[1] ? ' Premium' : '') . ' - ' . ProductNameNormalizer::properTitle($m[2])];
        }
        $parts = preg_split('#\s+/\s+#', $d, 2);
        if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
            $artist = ProductNameNormalizer::cleanArtistValue($parts[0]);
            $title = ProductNameNormalizer::properTitle($parts[1]);
            return ['artist' => $artist, 'name' => $artist . ' - ' . $title];
        }
        return ['artist' => null, 'name' => ProductNameNormalizer::properTitle($d)];
    }

    /**
     * Match parsed lines. $lines: [{upc, description, format, qty, unit_price, line_total}].
     * Returns the lines with matched product info, existing-purchase flags and
     * a proposed new product for unmatched ones.
     */
    public static function preview($business_id, array $lines, $supplier_id, $location_id, $invoiceDate, $invoiceKey = '', $distributor = '')
    {
        $catIds = self::categoryIds($business_id);

        $all = [];
        foreach ($lines as $l) {
            foreach (self::upcCandidates($l['upc'] ?? '') as $c) $all[$c] = true;
        }
        $rowsBySku = [];
        foreach (array_chunk(array_keys($all), 500) as $chunk) {
            $rows = DB::table('variations as v')
                ->join('products as p', 'v.product_id', '=', 'p.id')
                ->where('p.business_id', $business_id)
                ->whereNull('v.deleted_at')
                ->whereIn('v.sub_sku', $chunk)
                ->get(['v.id as variation_id', 'v.product_id', 'v.sub_sku', 'v.default_purchase_price',
                       'v.sell_price_inc_tax', 'p.name as product_name', 'p.is_inactive', 'p.category_id']);
            foreach ($rows as $r) $rowsBySku[$r->sub_sku][] = $r;
        }

        $catalogPool = self::catalogPool($business_id, $lines);

        $out = [];
        foreach ($lines as $l) {
            $upc = preg_replace('/\D/', '', (string) ($l['upc'] ?? ''));
            $desc = trim((string) ($l['description'] ?? ''));
            $format = trim((string) ($l['format'] ?? ''));
            $catName = self::guessCategory($format, $desc);
            $catId = $catName && isset($catIds[$catName]) ? (int) $catIds[$catName] : null;

            // Best product for this UPC: active over inactive, then the one in
            // the invoice's category, then the code as printed, then newest.
            $best = null; $bestScore = null; $allVids = [];
            foreach (self::upcCandidates($upc) as $rank => $cand) {
                foreach ($rowsBySku[$cand] ?? [] as $r) {
                    $allVids[] = (int) $r->variation_id;
                    $score = [(int) !$r->is_inactive, (int) ($catId && (int) $r->category_id === $catId), -$rank, (int) $r->product_id];
                    if ($bestScore === null || $score > $bestScore) { $best = $r; $bestScore = $score; }
                }
            }

            $catno = trim((string) ($l['catno'] ?? ''));
            if (!$best && $catno !== '') {
                $catName = $catName ?: 'Vinyl - Sealed';
                $catId = isset($catIds[$catName]) ? (int) $catIds[$catName] : null;
                $best = self::matchCatalog($business_id, $catno, $l['artist'] ?? '', $l['title'] ?? '', $catId, $catalogPool);
                if ($best) $allVids[] = (int) $best->variation_id;
            }

            $unit = round((float) ($l['unit_price'] ?? 0), 2);
            $line = [
                'catno' => $catno,
                'upc' => $upc,
                'description' => $desc,
                'format' => $format,
                'qty' => (int) ($l['qty'] ?? 0),
                'unit_price' => $unit,
                'line_total' => round((float) ($l['line_total'] ?? 0), 2),
                'matched' => (bool) $best,
                'variation_id' => $best ? (int) $best->variation_id : null,
                'product_id' => $best ? (int) $best->product_id : null,
                'product_name' => $best ? $best->product_name : null,
                'product_inactive' => $best ? (bool) $best->is_inactive : false,
                // Sell price the ERP has today (shown on the review; a matched
                // product with none gets a box to type one in).
                'current_sell' => $best && $best->sell_price_inc_tax !== null ? round((float) $best->sell_price_inc_tax, 2) : null,
                'current_cost' => $best && $best->default_purchase_price !== null ? round((float) $best->default_purchase_price, 2) : null,
                'existing' => [],   // same invoice already logged on another purchase: skip
                'earlier' => [],    // the title is on an older purchase: just FYI
                'on_order' => [],   // open order logged before its invoice: the import replaces it
                'new_product' => null,
                // Every product carrying this UPC (the catalog has duplicates),
                // so "already on a purchase" checks catch any of them.
                'upc_variation_ids' => array_values(array_unique($allVids)),
            ];
            if (!$best) {
                $nm = $catno !== ''
                    ? self::nameFromDescription(trim(($l['artist'] ?? '') . ' / ' . ($l['title'] ?? ''), ' /'))
                    : self::nameFromDescription($desc);
                $line['new_product'] = [
                    'name' => $nm['name'],
                    'artist' => $nm['artist'],
                    // Catalog lines get an auto SKU (no barcode on the invoice).
                    'sku' => $catno !== '' ? '' : self::skuForUpc($upc),
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'sub_category_id' => $catno !== '' && $catId && $distributor === 'vinylfuture' ? self::subCategoryId($business_id, $catId, 'Electronic/Dance') : null,
                    'sell_price' => ((self::config($distributor) ?: [])['suggest_price'] ?? true) === false ? null : self::suggestPrice($unit, $catName, (self::config($distributor) ?: [])['markup'] ?? 1.6, (self::config($distributor) ?: [])['min_price'] ?? 0),
                ];
            }
            $out[] = $line;
        }

        // A line is only left off when THIS invoice is already logged on
        // another purchase (its number in the ref or notes, e.g. Jon's
        // "Alliance PLS97785739", or the purchases registered for it on the
        // audit page). The same title on an older purchase is a reorder or a
        // back-order fill, so it is imported and only noted (Sarah 10/21).
        $vids = [];
        foreach ($out as $l) foreach ($l['upc_variation_ids'] as $v) $vids[] = $v;
        $vids = array_values(array_unique($vids));
        if ($vids && $supplier_id && $location_id) {
            $d = self::normalizeDate($invoiceDate);
            $from = date('Y-m-d', strtotime($d . ' -90 days'));
            $to = date('Y-m-d 23:59:59', strtotime($d . ' +10 days'));
            $key = strtoupper(trim((string) $invoiceKey));
            $registered = [];
            if ($key !== '' && $distributor !== '' && Storage::disk('local')->exists('distributor-invoices/registry.json')) {
                $reg = json_decode(Storage::disk('local')->get('distributor-invoices/registry.json'), true) ?: [];
                foreach ($reg[$distributor . ':' . $key]['purchases'] ?? [] as $pid) $registered[(int) $pid] = true;
            }
            $hits = DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'purchase')
                ->where('t.contact_id', $supplier_id)
                ->where('t.location_id', $location_id)
                ->whereBetween('t.transaction_date', [$from, $to])
                ->whereIn('pl.variation_id', $vids)
                ->get(['pl.variation_id', 't.id', 't.ref_no', 't.additional_notes', 't.status', 'pl.quantity']);
            $same = []; $earlier = []; $onOrder = [];
            foreach ($hits as $h) {
                $row = ['transaction_id' => (int) $h->id, 'ref_no' => $h->ref_no, 'status' => $h->status, 'qty' => (float) $h->quantity];
                $isSame = isset($registered[(int) $h->id])
                    || ($key !== '' && (stripos((string) $h->ref_no, $key) !== false || stripos((string) $h->additional_notes, $key) !== false));
                if ($isSame) $same[(int) $h->variation_id][] = $row;
                elseif (self::isOpenOrderPlaceholder($h->status, $h->ref_no)) $onOrder[(int) $h->variation_id][] = $row;
                else $earlier[(int) $h->variation_id][] = $row;
            }
            foreach ($out as &$line) {
                foreach ($line['upc_variation_ids'] as $v) {
                    if (isset($same[$v])) $line['existing'] = array_merge($line['existing'], $same[$v]);
                    if (isset($onOrder[$v])) $line['on_order'] = array_merge($line['on_order'], $onOrder[$v]);
                    if (isset($earlier[$v])) $line['earlier'] = array_merge($line['earlier'], $earlier[$v]);
                }
            }
            unset($line);
        }

        return $out;
    }

    /**
     * Create new products for "create" lines and one purchase with every line.
     * $lines: [{variation_id?, qty, unit_price, upc, description, new_product?{name, artist, sku, category_id, sell_price}}]
     * Returns ['transaction' => Transaction, 'created_products' => [...], 'lines' => n, 'total' => x].
     */
    public static function apply($distributor, $business_id, $user_id, $location_id, $contact_id, $invoiceKey, $invoiceDate, $notes, array $lines, array $opts = [])
    {
        $cfg = self::config($distributor);
        // A catch-up purchase for lines an earlier import dropped gets its own
        // ref ("AMS-0621577-B") and snapshot key, and leaves the invoice's
        // sidecar alone.
        $suffix = $opts['ref_suffix'] ?? '';
        $writeSidecar = $opts['sidecar'] ?? true;
        $productUtil = app(ProductUtil::class);
        $catIds = array_flip(self::categoryIds($business_id)); // id => name

        // Validate posted variation ids against this business.
        $postedVids = array_values(array_filter(array_map(function ($l) { return (int) ($l['variation_id'] ?? 0); }, $lines)));
        $valid = $postedVids ? DB::table('variations as v')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)
            ->whereIn('v.id', $postedVids)
            ->pluck('v.product_id', 'v.id')->all() : [];

        $created = [];
        $clean = [];
        // Catalog-number invoices: products already made for these catalog
        // numbers (one query), plus the ones this run creates.
        $catPool = !empty($cfg['catalog']) ? self::catalogPool($business_id, $lines) : null;
        $madeForCat = [];
        DB::beginTransaction();
        try {
            foreach ($lines as $l) {
                $qty = (float) ($l['qty'] ?? 0);
                if ($qty <= 0) continue;
                $unit = round((float) ($l['unit_price'] ?? 0), 4);
                $vid = (int) ($l['variation_id'] ?? 0);

                if ($vid && isset($valid[$vid])) {
                    // A matched product with no sell price: save the one typed
                    // on the review. Never overwrites an existing price.
                    $setSell = round((float) ($l['set_sell_price'] ?? 0), 2);
                    if ($setSell > 0) {
                        DB::table('variations')->where('id', $vid)
                            ->where(function ($q) { $q->whereNull('sell_price_inc_tax')->orWhere('sell_price_inc_tax', '<=', 0); })
                            ->update(['default_sell_price' => $setSell, 'sell_price_inc_tax' => $setSell, 'updated_at' => now()]);
                    }
                    $clean[] = ['variation_id' => $vid, 'product_id' => (int) $valid[$vid], 'qty' => $qty, 'unit_price' => $unit];
                    continue;
                }

                $np = $l['new_product'] ?? null;
                if (!$np) continue;
                $catno = !empty($cfg['catalog']) ? trim((string) ($l['catno'] ?? '')) : '';
                $sku = preg_replace('/\D/', '', (string) ($np['sku'] ?? ''));
                if ($catno !== '') {
                    $sku = ''; // auto SKU below
                } elseif (strlen($sku) < 11 || strlen($sku) > 14) {
                    throw new \RuntimeException('Line ' . ($l['description'] ?? '') . ': SKU must be the 11-14 digit UPC.');
                }
                $catId = (int) ($np['category_id'] ?? 0);
                if (!isset($catIds[$catId])) {
                    throw new \RuntimeException('Pick a category for ' . ($np['name'] ?? $sku) . '.');
                }
                $name = trim((string) ($np['name'] ?? ''));
                if ($name === '') {
                    throw new \RuntimeException('New product for UPC ' . $sku . ' needs a name.');
                }
                $sell = round((float) ($np['sell_price'] ?? 0), 2);
                if ($sell <= 0) {
                    throw new \RuntimeException('Set a selling price for ' . $name . '.');
                }

                // Same UPC already created earlier in this invoice (a title on
                // two lines) or by someone else a moment ago: reuse it.
                if ($catno !== '') {
                    $existing = $madeForCat[strtoupper($catno)] ?? null;
                    if (!$existing && $catPool) {
                        foreach ($catPool['cat'] as $r) {
                            if (stripos((string) $r->product_description, ': ' . $catno . ' - ') !== false && !$r->is_inactive) {
                                $existing = (object) ['id' => $r->variation_id, 'product_id' => $r->product_id];
                                break;
                            }
                        }
                    }
                } else {
                    $existing = DB::table('variations as v')->join('products as p', 'v.product_id', '=', 'p.id')
                        ->where('p.business_id', $business_id)->whereNull('v.deleted_at')
                        ->whereIn('v.sub_sku', self::upcCandidates($sku))
                        ->orderBy('p.is_inactive')->orderByDesc('p.id')->first(['v.id', 'v.product_id']);
                }
                if ($existing) {
                    $clean[] = ['variation_id' => (int) $existing->id, 'product_id' => (int) $existing->product_id, 'qty' => $qty, 'unit_price' => $unit];
                    continue;
                }

                $subCat = (int) ($np['sub_category_id'] ?? 0);
                if ($subCat && !DB::table('categories')->where('business_id', $business_id)->where('id', $subCat)->where('parent_id', $catId)->exists()) {
                    $subCat = 0;
                }
                $product = Product::create([
                    'name' => $name,
                    'artist' => !empty($np['artist']) ? $np['artist'] : null,
                    'sku' => $sku !== '' ? $sku : 'tmp' . uniqid(),
                    'barcode_type' => 'C128',
                    'brand_id' => null,
                    'category_id' => $catId,
                    'sub_category_id' => $subCat ?: null,
                    'tax' => 1,
                    'tax_type' => 'exclusive',
                    'alert_quantity' => 0,
                    'business_id' => $business_id,
                    'created_by' => $user_id,
                    'added_via' => $distributor . '_invoice',
                    'enable_stock' => 1,
                    'product_description' => $cfg['label'] . ' invoice ' . $invoiceKey . ': ' . ($l['description'] ?? ''),
                    'unit_id' => 1,
                    'type' => 'single',
                    'not_for_selling' => 0,
                ]);
                if ($sku === '') {
                    $sku = $productUtil->generateProductSku($product->id);
                    $product->sku = $sku;
                    $product->save();
                }
                $productUtil->createSingleProductVariation($product->id, $sku, $unit, $unit, 0, $sell, $sell);
                $product->product_locations()->sync([$location_id]);
                $variation = DB::table('variations')->where('product_id', $product->id)->first(['id']);

                $created[] = ['product_id' => $product->id, 'variation_id' => (int) $variation->id, 'sku' => $sku, 'name' => $name];
                if ($catno !== '') $madeForCat[strtoupper($catno)] = (object) ['id' => (int) $variation->id, 'product_id' => $product->id];
                $clean[] = ['variation_id' => (int) $variation->id, 'product_id' => $product->id, 'qty' => $qty, 'unit_price' => $unit];
            }

            // Lines already on an order logged before this invoice go onto
            // that order (invoice cost, invoice # in its notes). Nothing is
            // deleted; only lines with no logged order make a new purchase.
            $att = empty($opts['no_settle'])
                ? self::attachToOrderPlaceholders($business_id, $contact_id, $location_id, $invoiceDate, $clean, $cfg['label'], $invoiceKey . $suffix)
                : ['remaining' => $clean, 'attached' => []];
            $attached = $att['attached'];
            $clean = $att['remaining'];

            if (count($clean) === 0 && !$attached) {
                throw new \RuntimeException('No lines to import.');
            }

            $total = 0.0;
            foreach ($clean as $l) { $total += $l['qty'] * $l['unit_price']; }
            $total = round($total, 2);

            $newPurchase = count($clean) > 0;
            if (!$newPurchase) {
                $transaction = Transaction::find($attached[0]['transaction_id']);
            } else {
            $productUtil->setAndGetReferenceCount('purchase', $business_id);
            $transaction = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $location_id,
                'type' => 'purchase',
                'status' => 'ordered',
                'payment_status' => 'due',
                'contact_id' => $contact_id,
                'ref_no' => $cfg['ref'] . $invoiceKey . $suffix,
                'transaction_date' => self::normalizeDate($invoiceDate),
                'total_before_tax' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'discount_type' => 'fixed',
                'final_total' => $total,
                'additional_notes' => $notes,
                'created_by' => $user_id,
            ]);

            foreach ($clean as $l) {
                $pl = new PurchaseLine();
                $pl->transaction_id = $transaction->id;
                $pl->product_id = $l['product_id'];
                $pl->variation_id = $l['variation_id'];
                $pl->quantity = $l['qty'];
                $pl->purchase_price = $l['unit_price'];
                $pl->purchase_price_inc_tax = $l['unit_price'];
                $pl->pp_without_discount = $l['unit_price'];
                $pl->item_tax = 0;
                $pl->tax_id = null;
                $pl->save();
            }

            if ($attached) {
                $transaction->additional_notes = trim($notes . '. Lines already on the order logged before this invoice were put on that order: '
                    . implode(', ', array_map(function ($a) { return '#' . $a['transaction_id'] . ' ' . $a['ref_no']; }, $attached)));
                $transaction->save();
            }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $stamp = now()->toDateTimeString();
        Storage::disk('local')->put('admin-snapshots/' . $cfg['action'] . '_' . $invoiceKey . $suffix . '_' . now()->format('Ymd_His') . '.json', json_encode([
            'action' => $cfg['action'],
            'timestamp' => $stamp,
            'direction' => $cfg['label'] . " invoice {$invoiceKey}{$suffix} → " . ($newPurchase ? "purchase #{$transaction->id} (" . count($clean) . ' lines, ' . count($created) . ' new products)' : 'added to existing order(s) only'),
            'business_id' => $business_id,
            'invoice' => $invoiceKey . $suffix,
            'transaction_id' => $newPurchase ? $transaction->id : null,
            'created_products' => $created,
            'attached_to_orders' => $attached,
        ], JSON_PRETTY_PRINT));

        if ($writeSidecar) Storage::disk('local')->put(self::sidecarPath($distributor, $invoiceKey), json_encode([
            'invoice' => $invoiceKey,
            'transaction_id' => $transaction->id,
            'line_count' => count($clean),
            'new_products' => count($created),
            'total' => $total,
            'invoice_date' => substr(self::normalizeDate($invoiceDate), 0, 10),
            'location_id' => (int) $location_id,
            'applied_at' => $stamp,
            'applied_by' => $user_id,
        ], JSON_PRETTY_PRINT));

        return ['transaction' => $transaction, 'created_products' => $created, 'lines' => count($clean), 'total' => $total, 'attached' => $attached, 'new_purchase' => $newPurchase];
    }

    /**
     * An order logged in the ERP before its invoice exists (e.g. ref
     * "ALLIANCE-ORD320180114516A1", "Alliance 275142054736a1", "AMS-ORDER-1627466").
     * Only purchases still Ordered/Pending count; received ones moved stock.
     */
    public static function isOpenOrderPlaceholder($status, $ref)
    {
        if (!in_array(strtolower((string) $status), ['ordered', 'pending'], true)) return false;
        $ref = strtoupper((string) $ref);
        return (bool) (preg_match('/(^|[^A-Z])ORD(ER)?[-_ ]?\d/', $ref) || preg_match('/\d{12}A1\b/', $ref));
    }

    /**
     * Put invoice lines onto orders logged before the invoice existed (same
     * supplier + store, still Ordered/Pending, oldest first) instead of
     * creating a second purchase for them. The order line takes the invoice
     * cost and the order's notes get the invoice #, so the invoice is never
     * imported again. Nothing is deleted or reduced. Invoice qty beyond what
     * the order holds stays in 'remaining' and goes on a new purchase.
     */
    private static function attachToOrderPlaceholders($business_id, $contact_id, $location_id, $invoiceDate, array $clean, $label, $invoiceKey)
    {
        $d = self::normalizeDate($invoiceDate);
        $from = date('Y-m-d', strtotime($d . ' -120 days'));
        $to = date('Y-m-d 23:59:59', strtotime($d . ' +10 days'));
        $remaining = []; $byTx = []; $used = [];
        foreach ($clean as $l) {
            $left = (float) $l['qty'];
            $cands = DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->where('t.business_id', $business_id)->where('t.type', 'purchase')
                ->where('t.contact_id', $contact_id)->where('t.location_id', $location_id)
                ->whereIn('t.status', ['ordered', 'pending'])
                ->whereBetween('t.transaction_date', [$from, $to])
                ->where('pl.variation_id', (int) $l['variation_id'])
                ->orderBy('t.transaction_date')->orderBy('pl.id')
                ->get(['pl.id', 'pl.quantity', 'pl.purchase_price', 't.id as tx_id', 't.ref_no', 't.status', 't.additional_notes']);
            foreach ($cands as $c) {
                if ($left <= 0) break;
                if (isset($used[$c->id]) || !self::isOpenOrderPlaceholder($c->status, $c->ref_no)) continue;
                // An order already matched to another invoice is not reused.
                if (stripos((string) $c->additional_notes, $label . ' invoice ') !== false && stripos((string) $c->additional_notes, 'invoiced on') !== false) continue;
                $used[$c->id] = true;
                $left -= min($left, (float) $c->quantity);
                $unit = round((float) $l['unit_price'], 4);
                if (abs($unit - (float) $c->purchase_price) > 0.0001) {
                    $diff = round(((float) $c->quantity) * ($unit - (float) $c->purchase_price), 2);
                    DB::table('purchase_lines')->where('id', $c->id)->update([
                        'purchase_price' => $unit, 'purchase_price_inc_tax' => $unit, 'pp_without_discount' => $unit,
                    ]);
                    DB::table('transactions')->where('id', $c->tx_id)->update([
                        'total_before_tax' => DB::raw('total_before_tax + (' . $diff . ')'),
                        'final_total' => DB::raw('final_total + (' . $diff . ')'),
                    ]);
                }
                $byTx[(int) $c->tx_id] = ['transaction_id' => (int) $c->tx_id, 'ref_no' => $c->ref_no];
            }
            if ($left > 0) { $r = $l; $r['qty'] = $left; $remaining[] = $r; }
        }
        foreach ($byTx as $k => $a) {
            $n = DB::table('transactions')->where('id', $k)->value('additional_notes');
            DB::table('transactions')->where('id', $k)->update([
                'additional_notes' => trim(((string) $n) . ' / ' . $label . ' invoice ' . $invoiceKey . ' invoiced on ' . substr($d, 0, 10)),
            ]);
        }
        return ['remaining' => $remaining, 'attached' => array_values($byTx)];
    }

    // Add catalog lines to a purchase that is still Ordered (no stock moves):
    // used when an auto-imported invoice left lines off under the old rule.
    public static function appendLines($business_id, $transaction_id, array $lines)
    {
        $tx = DB::table('transactions')->where('business_id', $business_id)->where('id', $transaction_id)->where('type', 'purchase')->first();
        if (!$tx || $tx->status === 'received') throw new \RuntimeException('purchase #' . $transaction_id . ' is missing or already received');
        $added = 0; $total = 0.0;
        DB::beginTransaction();
        try {
            foreach ($lines as $l) {
                $vid = (int) ($l['variation_id'] ?? 0);
                $pid = $vid ? DB::table('variations')->where('id', $vid)->value('product_id') : null;
                if (!$pid || (float) $l['qty'] <= 0) continue;
                $pl = new PurchaseLine();
                $pl->transaction_id = $transaction_id;
                $pl->product_id = (int) $pid;
                $pl->variation_id = $vid;
                $pl->quantity = (float) $l['qty'];
                $pl->purchase_price = round((float) $l['unit_price'], 4);
                $pl->purchase_price_inc_tax = round((float) $l['unit_price'], 4);
                $pl->pp_without_discount = round((float) $l['unit_price'], 4);
                $pl->item_tax = 0;
                $pl->tax_id = null;
                $pl->save();
                $added++;
                $total += (float) $l['qty'] * (float) $l['unit_price'];
            }
            if ($added) {
                DB::table('transactions')->where('id', $transaction_id)->update([
                    'total_before_tax' => DB::raw('total_before_tax + ' . round($total, 2)),
                    'final_total' => DB::raw('final_total + ' . round($total, 2)),
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return [$added, round($total, 2)];
    }

    // Undo: delete the purchase (only while not received) and every product
    // the import created that nothing else has touched since (no other
    // purchase/sale line, no stock). Products in use are left as they are.
    public static function undo(array $data)
    {
        $txId = (int) ($data['transaction_id'] ?? 0);
        $purchase = $txId ? DB::table('transactions')->where('id', $txId)->where('type', 'purchase')->first() : null;
        if ($purchase && ($purchase->status ?? '') === 'received') {
            return [false, "Purchase #{$txId} was received (stock involved) — delete it from the Purchases page instead."];
        }
        $removedProducts = 0; $keptProducts = 0; $lines = 0;
        DB::beginTransaction();
        try {
            if ($purchase) {
                $lines = DB::table('purchase_lines')->where('transaction_id', $txId)->delete();
                DB::table('transactions')->where('id', $txId)->where('type', 'purchase')->delete();
            }
            foreach ($data['created_products'] ?? [] as $cp) {
                $pid = (int) ($cp['product_id'] ?? 0);
                if (!$pid) continue;
                $used = DB::table('purchase_lines')->where('product_id', $pid)->exists()
                    || DB::table('transaction_sell_lines')->where('product_id', $pid)->exists()
                    || DB::table('variation_location_details')->where('product_id', $pid)->where('qty_available', '!=', 0)->exists();
                if ($used) { $keptProducts++; continue; }
                DB::table('variation_location_details')->where('product_id', $pid)->delete();
                DB::table('variations')->where('product_id', $pid)->delete();
                DB::table('product_variations')->where('product_id', $pid)->delete();
                DB::table('product_locations')->where('product_id', $pid)->delete();
                DB::table('products')->where('id', $pid)->delete();
                $removedProducts++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return [false, 'Undo failed, nothing changed: ' . $e->getMessage()];
        }
        $msg = $purchase ? "Deleted purchase #{$txId} + {$lines} line(s)" : "Purchase #{$txId} was already gone";
        if ($removedProducts || $keptProducts) {
            $msg .= "; removed {$removedProducts} product(s) the import created" . ($keptProducts ? ", kept {$keptProducts} that are in use" : '');
        }
        return [true, $msg . '.'];
    }

    private static function likeEscape($s)
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $s);
    }

    public static function subCategoryId($business_id, $parentId, $name)
    {
        $id = DB::table('categories')->where('business_id', $business_id)->whereNull('deleted_at')
            ->where('parent_id', $parentId)->where('name', $name)->value('id');
        return $id ? (int) $id : null;
    }

    // Lowercase letters/digits only, minus format noise ("(2x12\")", "EP",
    // "Vol." / "Vol") so "Vol 1 (2x12\")" and "Vol. 1" compare equal.
    public static function titleKey($s)
    {
        $s = mb_strtolower(str_replace("\u{00AD}", '-', (string) $s));
        $s = preg_replace('/\((?:\d+x)?(?:7|10|12)(?:"|\'\'|inch|in)?\)|\b(?:ep|lp|vol)\b\.?|\bvolume\b/u', ' ', $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
        return $s;
    }

    /**
     * Product for a catalog-number line (no barcode): first a product this
     * importer created for the same catalog number ("...: SW10 - ..." in its
     * description), then one whose artist matches and whose title (name minus
     * an "Artist - " or "Genre - " prefix) matches. Prefers active, the
     * invoice's category (sealed vinyl), then newest. Null when unsure.
     */
    const CATALOG_COLS = ['v.id as variation_id', 'v.product_id', 'v.sub_sku', 'v.default_purchase_price',
        'v.sell_price_inc_tax', 'p.name as product_name', 'p.is_inactive', 'p.category_id', 'p.artist', 'p.product_description'];

    /**
     * Everything matchCatalog needs for a whole invoice in two queries (one
     * per line was ~1s each: LIKE scans of products). Products this importer
     * made for any of the catalog numbers, and products whose artist is (or
     * whose name starts with) any of the invoice's artists.
     */
    public static function catalogPool($business_id, array $lines)
    {
        $catnos = []; $artists = [];
        foreach ($lines as $l) {
            $c = trim((string) ($l['catno'] ?? ''));
            if ($c === '') continue;
            $catnos[$c] = true;
            $a = trim((string) ($l['artist'] ?? ''));
            if ($a !== '') $artists[$a] = true;
        }
        if (!$catnos) return null;
        $base = function () use ($business_id) {
            return DB::table('variations as v')->join('products as p', 'v.product_id', '=', 'p.id')
                ->where('p.business_id', $business_id)->whereNull('v.deleted_at');
        };
        $byCat = $base()->whereIn('p.added_via', ['vinylfuture_invoice', 'matador_invoice'])->where(function ($q) use ($catnos) {
            foreach (array_keys($catnos) as $c) $q->orWhere('p.product_description', 'like', '%: ' . self::likeEscape($c) . ' - %');
        })->limit(2000)->get(self::CATALOG_COLS);
        $byArtist = $artists ? $base()->where(function ($q) use ($artists) {
            $q->whereIn('p.artist', array_keys($artists));
            foreach (array_keys($artists) as $a) $q->orWhere('p.name', 'like', self::likeEscape($a) . '%');
        })->limit(5000)->get(self::CATALOG_COLS) : collect();
        return ['cat' => $byCat, 'artist' => $byArtist];
    }

    public static function matchCatalog($business_id, $catno, $artist, $title, $catId, $pool = null)
    {
        $cols = self::CATALOG_COLS;
        $base = function () use ($business_id) {
            return DB::table('variations as v')->join('products as p', 'v.product_id', '=', 'p.id')
                ->where('p.business_id', $business_id)->whereNull('v.deleted_at');
        };
        $pick = function ($rows) use ($catId) {
            $best = null; $bestScore = null;
            foreach ($rows as $r) {
                $score = [(int) !$r->is_inactive, (int) ($catId && (int) $r->category_id === $catId), (int) $r->product_id];
                if ($bestScore === null || $score > $bestScore) { $best = $r; $bestScore = $score; }
            }
            return $best;
        };

        $catno = trim((string) $catno);
        if ($catno !== '') {
            $rows = $pool !== null
                ? $pool['cat']->filter(function ($r) use ($catno) { return stripos((string) $r->product_description, ': ' . $catno . ' - ') !== false; })
                : $base()->where('p.product_description', 'like', '%: ' . self::likeEscape($catno) . ' - %')->limit(20)->get($cols);
            $hit = $pick($rows);
            if ($hit) return $hit;
        }

        $artist = trim((string) $artist);
        $tKey = self::titleKey($title);
        if ($artist === '' || strlen($tKey) < 3) return null;
        $aKey = self::titleKey($artist);
        $rows = $pool !== null ? $pool['artist'] : $base()->where(function ($q) use ($artist) {
            $q->where('p.artist', $artist)->orWhere('p.name', 'like', self::likeEscape($artist) . '%');
        })->limit(300)->get($cols);
        $hits = [];
        foreach ($rows as $r) {
            $name = (string) $r->product_name;
            $artistOk = self::titleKey($r->artist) === $aKey || stripos($name, $artist) === 0;
            if (!$artistOk) continue;
            // Name forms in the catalog: "Artist - Title", "Genre - Title" (mass add).
            $parts = preg_split('/\s+-\s+/u', $name, 2);
            $nTitle = count($parts) === 2 ? $parts[1] : $name;
            $nKey = self::titleKey($nTitle);
            if ($nKey === $tKey || ($nKey !== '' && strlen($tKey) >= 8 && strlen($nKey) >= 8
                && (strpos($nKey, $tKey) === 0 || strpos($tKey, $nKey) === 0))) {
                $hits[] = $r;
            }
        }
        return $pick($hits);
    }

    public static function normalizeDate($d)
    {
        $d = trim((string) $d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d . ' 00:00:00';
        }
        return now()->toDateTimeString();
    }
}
