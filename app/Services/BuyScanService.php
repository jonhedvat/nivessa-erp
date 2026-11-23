<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Buy form "Scan or paste" box (Jon 11/23): a UPC or a Discogs release
 * ID/link -> title, format and the buy item type whose rate applies.
 *
 * Order: our own products by barcode SKU first (best genre/format signal and
 * "we already carry it"), then Discogs (barcode search or the release), else
 * nothing — the cashier picks the type. Never falls back to a vinyl rate.
 */
class BuyScanService
{
    /** @var array item type keys that exist (dropdown) */
    protected $types;

    public function __construct(array $types = null)
    {
        $this->types = $types ?? app(BuyOfferCalculatorService::class)->getRules()['item_types'];
    }

    public static function digits($code)
    {
        return preg_replace('/[\s-]+/', '', (string) $code);
    }

    public static function isUpc($code)
    {
        return (bool) preg_match('/^\d{8,14}$/', self::digits($code));
    }

    /** Discogs release id from a bare id or a /release/123 link; null otherwise. */
    public static function discogsReleaseId($q)
    {
        $q = trim((string) $q);
        if (preg_match('#discogs\.com/.*release/(\d+)#i', $q, $m)) return (int) $m[1];
        if (preg_match('#^\[?r(\d+)\]?$#i', $q, $m)) return (int) $m[1];
        // Bare number under 8 digits = release id (UPC/EAN are 8+ digits).
        if (preg_match('/^\d{1,7}$/', $q)) return (int) $q;
        return null;
    }

    public function lookup($business_id, $q)
    {
        $q = trim((string) $q);
        $out = [
            'found' => false, 'source' => 'none', 'upc' => null, 'discogs_release_id' => null,
            'title' => '', 'artist' => '', 'format' => null, 'format_label' => null, 'genre' => null,
            'sealed' => false, 'item_type_used' => null, 'item_type_sealed' => null, 'ours' => [],
        ];
        if ($q === '') return $out;

        $releaseId = self::discogsReleaseId($q);
        if (!$releaseId && self::isUpc($q)) {
            $upc = self::digits($q);
            $out['upc'] = $upc;
            $ours = $this->ourProductsByUpc($business_id, $upc);
            $out['ours'] = $ours;
            if ($ours) {
                $p = $ours[0];
                $out['found'] = true;
                $out['source'] = 'ours';
                $out['title'] = $p['name'];
                $out['artist'] = (string) $p['artist'];
                $out['format'] = $p['format'];
                $out['genre'] = $p['genre'];
                $out['sealed'] = $p['condition'] === 'sealed';
                $this->setTypes($out, $p['format'], $p['genre_text'], '');
                return $out;
            }
            $hit = $this->discogsByBarcode($business_id, $upc);
            if ($hit) {
                $out = array_merge($out, $hit, ['found' => true, 'source' => 'discogs']);
                return $out;
            }
            return $out;
        }
        if ($releaseId) {
            $hit = $this->discogsRelease($business_id, $releaseId);
            if ($hit) {
                $out = array_merge($out, $hit, ['found' => true, 'source' => 'discogs']);
                // A release's own barcode -> do we carry it?
                if (!empty($hit['upc'])) $out['ours'] = $this->ourProductsByUpc($business_id, $hit['upc']);
            }
            $out['discogs_release_id'] = $releaseId;
        }
        return $out;
    }

    /**
     * Active products whose barcode SKU matches (leading zeros, spaces and
     * dashes ignored — same rule as the shelf count scan box).
     */
    public function ourProductsByUpc($business_id, $upc)
    {
        $key = ltrim(self::digits($upc), '0');
        if (strlen($key) < 7) return [];
        $rows = DB::table('products as p')
            ->join('variations as va', 'va.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('categories as s', 's.id', '=', 'p.sub_category_id')
            ->where('p.business_id', $business_id)->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)->whereNull('va.deleted_at')
            ->where(function ($x) use ($key) {
                $x->whereRaw("TRIM(LEADING '0' FROM REPLACE(REPLACE(p.sku,' ',''),'-','')) = ?", [$key])
                  ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(REPLACE(va.sub_sku,' ',''),'-','')) = ?", [$key]);
            })
            ->select('p.id', 'p.name', 'p.artist', 'p.type', 'va.id as variation_id', 'va.sell_price_inc_tax as price', 'c.name as cat', 's.name as sub')
            ->limit(10)->get();
        if ($rows->isEmpty()) return [];

        $ids = $rows->pluck('id')->unique()->all();
        $stock = [];
        foreach (DB::table('variation_location_details as v')->join('business_locations as l', 'l.id', '=', 'v.location_id')
            ->whereIn('v.product_id', $ids)->groupBy('v.product_id', 'l.name')
            ->select('v.product_id', 'l.name', DB::raw('SUM(v.qty_available) as q'))->get() as $s) {
            $stock[(int) $s->product_id][$s->name] = (float) $s->q;
        }
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->id])) continue;
            $seen[$r->id] = true;
            $catText = trim($r->cat . ' ' . $r->sub);
            $out[] = [
                'id' => (int) $r->id,
                'variation_id' => (int) $r->variation_id,
                'single' => $r->type === 'single',
                'name' => $r->name,
                'artist' => $r->artist,
                'category' => trim($r->cat . ($r->sub ? ' / ' . $r->sub : '')),
                'format' => self::formatFromText($r->cat . ' ' . $r->sub),
                'condition' => self::conditionFromText($r->cat) ?: self::conditionFromText($r->sub),
                'genre' => $r->sub,
                'genre_text' => $catText,
                'price' => $r->price !== null ? round((float) $r->price, 2) : null,
                'stock' => $stock[(int) $r->id] ?? [],
            ];
        }
        return $out;
    }

    protected function discogsByBarcode($business_id, $upc)
    {
        try {
            $svc = new DiscogsService($business_id);
            if (!$svc->isConfigured()) return null;
            // Scanned as a 12-digit UPC or a 13-digit EAN with a leading 0; Discogs stores either.
            $tries = array_values(array_unique(array_filter([$upc, ltrim($upc, '0'), '0' . $upc],
                function ($c) { return preg_match('/^\d{8,14}$/', $c); })));
            $hits = [];
            foreach ($tries as $n => $c) {
                if ($n > 0) usleep(300000);
                $res = $svc->searchByBarcode($c);
                $hits = (array) ($res['data']->results ?? []);
                if ($hits) break;
            }
            if (!$hits) return null;
            // The pressing most people own.
            usort($hits, function ($a, $b) { return ((int) ($b->community->have ?? 0)) <=> ((int) ($a->community->have ?? 0)); });
            $h = $hits[0];
            $parts = explode(' - ', (string) ($h->title ?? ''), 2);
            $artist = trim(preg_replace('/\s*\(\d+\)$/', '', $parts[0]));
            $title = trim($parts[1] ?? $parts[0]);
            $fmtText = implode(' ', (array) ($h->format ?? []));
            $genreText = implode(' ', array_merge((array) ($h->genre ?? []), (array) ($h->style ?? [])));
            $out = [
                'discogs_release_id' => (int) $h->id,
                'title' => $artist !== '' && $artist !== $title ? $artist . ' - ' . $title : $title,
                'artist' => $artist,
                'image' => $h->cover_image ?? ($h->thumb ?? null),
            ];
            return $out + $this->discogsTyping($fmtText, $genreText, $title);
        } catch (\Throwable $e) {
            \Log::warning('buy scan barcode ' . $upc . ': ' . $e->getMessage());
            return null;
        }
    }

    protected function discogsRelease($business_id, $releaseId)
    {
        try {
            $svc = new DiscogsService($business_id);
            if (!$svc->isConfigured()) return null;
            $rel = $svc->getReleaseById($releaseId);
            $d = $rel['data'] ?? null;
            if (!empty($rel['error']) || !is_object($d)) return null;
            $artist = trim(preg_replace('/\s*\(\d+\)$/', '', (string) ($d->artists[0]->name ?? '')));
            $title = (string) ($d->title ?? '');
            $fmt = [];
            foreach ((array) ($d->formats ?? []) as $f) {
                $fmt[] = $f->name ?? '';
                foreach ((array) ($f->descriptions ?? []) as $x) $fmt[] = $x;
            }
            $upc = null;
            foreach ((array) ($d->identifiers ?? []) as $ident) {
                if (($ident->type ?? '') === 'Barcode' && self::isUpc($ident->value ?? '')) { $upc = self::digits($ident->value); break; }
            }
            $genreText = implode(' ', array_merge((array) ($d->genres ?? []), (array) ($d->styles ?? [])));
            $out = [
                'title' => $artist !== '' ? $artist . ' - ' . $title : $title,
                'artist' => $artist,
                'image' => $d->thumb ?? null,
                'upc' => $upc,
            ];
            return $out + $this->discogsTyping(implode(' ', $fmt), $genreText, $title);
        } catch (\Throwable $e) {
            \Log::warning('buy scan release ' . $releaseId . ': ' . $e->getMessage());
            return null;
        }
    }

    protected function discogsTyping($fmtText, $genreText, $title)
    {
        $format = self::formatFromText($fmtText);
        // Discogs "DVD" is a music DVD (concert/doc) — that's its own rate.
        if (in_array($format, ['dvd', 'bluray'], true) && !preg_match('/stage & screen/i', $genreText)) {
            $genreText = 'music';
        }
        $out = ['format' => $format, 'genre' => self::genreKey($genreText), 'sealed' => false];
        $this->setTypes($out, $format, $genreText, $fmtText . ' ' . $title);
        return $out;
    }

    protected function setTypes(array &$out, $format, $genreText, $extra)
    {
        $out['format_label'] = self::formatLabel($format);
        $out['item_type_used'] = $this->itemTypeFor($format, $genreText, false, $extra);
        $out['item_type_sealed'] = $this->itemTypeFor($format, $genreText, true, $extra);
    }

    public static function formatLabel($format)
    {
        $map = ['vinyl' => 'Vinyl', 'rpm45' => '7" / 45', 'cd' => 'CD', 'cassette' => 'Cassette', 'dvd' => 'DVD',
            'bluray' => 'Blu-ray', '4k' => '4K', 'vhs' => 'VHS', 'laserdisc' => 'LaserDisc', 'videogame' => 'Video game', 'book' => 'Book'];
        return $map[$format] ?? null;
    }

    public static function formatFromText($text)
    {
        $t = mb_strtolower((string) $text);
        // A combined "DVD / Blu-ray" category can't say which: let the cashier pick.
        if (preg_match('/blu.?ray/', $t) && preg_match('/\bdvd/', $t)) return null;
        if (preg_match('/4k|ultra hd|uhd/', $t)) return '4k';
        if (preg_match('/blu.?ray/', $t)) return 'bluray';
        if (preg_match('/\bdvd/', $t)) return 'dvd';
        if (preg_match('/\bvhs\b/', $t)) return 'vhs';
        if (preg_match('/laser.?disc/', $t)) return 'laserdisc';
        if (preg_match('/video ?game|playstation|nintendo|xbox|\bps[1-5]\b/', $t)) return 'videogame';
        if (preg_match('/cassette|\btapes?\b/', $t)) return 'cassette';
        if (preg_match('/\bcds?\b|\bcd-?r\b|compact dis|\bsacd\b/', $t)) return 'cd';
        if (preg_match('/7"|\b7 ?inch|\b45\b|45 rpm/', $t)) return 'rpm45';
        if (preg_match('/vinyl|\blp\b|\b12"|record/', $t)) return 'vinyl';
        if (preg_match('/\bbooks?\b/', $t)) return 'book';
        return null;
    }

    public static function conditionFromText($text)
    {
        $t = mb_strtolower((string) $text);
        if (strpos($t, 'sealed') !== false) return 'sealed';
        if (preg_match('/\bused\b/', $t)) return 'used';
        // "New Wave" is a genre, not a condition.
        if (preg_match('/\bnew\b(?!\s*wave)/', $t)) return 'sealed';
        return null;
    }

    public static function genreKey($text)
    {
        $t = mb_strtolower((string) $text);
        $rules = [
            'horror' => '/horror/', 'scifi' => '/sci.?fi|science fiction/', 'animation' => '/anim|cartoon|anime/',
            'documentary' => '/documentar/', 'comedy' => '/comedy/', 'family' => '/family|kids|children/',
            'tv' => '/\btv\b|television/', 'musical' => '/musical/', 'romance' => '/romance/',
            'thriller' => '/thriller|crime|mystery/', 'fantasy' => '/fantasy/', 'action' => '/action|adventure|war\b|western/',
            'drama' => '/drama/',
            'soundtracks' => '/soundtrack|stage & screen|score/', 'classical' => '/classical|opera/',
            'hiphop' => '/hip.?hop|rap\b/', 'metal' => '/metal|grindcore/', 'punk' => '/punk|hardcore|emo\b/',
            'electronic' => '/electronic|house|techno|dance|trance|drum n bass|edm/', 'rnb' => '/r&b|rnb|soul|funk/',
            'jazz' => '/jazz/', 'blues' => '/blues/', 'country' => '/country/', 'latin' => '/latin/', 'reggae' => '/reggae|dub\b/',
            'folk' => '/folk/', 'pop' => '/\bpop\b|k-?pop/', 'rock' => '/rock|alternative|indie|new wave|grunge|shoegaze/',
            'music' => '/\bmusic\b|concert/',
        ];
        foreach ($rules as $k => $re) {
            if (preg_match($re, $t)) return $k;
        }
        return null;
    }

    protected function first(array $keys)
    {
        foreach ($keys as $k) {
            if ($k && isset($this->types[$k]) && empty($this->types[$k]['retired'])) return $k;
        }
        return null;
    }

    /** Buy item type for a format + genre + sealed/used. Null = cashier picks. */
    public function itemTypeFor($format, $genreText, $sealed, $extra = '')
    {
        $g = self::genreKey($genreText);
        $c = $sealed ? 'sealed' : 'used';
        $x = mb_strtolower((string) $extra);
        switch ($format) {
            case 'cd':
                if (preg_match('/box ?set/', $x)) return $this->first(["cd_boxset_$c"]);
                $map = ['hiphop' => 'hiphop', 'metal' => 'metal', 'punk' => 'punk', 'pop' => 'pop', 'electronic' => 'electronic',
                    'blues' => 'blues', 'country' => 'country', 'rock' => 'rock_alt_newwave', 'rnb' => 'rb_jazz', 'jazz' => 'rb_jazz',
                    'soundtracks' => 'soundtracks', 'classical' => 'classical_opera'];
                return $this->first([isset($map[$g]) ? 'cd_' . $map[$g] . '_' . $c : null, "cd_general_$c"]);
            case 'cassette':
                if ($sealed) return $this->first(['cassette_everything_else']);
                $map = ['hiphop' => 'hiphop', 'metal' => 'metal', 'punk' => 'punk', 'rnb' => 'rnb', 'rock' => 'rock', 'jazz' => 'jazz'];
                return $this->first([isset($map[$g]) ? 'cassette_' . $map[$g] . '_used' : null, 'cassette_everything_else']);
            case 'dvd':
                if ($sealed) return $this->first(['dvd_sealed']);
                if (strpos($x, 'criterion') !== false) return $this->first(['dvd_criterion_used']);
                if (preg_match('/box ?set|complete series|season/', $x)) return $this->first(['dvd_boxset_used', 'dvd_used']);
                return $this->first([$g ? "dvd_{$g}_used" : null, 'dvd_used']);
            case 'bluray':
                return $this->first(["bluray_$c"]);
            case '4k':
                return $this->first(['4k_used', 'bluray_' . $c]);
            case 'vhs':
                return $this->first([$g === 'horror' ? 'vhs_horror_used' : null, 'vhs_used']);
            case 'laserdisc':
                return $this->first(['laserdisc']);
            case 'videogame':
                return $this->first(['videogame_with_case']);
            case 'rpm45':
                $map = ['hiphop' => 'rpm45_hh_metal_punk', 'metal' => 'rpm45_hh_metal_punk', 'punk' => 'rpm45_hh_metal_punk',
                    'rock' => 'rpm45_newwave_rock_alt', 'rnb' => 'rpm45_rnb'];
                return $this->first([$map[$g] ?? null, 'rpm45_anything_else']);
            case 'vinyl':
                // One scanned record = priced on its own Discogs median.
                return $this->first(['individual_vinyl']);
            case 'book':
                return $this->first(['book']);
        }
        return null;
    }

    /** Format a buy item type is for, from its key. */
    public static function formatOfItemType($itemType)
    {
        $t = (string) $itemType;
        foreach (['cd_' => 'cd', 'cassette_' => 'cassette', 'dvd_' => 'dvd', 'bluray_' => 'bluray', '4k_' => '4k',
                     'vhs_' => 'vhs', 'rpm45_' => 'rpm45', 'bulk_vinyl_' => 'vinyl', 'individual_vinyl' => 'vinyl'] as $p => $f) {
            if (strpos($t, $p) === 0) return $f;
        }
        return null;
    }

    /**
     * The one product we already carry that this bought line is another copy
     * of: same barcode, same format, same sealed/used. Anything less certain
     * (two candidates, a category that doesn't say used/sealed, a used copy of
     * something we only stock sealed) returns null and the item is listed new.
     */
    public function carriedProductFor($business_id, $upc, $itemType, $grade = null)
    {
        if (!self::isUpc($upc)) return null;
        $format = self::formatOfItemType($itemType);
        if (!$format) return null;
        $sealed = strpos((string) $itemType, 'sealed') !== false
            || in_array(mb_strtolower(trim((string) $grade)), ['m', 'mint', 'mint (m)', 'sealed'], true);
        // Used records are one of a kind (graded and priced each) — always listed on their own.
        if (!$sealed && in_array($format, ['vinyl', 'rpm45'], true)) return null;
        $want = $sealed ? 'sealed' : 'used';
        $hits = array_values(array_filter($this->ourProductsByUpc($business_id, $upc), function ($p) use ($format, $want) {
            return $p['single'] && $p['format'] === $format && $p['condition'] === $want;
        }));
        return count($hits) === 1 ? $hits[0] : null;
    }
}
