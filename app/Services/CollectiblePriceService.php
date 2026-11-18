<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sarah 2026-11-17: what a single Pokemon / Magic / Yu-Gi-Oh! card or Funko
 * Pop is worth, so the website quote pays PAY (20%) of real value on $10+
 * items instead of 5c bulk.
 *
 * Cards: our own `collectible_prices` table, filled weekly by
 * `php artisan collectibles:import` from free public price lists
 *   - Pokemon: pokemontcg.io (TCGplayer market price per printing/variant)
 *   - Magic: Scryfall "default cards" bulk file (TCGplayer usd / usd_foil)
 *   - Yu-Gi-Oh!: YGOPRODeck card dump (price per set printing)
 * Funko: no free price list exists, so a lookup asks eBay (current Buy It
 * Now asks x ASKING_TO_VALUE, like signed items) and saves the answer in the
 * same table for FUNKO_FRESH_DAYS.
 *
 * Lookups never guess high: when the description matches several printings
 * whose prices are far apart, it says "tell us the set" (priced in store).
 */
class CollectiblePriceService
{
    const PAY = 0.20;
    const MIN_VALUE = 10;
    /** Matching printings within this ratio count as one price (median). */
    const SPREAD_OK = 2.0;
    const FUNKO_FRESH_DAYS = 14;
    const ASKING_TO_VALUE = 0.70;
    const MIN_LISTINGS = 3;

    const GAMES = ['pokemon', 'mtg', 'yugioh', 'funko'];
    const SOURCE_LABEL = ['pokemon' => 'TCGplayer', 'mtg' => 'TCGplayer (Scryfall)', 'yugioh' => 'TCGplayer (YGOPRODeck)', 'funko' => 'eBay'];

    const UA = 'NivessaERP/1.0 (buy quotes)';

    public static function norm($s)
    {
        $s = mb_strtolower((string) $s);
        $s = str_replace(['’', "'", '`'], '', $s);
        $s = preg_replace('/[^\p{L}\p{N}\/]+/u', ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    // ------------------------------------------------------------------
    // Lookup
    // ------------------------------------------------------------------

    /**
     * @return array ok, value, label, source, search_url, msg
     *   ok=false + msg when nothing / too many different matches.
     */
    public function lookup($game, $description, $allowRemote = true, $businessId = null)
    {
        $out = ['ok' => false, 'value' => 0, 'label' => '', 'source' => self::SOURCE_LABEL[$game] ?? '', 'search_url' => '', 'msg' => ''];
        $desc = self::norm($description);
        if (mb_strlen($desc) < 3) {
            $out['msg'] = 'Type the name to get a price.';
            return $out;
        }
        $out['search_url'] = $this->ebaySoldUrl($game, $description);
        if ($game === 'funko') {
            return $this->lookupFunko($desc, $description, $allowRemote, $businessId, $out);
        }

        $words = array_slice(explode(' ', $desc), 0, 12);
        $grams = [];
        for ($i = 0; $i < count($words); $i++) {
            for ($j = $i; $j < count($words) && $j < $i + 8; $j++) {
                $grams[implode(' ', array_slice($words, $i, $j - $i + 1))] = true;
            }
        }
        $names = DB::table('collectible_prices')->where('game', $game)
            ->whereIn('name_norm', array_keys($grams))
            ->distinct()->pluck('name_norm')->all();
        if (!$names) {
            $out['msg'] = "We couldn't find that card. Check the spelling, or we'll price it in store.";
            return $out;
        }
        // Longest name wins ("charizard ex" over "charizard").
        usort($names, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
        $name = $names[0];
        $rest = trim(preg_replace('/(^| )' . preg_quote($name, '/') . '( |$)/u', ' ', $desc, 1));
        $restTokens = array_values(array_filter(explode(' ', $rest), function ($t) {
            return mb_strlen($t) >= 2 && !in_array($t, ['the', 'card', 'cards', 'pokemon', 'magic', 'mtg', 'yugioh', 'yu', 'gi', 'oh', 'tcg', 'set', 'of', 'and', 'english'], true);
        }));

        $rows = DB::table('collectible_prices')->where('game', $game)->where('name_norm', $name)->where('price', '>', 0)->get();
        $best = [];
        $bestScore = -1;
        foreach ($rows as $r) {
            $hay = ' ' . self::norm($r->set_name . ' ' . $r->set_code . ' ' . $r->variant) . ' ';
            $num = self::norm($r->number);
            $score = 0;
            foreach ($restTokens as $t) {
                // "4/102" or "4" = card number; "lob en001" = set code
                $tn = preg_replace('/\/.*$/', '', $t);
                if ($num !== '' && ($tn === $num || ltrim($tn, '0') === ltrim($num, '0'))) {
                    $score += 3;
                } elseif (mb_strpos($hay, ' ' . $t . ' ') !== false) {
                    $score += 2;
                } elseif (mb_strlen($t) >= 4 && mb_strpos($hay, $t) !== false) {
                    $score += 1;
                }
            }
            // Only say "holo / foil / 1st edition" when the seller did.
            $v = self::norm($r->variant);
            if (preg_match('/1st|first/', $v) && !preg_match('/\b(1st|first)\b/', $rest)) $score -= 2;
            if (preg_match('/foil|holo/', $v) && !preg_match('/foil|holo|shiny/', $rest) && $game === 'mtg') $score -= 1;
            if (preg_match('/reverse/', $v) && !preg_match('/reverse/', $rest)) $score -= 1;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$r];
            } elseif ($score === $bestScore) {
                $best[] = $r;
            }
        }
        if (!$best) {
            $out['msg'] = "We couldn't find a price for that card.";
            return $out;
        }
        $prices = array_map(function ($r) { return (float) $r->price; }, $best);
        sort($prices);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        if ($max > self::SPREAD_OK * max($min, 1)) {
            // Several printings at different prices and we can't tell which.
            // Cheap anyway? Then it's under the line whatever printing it is.
            if ($max < self::MIN_VALUE) {
                $min = $max = $prices[0];
            } else {
                $out['msg'] = 'There are several versions of ' . $best[0]->name . ' (' . count($best) . ' printings, $' . number_format($min, 0) . '–$' . number_format($max, 0) . '). Add the set name or card number, or we price it in store.';
                $out['ambiguous'] = true;
                return $out;
            }
        }
        // Lower median: never round a tie up.
        $value = $prices[intdiv(count($prices) - 1, 2)];
        $pick = $best[0];
        foreach ($best as $r) {
            if ((float) $r->price === $value) { $pick = $r; break; }
        }
        $out['ok'] = true;
        $out['value'] = round($value, 2);
        $out['label'] = trim($pick->name . ' · ' . $pick->set_name . ($pick->number ? ' #' . $pick->number : '') . ($pick->variant ? ' · ' . $pick->variant : ''));
        $out['matches'] = count($best);
        $out['source_url'] = $pick->url;
        $out['search_url'] = $this->ebaySoldUrl($game, $pick->name . ' ' . $pick->set_name . ' ' . $pick->number);
        return $out;
    }

    protected function lookupFunko($desc, $raw, $allowRemote, $businessId, array $out)
    {
        $key = trim(preg_replace('/\b(funko|pop|pops|vinyl|figure)\b/u', ' ', $desc));
        $key = trim(preg_replace('/\s+/', ' ', $key));
        if (mb_strlen($key) < 3) {
            $out['msg'] = 'Type which Funko (character, number if you have it).';
            return $out;
        }
        $row = DB::table('collectible_prices')->where('game', 'funko')->where('name_norm', $key)
            ->where('updated_at', '>=', now()->subDays(self::FUNKO_FRESH_DAYS))->first();
        if ($row) {
            if ((float) $row->price <= 0) {
                $out['msg'] = 'Not enough Funko listings on eBay to price it. We price it in store.';
                return $out;
            }
            $out['ok'] = true;
            $out['value'] = (float) $row->price;
            $out['label'] = $row->name;
            $out['source_url'] = $row->url;
            return $out;
        }
        if (!$allowRemote) {
            $out['msg'] = 'pending';
            $out['pending'] = true;
            return $out;
        }
        $q = 'funko pop ' . $key;
        try {
            $res = (new EbayService($businessId ?: 1))->browseSearch([
                'q' => $q,
                'limit' => 100,
                'filter' => 'buyingOptions:{FIXED_PRICE},priceCurrency:USD,price:[3..]',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Funko eBay lookup failed: ' . $e->getMessage());
            $out['msg'] = 'eBay lookup failed. We price it in store.';
            return $out;
        }
        $tokens = array_values(array_filter(explode(' ', $key), function ($t) { return mb_strlen($t) >= 2; }));
        $need = max(1, (int) ceil(count($tokens) * 0.7));
        $prices = [];
        foreach ((array) ($res['itemSummaries'] ?? []) as $it) {
            $t = self::norm($it['title'] ?? '');
            $p = (float) ($it['price']['value'] ?? 0);
            if ($p <= 0 || mb_strpos($t, 'funko') === false || preg_match('/\b(lot|bundle|set of|custom|empty box|box only|protector|case only|read)\b/', $t)) {
                continue;
            }
            $hit = 0;
            foreach ($tokens as $tok) {
                if (preg_match('/(^| )' . preg_quote($tok, '/') . '( |$)/u', $t)) $hit++;
            }
            if ($hit >= $need) $prices[] = $p;
        }
        $value = 0;
        if (count($prices) >= self::MIN_LISTINGS) {
            sort($prices);
            $n = count($prices);
            $median = $n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2;
            $value = round($median * self::ASKING_TO_VALUE, 2);
        }
        DB::table('collectible_prices')->where('game', 'funko')->where('name_norm', $key)->delete();
        DB::table('collectible_prices')->insert([
            'game' => 'funko', 'name' => 'Funko Pop ' . trim($raw), 'name_norm' => $key,
            'price' => $value, 'source' => 'ebay', 'url' => $out['search_url'],
            'variant' => count($prices) . ' listings', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($value <= 0) {
            $out['msg'] = 'Not enough Funko listings on eBay to price it. We price it in store.';
            return $out;
        }
        $out['ok'] = true;
        $out['value'] = $value;
        $out['label'] = 'Funko Pop ' . trim($raw);
        return $out;
    }

    public function ebaySoldUrl($game, $text)
    {
        $prefix = ['pokemon' => 'pokemon card', 'mtg' => 'mtg', 'yugioh' => 'yugioh', 'funko' => 'funko pop'][$game] ?? '';
        $q = trim($prefix . ' ' . preg_replace('/\s+/', ' ', (string) $text));
        return 'https://www.ebay.com/sch/i.html?_nkw=' . rawurlencode($q) . '&LH_Sold=1&LH_Complete=1';
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /** @return int rows imported */
    public function import($game, callable $log = null)
    {
        $log = $log ?: function () {};
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
        $batch = (int) (DB::table('collectible_prices')->where('game', $game)->max('batch') ?? 0) + 1;
        $buf = [];
        $count = 0;
        $flush = function () use (&$buf, &$count) {
            if ($buf) {
                foreach (array_chunk($buf, 500) as $chunk) {
                    DB::table('collectible_prices')->insert($chunk);
                }
                $count += count($buf);
                $buf = [];
            }
        };
        $now = now();
        $add = function ($name, $set, $code, $number, $variant, $price, $source, $url) use (&$buf, $flush, $game, $batch, $now) {
            $price = round((float) $price, 2);
            if ($price <= 0 || trim((string) $name) === '') return;
            $buf[] = [
                'game' => $game,
                'name' => mb_substr($name, 0, 191),
                'name_norm' => mb_substr(self::norm($name), 0, 191),
                'set_name' => mb_substr((string) $set, 0, 191) ?: null,
                'set_code' => mb_substr((string) $code, 0, 64) ?: null,
                'number' => mb_substr((string) $number, 0, 32) ?: null,
                'variant' => mb_substr((string) $variant, 0, 64) ?: null,
                'price' => $price,
                'source' => $source,
                'url' => mb_substr((string) $url, 0, 500) ?: null,
                'batch' => $batch,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($buf) >= 2000) $flush();
        };

        if ($game === 'pokemon') {
            $this->importPokemon($add, $log);
        } elseif ($game === 'mtg') {
            $this->importMtg($add, $log);
        } elseif ($game === 'yugioh') {
            $this->importYugioh($add, $log);
        } else {
            throw new \InvalidArgumentException('Unknown game ' . $game);
        }
        $flush();
        if ($count < 1000) {
            // Something went wrong upstream: keep last week's prices.
            DB::table('collectible_prices')->where('game', $game)->where('batch', $batch)->delete();
            throw new \RuntimeException("Only $count $game prices came back; kept the previous import.");
        }
        DB::table('collectible_prices')->where('game', $game)->where('batch', '<', $batch)->delete();
        $log("$game: $count prices (batch $batch)");
        return $count;
    }

    protected function importPokemon(callable $add, callable $log)
    {
        $page = 1;
        $total = null;
        do {
            $url = 'https://api.pokemontcg.io/v2/cards?pageSize=250&page=' . $page . '&orderBy=id&select=id,name,number,set,rarity,tcgplayer';
            $headers = [];
            if ($key = env('POKEMONTCG_API_KEY')) $headers[] = 'X-Api-Key: ' . $key;
            $d = json_decode($this->get($url, $headers, 8), true);
            if (!is_array($d) || !isset($d['data'])) {
                throw new \RuntimeException("Pokemon page $page failed");
            }
            $total = (int) ($d['totalCount'] ?? 0);
            foreach ($d['data'] as $c) {
                $prices = $c['tcgplayer']['prices'] ?? [];
                foreach ($prices as $variant => $p) {
                    $price = $p['market'] ?? ($p['mid'] ?? null);
                    $add($c['name'], $c['set']['name'] ?? '', $c['set']['ptcgoCode'] ?? ($c['set']['id'] ?? ''),
                        $c['number'] ?? '', $this->pokeVariant($variant, $c['rarity'] ?? ''), $price, 'tcgplayer', $c['tcgplayer']['url'] ?? '');
                }
            }
            if ($page % 10 === 0) $log("pokemon page $page / " . ceil($total / 250));
            $page++;
        } while (($page - 1) * 250 < $total);
    }

    protected function pokeVariant($v, $rarity)
    {
        $map = ['normal' => '', 'holofoil' => 'holo', 'reverseHolofoil' => 'reverse holo', '1stEditionHolofoil' => '1st edition holo',
            '1stEditionNormal' => '1st edition', 'unlimitedHolofoil' => 'unlimited holo', 'unlimitedNormal' => 'unlimited', '1stEdition' => '1st edition', 'unlimited' => 'unlimited'];
        $label = $map[$v] ?? $v;
        return trim($label . ($rarity ? ' ' . $rarity : ''));
    }

    protected function importMtg(callable $add, callable $log)
    {
        $meta = json_decode($this->get('https://api.scryfall.com/bulk-data/default-cards', ['Accept: application/json'], 3), true);
        $src = $meta['jsonl_download_uri'] ?? ($meta['download_uri'] ?? null);
        if (!$src) throw new \RuntimeException('Scryfall bulk-data lookup failed');
        $tmp = tempnam(sys_get_temp_dir(), 'scry');
        $log('mtg: downloading ' . $src);
        $this->download($src, $tmp);
        $isGz = substr($src, -3) === '.gz';
        $fh = $isGz ? gzopen($tmp, 'rb') : fopen($tmp, 'rb');
        $read = $isGz ? 'gzgets' : 'fgets';
        try {
            while (($line = $read($fh)) !== false) {
                $line = rtrim(trim($line), ',');
                if ($line === '' || $line === '[' || $line === ']') continue;
                $c = json_decode($line, true);
                if (!is_array($c) || !in_array('paper', (array) ($c['games'] ?? []), true)) continue;
                $p = $c['prices'] ?? [];
                $url = $c['purchase_uris']['tcgplayer'] ?? ($c['scryfall_uri'] ?? '');
                $add($c['name'], $c['set_name'] ?? '', strtoupper($c['set'] ?? ''), $c['collector_number'] ?? '', $c['rarity'] ?? '', $p['usd'] ?? null, 'scryfall', $url);
                $add($c['name'], $c['set_name'] ?? '', strtoupper($c['set'] ?? ''), $c['collector_number'] ?? '', 'foil ' . ($c['rarity'] ?? ''), $p['usd_foil'] ?? null, 'scryfall', $url);
                $add($c['name'], $c['set_name'] ?? '', strtoupper($c['set'] ?? ''), $c['collector_number'] ?? '', 'etched foil ' . ($c['rarity'] ?? ''), $p['usd_etched'] ?? null, 'scryfall', $url);
            }
        } finally {
            $isGz ? gzclose($fh) : fclose($fh);
            @unlink($tmp);
        }
    }

    protected function importYugioh(callable $add, callable $log)
    {
        $d = json_decode($this->get('https://db.ygoprodeck.com/api/v7/cardinfo.php', [], 3), true);
        if (!is_array($d) || empty($d['data'])) throw new \RuntimeException('YGOPRODeck dump failed');
        foreach ($d['data'] as $c) {
            foreach ((array) ($c['card_sets'] ?? []) as $s) {
                $code = $s['set_code'] ?? '';
                $num = preg_match('/-(?:[A-Z]{1,2})?(\d+)$/', $code, $m) ? $m[1] : '';
                $add($c['name'], $s['set_name'] ?? '', $code, $num, $s['set_rarity'] ?? '', $s['set_price'] ?? null, 'ygoprodeck', $c['ygoprodeck_url'] ?? '');
            }
        }
    }

    protected function get($url, array $headers = [], $tries = 3)
    {
        $last = '';
        for ($i = 0; $i < $tries; $i++) {
            if ($i) sleep(min(30, 2 * $i));
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => array_merge(['User-Agent: ' . self::UA], $headers),
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $last = curl_error($ch) ?: "HTTP $code";
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) return $body;
        }
        throw new \RuntimeException("GET $url failed: $last");
    }

    protected function download($url, $path)
    {
        $fp = fopen($path, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_HTTPHEADER => ['User-Agent: ' . self::UA],
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code >= 300) throw new \RuntimeException("Download $url failed (HTTP $code)");
    }
}
