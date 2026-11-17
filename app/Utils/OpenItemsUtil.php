<?php

namespace App\Utils;

use App\Services\WebsiteBridge;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\WebsiteOrdersController;
use App\Http\Controllers\WebsiteAdmin\WebsiteBookingsController;
use Carbon\Carbon;

/**
 * Open Items: one list of everything still waiting on someone, so nothing
 * falls through the cracks. Each section reads live data (ERP tables or the
 * website bridge) and returns rows with how long they've waited and a link
 * to the screen where they get closed out. Nothing here writes anything.
 */
class OpenItemsUtil
{
    const TZ = 'America/Los_Angeles';
    const RECENT_DAYS = 14;

    /** All sections, in the order they should be worked. */
    public static function sections(int $business_id): array
    {
        $out = [];
        foreach ([
            'unhappy'   => fn () => self::unhappyCustomers($business_id),
            'staff'     => fn () => self::staffNotReady($business_id),
            'sellers'   => fn () => self::sellerLeads(),
            'orders'    => fn () => self::stuckOrders(),
            'discogs'   => fn () => self::discogsOrders(),
            'customers' => fn () => self::customersWaiting($business_id),
            'contact'   => fn () => self::contactForms(),
            'events'    => fn () => self::eventPrep($business_id),
            'buys'      => fn () => self::draftBuys($business_id),
            'bookings'  => fn () => self::unpaidBookings(),
        ] as $key => $fn) {
            try {
                $out[$key] = $fn();
            } catch (\Throwable $e) {
                \Log::warning('open-items ' . $key . ' failed: ' . $e->getMessage());
                $names = ['sellers' => 'People who want to sell us records', 'discogs' => 'Discogs orders to ship', 'staff' => 'Get new teammates ready', 'Set up their login and time clock PIN.' => 'Customers to reply to', 'Answer them today.' => 'Let\'s make these customers happy', 'Call them and make it right.' => 'Website orders to get out the door', 'contact' => 'Website messages to answer', 'bookings' => 'Venue bookings to finish', 'buys' => 'Buys to finish', 'Ask the cashier, then Accept or Reject.' => 'Listening party prep'];
                $out[$key] = self::section($names[$key] ?? $key, '', '', [], 'Could not load this right now.');
            }
            // Only the last 2 weeks; older ones are counted, not listed.
            // Staff and event prep are about upcoming dates, so they are never cut.
            $out[$key]['older'] = 0;
            if (!in_array($key, ['staff', 'events', 'unhappy'], true)) {
                $recent = array_values(array_filter($out[$key]['rows'], fn ($r) => $r['hours'] <= self::RECENT_DAYS * 24));
                $out[$key]['older'] = count($out[$key]['rows']) - count($recent);
                $out[$key]['rows'] = $recent;
            }
            $out[$key]['title'] = trim(preg_replace('/\s*\(last \d+ days\)/', '', $out[$key]['title']));
        }
        return $out;
    }

    public static function total(array $sections): int
    {
        return array_sum(array_map(fn ($s) => count($s['rows']), $sections));
    }

    protected static function section(string $title, string $how, string $link, array $rows, ?string $error = null): array
    {
        return ['title' => $title, 'how' => $how, 'link' => $link, 'rows' => $rows, 'error' => $error];
    }

    protected static function row($who, $what, $since, string $link, string $key = ''): array
    {
        $who = (string) $who;
        // Quo mirror tags like <!--quo-msg-...--> are not part of the message.
        $what = trim(preg_replace('/<!--.*?-->/s', '', (string) $what));
        $ts = $since ? Carbon::parse($since)->setTimezone(self::TZ) : null;
        return [
            'who'   => trim($who) !== '' ? trim($who) : 'Unknown',
            'what'  => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $what)), 0, 140, '...'),
            'since' => $ts ? $ts->format('m/d/y') : '',
            'age'   => $ts ? self::age($ts) : '',
            'hours' => $ts ? $ts->diffInHours(Carbon::now(self::TZ)) : 0,
            'link'  => $link,
            'key'   => $key,
        ];
    }

    /** Channels whose messages show up here (everything the Communications Hub pulls in). */
    const REPLY_CHANNELS = ['phone_1', 'phone_2', 'instagram', 'facebook', 'whatsapp', 'tiktok', 'email'];

    protected static function whoWithChannel($r): string
    {
        $name = (string) ($r->customer_name ?: $r->contact_info);
        $label = [
            'phone_1' => 'Pico text', 'phone_2' => 'Hollywood text', 'instagram' => 'Instagram',
            'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'email' => 'Email',
        ][$r->channel] ?? '';
        return $label ? $name . ' (' . $label . ')' : $name;
    }

    /**
     * Marks a row as one Claude can draft a reply for, with the full message
     * (rows only show 140 characters). Phone and Instagram rows can be sent
     * straight from the reply panel; the rest are copied into their own inbox.
     */
    protected static function replyable(array $row, string $kind, string $text, int $commId = 0, string $channel = ''): array
    {
        $row['reply'] = [
            'kind'    => $kind,
            'text'    => mb_substr(trim(preg_replace('/<!--.*?-->/s', '', $text)), 0, 3000),
            'commId'  => $commId,
            'canSend' => $commId && in_array($channel, ['phone_1', 'phone_2', 'instagram'], true),
            'via'     => in_array($channel, ['phone_1', 'phone_2'], true) ? 'text' : ($channel === 'instagram' ? 'Instagram' : ''),
        ];
        return $row;
    }

    protected static function age(Carbon $ts): string
    {
        $h = $ts->diffInHours(Carbon::now(self::TZ));
        if ($h < 48) {
            return $h . 'h';
        }
        return intdiv($h, 24) . ' days';
    }

    protected static function oldestFirst(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['hours'] <=> $a['hours']);
        return $rows;
    }

    /** Texts that never need a reply: login codes, opt-outs, automated notices. */
    protected static function isNoise(string $msg): bool
    {
        $m = strtolower(preg_replace('/<!--.*?-->/s', '', $msg));
        return (bool) preg_match('/verification code|two-factor|is your .{0,20}code|whatsapp business account|account confirmation request|\\bstop\\b|^\\s*(\\[[^\\]]*\\]\\s*)?(ok|ok\\.? thanks|thanks|thank you|liked .*)\\W*$/s', $m);
    }

    /** Quo texts and calls with no reply after 24 hours (last 30 days). */
    public static function customersWaiting(int $business_id): array
    {
        $rows = \DB::table('communications')
            ->where('business_id', $business_id)
            ->whereIn('channel', self::REPLY_CHANNELS)
            ->where('status', 'pending')
            ->whereIn('id', array_keys(array_filter(self::triage($business_id), fn ($l) => $l === 'reply')) ?: [0])
            ->where('created_at', '<', now()->subDay())
            ->where('created_at', '>=', now()->subDays(30))
            ->where('message', '!=', '(no inbound message logged for this contact)')
            ->orderBy('created_at')
            ->limit(100)
            ->get(['id', 'channel', 'customer_name', 'contact_info', 'message', 'created_at']);
        $out = [];
        foreach ($rows as $r) {
            // Automated mail (receipts, notices) never needs a reply.
            if ($r->channel === 'email' && preg_match('/no-?reply|notification|mailer-daemon|do-not-reply|newsletter/i', (string) $r->contact_info)) {
                continue;
            }
            // Toll-free 833/844/855 numbers calling us are robocalls.
            if (self::isNoise((string) $r->message) || preg_match('/^\+?1?8(33|44|55|66|77|88)/', (string) $r->contact_info) || stripos((string) $r->contact_info, 'anonymous') !== false || stripos((string) $r->customer_name, 'anonymous') !== false) {
                continue;
            }
            $row = self::row(self::whoWithChannel($r), (string) $r->message, $r->created_at, url('/communications'), 'comm:' . $r->id);
            $out[] = self::replyable($row, 'customers', (string) $r->message, (int) $r->id, (string) $r->channel);
        }
        return self::section('Customers to reply to', 'Answer them today.', url('/communications'), self::oldestFirst($out));
    }

    const TRIAGE_SYSTEM = 'You sort open text, call and DM threads for Nivessa Records, a record store with stores on Pico and Hollywood. Each thread shows what the customer sent and what we already replied. Give each ID exactly one label:
"upset" = the customer is clearly unhappy with us (complaint, frustration, wrong or missing order, refund because something went wrong, felt ignored) AND it is not settled yet in the thread.
"reply" = a real customer is still waiting on an answer or action from us (a question we have not answered, or we promised to follow up and have not).
"done" = nothing left for us to do: they said thanks, ok, see you, no worries, or the question was answered; a missed call with no message (an automatic text already went out); spam, sales pitches, loans or working capital offers; verification codes; STOP; wrong numbers; and messages from our own staff about shifts, training or work.
Reply with only a JSON object mapping each ID to its label, like {"12":"done","40":"reply"}.';

    /**
     * Claude sorts every open message thread (with our replies) into upset,
     * still needs a reply, or done. Keyword tags were wrong too often: polite
     * questions showed as unhappy, and thank-yous, spam and staff texts
     * showed as waiting. Each thread is read once and re-read only when it
     * changes. If Claude can't be reached, falls back to the old rules.
     */
    public static function triage(int $business_id): array
    {
        static $memo = [];
        if (isset($memo[$business_id])) {
            return $memo[$business_id];
        }
        $rows = \DB::table('communications')
            ->where('business_id', $business_id)
            ->whereIn('channel', self::REPLY_CHANNELS)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(45))
            ->orderBy('created_at')
            ->limit(400)
            ->get(['id', 'topic', 'message', 'resolution_notes', 'has_real_reply']);
        $out = [];
        $todo = [];
        foreach ($rows as $r) {
            $ours = trim(preg_replace('/<!--.*?-->/s', '', (string) $r->resolution_notes));
            $text = 'CUSTOMER: ' . mb_substr(preg_replace('/\s+/', ' ', (string) $r->message), -900)
                . ($ours !== '' ? ' || WE REPLIED: ' . mb_substr(preg_replace('/\s+/', ' ', $ours), -500) : ' || WE REPLIED: nothing yet');
            $ck = 'oi_tri_' . $r->id . '_' . md5($text);
            $v = \Cache::get($ck);
            if ($v !== null) {
                $out[$r->id] = $v;
            } else {
                $todo[$r->id] = ['text' => $text, 'ck' => $ck, 'row' => $r];
            }
        }
        foreach (array_chunk($todo, 30, true) as $chunk) {
            $got = self::askClaudeLabels(array_map(fn ($x) => $x['text'], $chunk), self::TRIAGE_SYSTEM);
            foreach ($chunk as $id => $x) {
                $lab = $got[(string) $id] ?? null;
                if (!in_array($lab, ['upset', 'reply', 'done'], true)) {
                    // Claude unreachable: old rules for now, retried next load.
                    $r = $x['row'];
                    $out[$id] = $r->topic === 'unhappy_customer' ? 'upset' : ((trim((string) $r->resolution_notes) === '' && !self::isNoise((string) $r->message)) ? 'reply' : 'done');
                    continue;
                }
                \Cache::put($x['ck'], $lab, now()->addDays(60));
                $out[$id] = $lab;
            }
        }
        return $memo[$business_id] = $out;
    }

    /** Asks Claude to label items. $items is id => text. Returns id => label, or [] if it could not answer. */
    public static function askClaudeLabels(array $items, string $system): array
    {
        $cfg = \App\Http\Controllers\HelpAssistantSettingsController::readConfig();
        $apiKey = ($cfg['api_key'] ?? null) ?: config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');
        if (!$apiKey || !$items) {
            return [];
        }
        $list = '';
        foreach ($items as $id => $text) {
            $list .= 'ID ' . $id . ': ' . $text . "\n\n";
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 45]);
            $resp = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => ['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'],
                'json' => ['model' => $cfg['model'] ?? config('services.anthropic.model', 'claude-haiku-4-5'), 'max_tokens' => 1500, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $list]]],
                'http_errors' => false,
            ]);
            if ($resp->getStatusCode() !== 200) {
                \Log::warning('open-items triage HTTP ' . $resp->getStatusCode());
                return [];
            }
            $data = json_decode((string) $resp->getBody(), true);
            $txt = '';
            foreach ((array) ($data['content'] ?? []) as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $txt .= $b['text'];
                }
            }
            if (!preg_match('/\{.*\}/s', $txt, $m)) {
                return [];
            }
            $j = json_decode($m[0], true);
            return is_array($j) ? array_map('strval', $j) : [];
        } catch (\Throwable $e) {
            \Log::warning('open-items triage failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Open messages from customers who are actually upset (Claude reads each one). */
    public static function unhappyCustomers(int $business_id): array
    {
        $ids = array_keys(array_filter(self::triage($business_id), fn ($l) => $l === 'upset'));
        $rows = $ids ? \DB::table('communications')
            ->where('business_id', $business_id)
            ->whereIn('id', $ids)
            ->orderBy('created_at')
            ->get(['id', 'channel', 'customer_name', 'contact_info', 'message', 'created_at']) : collect();
        $out = [];
        foreach ($rows as $r) {
            $row = self::row(self::whoWithChannel($r), (string) $r->message, $r->created_at, url('/communications'), 'comm:' . $r->id);
            $out[] = self::replyable($row, 'unhappy', (string) $r->message, (int) $r->id, (string) $r->channel);
        }
        return self::section('Let\'s make these customers happy', 'Call them and make it right.', url('/communications'), self::oldestFirst($out));
    }

    /** Paid website orders still open after 3 days, plus every "can't find" order. */
    public static function stuckOrders(): array
    {
        $resp = \Cache::remember('open_items_web_orders', 300, function () {
            return app(WebsiteOrdersController::class)->bridgeGet('/erp/orders/console?limit=300&payment_status=completed', 25);
        });
        if (!is_array($resp) || !isset($resp['data'])) {
            \Cache::forget('open_items_web_orders');
            return self::section('Website orders to get out the door', '', url('/website-orders'), [], 'Could not reach the website right now.');
        }
        $cut = now()->subDays(3)->timestamp;
        $out = [];
        foreach ((array) $resp['data'] as $o) {
            if (!is_array($o)) {
                continue;
            }
            $id = (string) ($o['_id'] ?? '');
            $created = strtotime((string) ($o['createdAt'] ?? '')) ?: 0;
            if (!$created || $created < now()->subDays(60)->timestamp) {
                continue;
            }
            $name = (string) (($o['display_name'] ?? '') ?: (($o['shippingAddress'][0]['name'] ?? '') ?: ($o['user_id']['name'] ?? '')));
            $name = trim((!empty($o['orderNumber']) ? '#' . $o['orderNumber'] . ' ' : '') . $name);
            if (WebsiteOrdersController::inTab($o, 'cant_find')) {
                $items = implode(', ', array_filter(array_map(fn ($it) => is_array($it) ? (string) ($it['product_name'] ?? ($it['product_id']['name'] ?? '')) : '', (array) ($o['items'] ?? []))));
                $row = self::row($name, "Can't find it: recheck bins or offer a swap", $o['createdAt'] ?? null, url('/website-orders?order=' . $id), 'order:' . $id);
                $out[] = self::replyable($row, 'cantfind', 'Website order ' . $name . ($items ? ' for: ' . $items : '') . '. We cannot find the record on the shelf right now.');
            } elseif ($created < $cut && WebsiteOrdersController::inTab($o, 'needs_action')) {
                $how = ($o['fulfillment_method'] ?? '') === 'pickup' ? 'Get the pickup ready' : 'Ready to ship';
                $out[] = self::row($name, $how . ', ordered ' . intdiv(time() - $created, 86400) . ' days ago', $o['createdAt'] ?? null, url('/website-orders?order=' . $id), 'order:' . $id);
            }
        }
        return self::section('Website orders to get out the door', 'Text the cashier on shift the order link.', url('/website-orders?tab=needs_action'), self::oldestFirst($out));
    }

    const SELLER_RE = '/\b(for sale|sell(ing)?|collection|bulk|library|estate|records? to sell|lot of)\b/i';

    /** Contact form messages still "New" in the last 30 days, spam removed. Null if the website is down. */
    protected static function newContactMessages(): ?array
    {
        $resp = \Cache::remember('open_items_contact', 300, fn () => WebsiteBridge::admin('GET', '/customer/messages'));
        if (empty($resp['success'])) {
            \Cache::forget('open_items_contact');
            return null;
        }
        $out = [];
        foreach ((array) ($resp['data'] ?? []) as $m) {
            if (!is_array($m) || ($m['status'] ?? 'new') !== 'new') {
                continue;
            }
            $at = strtotime((string) ($m['createdAt'] ?? '')) ?: 0;
            if ($at < now()->subDays(30)->timestamp) {
                continue;
            }
            $blob = strtolower(($m['subject'] ?? '') . ' ' . ($m['message'] ?? ''));
            if (preg_match('/virtual assistant|explainer video|social media manager|seo|funding|leads for|streamlining your workflow|transparency certificate|homepage|free set of new ads/', $blob)) {
                continue;
            }
            $m['_seller'] = (bool) preg_match(self::SELLER_RE, $blob) && !preg_match('/\b(order|refund|ship|tracking)\b/', $blob);
            $out[] = $m;
        }
        // Claude drops sales pitches (loans, data deals, marketing) that keywords miss. Cached per message.
        $todo = [];
        foreach ($out as $i => $m) {
            $id = (string) ($m['_id'] ?? md5(json_encode($m)));
            $v = \Cache::get('oi_spam_' . $id);
            if ($v === 'spam') {
                unset($out[$i]);
            } elseif ($v === null) {
                $todo[$id] = mb_substr(($m['subject'] ?? '') . ': ' . preg_replace('/\s+/', ' ', (string) ($m['message'] ?? '')), 0, 700);
            }
        }
        if ($todo) {
            $got = self::askClaudeLabels($todo, 'You sort messages sent to Nivessa Records through its website contact form. Label each ID "spam" if it is a sales pitch or solicitation aimed at the business (loans, credit, funding, data licensing, marketing, SEO, ads, software, AI tools) or junk. Label it "real" if it is a real person writing to us: customers, people selling us records, job seekers, event or artist partners, press, or a business asking us to fix or remove something about them. Reply with only a JSON object mapping each ID to its label.');
            if ($got) {
                foreach ($out as $i => $m) {
                    $id = (string) ($m['_id'] ?? md5(json_encode($m)));
                    if (isset($todo[$id], $got[$id])) {
                        \Cache::put('oi_spam_' . $id, $got[$id] === 'spam' ? 'spam' : 'real', now()->addDays(60));
                        if ($got[$id] === 'spam') {
                            unset($out[$i]);
                        }
                    }
                }
            }
        }
        return array_values($out);
    }

    protected static function contactRows(array $msgs, string $kind = 'contact'): array
    {
        $out = [];
        foreach ($msgs as $m) {
            $what = trim(($m['subject'] ?? '') . ': ' . ($m['message'] ?? ''), ': ');
            $row = self::row((string) ($m['name'] ?? $m['email'] ?? ''), $what, $m['createdAt'] ?? null, url('/website-inbox/messages'), 'contact:' . ($m['_id'] ?? md5($what)));
            $out[] = self::replyable($row, $kind, $what);
        }
        return self::oldestFirst($out);
    }

    /** People offering us records. The biggest money on the page. */
    public static function sellerLeads(): array
    {
        $msgs = self::newContactMessages();
        if ($msgs === null) {
            return self::section('People who want to sell us records', '', url('/website-inbox/messages'), [], 'Could not reach the website right now.');
        }
        $rows = self::contactRows(array_filter($msgs, fn ($m) => $m['_seller']), 'sellers');
        return self::section('People who want to sell us records', 'Book a time for them to bring it in.', url('/buy-from-customer'), $rows);
    }

    /** Website contact form messages nobody has replied to. */
    public static function contactForms(): array
    {
        $msgs = self::newContactMessages();
        if ($msgs === null) {
            return self::section('Website messages to answer', '', url('/website-inbox/messages'), [], 'Could not reach the website right now.');
        }
        $rows = self::contactRows(array_filter($msgs, fn ($m) => !$m['_seller']));
        return self::section('Website messages to answer', 'Answer from the Website Inbox.', url('/website-inbox/messages'), $rows);
    }

    /** Discogs orders over 30 days old still not shipped, and buyers we owe an answer. */
    public static function discogsOrders(): array
    {
        $ctl = app(WebsiteOrdersController::class);
        $old = \Cache::remember('open_items_discogs_old', 300, fn () => $ctl->bridgeGet('/erp/discogs/to-ship?old=1', 20));
        $wait = \Cache::remember('open_items_discogs_wait', 300, fn () => $ctl->bridgeGet('/erp/discogs/waiting-on-buyer', 25));
        if (empty($old['success']) && empty($wait['success'])) {
            \Cache::forget('open_items_discogs_old');
            \Cache::forget('open_items_discogs_wait');
            return self::section('Discogs orders to ship', '', url('/website-orders?tab=discogs_old'), [], 'Could not reach Discogs right now.');
        }
        $out = [];
        foreach ((array) ($old['data'] ?? []) as $d) {
            if (is_array($d)) {
                $out[] = self::row((string) ($d['buyer'] ?? ''), 'Paid ' . ($d['id'] ?? '') . ', not shipped after 30+ days', $d['order_date'] ?? $d['created'] ?? null, url('/website-orders?tab=discogs_old'), 'discogs:' . ($d['id'] ?? ''));
            }
        }
        foreach ((array) ($wait['data'] ?? []) as $d) {
            if (is_array($d)) {
                $r = self::row((string) ($d['buyer'] ?? ''), "We said we can't find it, buyer hasn't answered: refund or send a substitute " . ($d['id'] ?? ''), null, url('/website-orders?tab=discogs_old'), 'discogs:' . ($d['id'] ?? ''));
                $days = (int) ($d['days_waiting'] ?? 0);
                $r['age'] = $days . ' days';
                $r['hours'] = $days * 24;
                $out[] = $r;
            }
        }
        return self::section('Discogs orders to ship', 'Ship it today.', url('/website-orders?tab=discogs_old'), self::oldestFirst($out));
    }

    /** Venue booking requests that never got paid. */
    public static function unpaidBookings(): array
    {
        $rows = \Cache::remember('open_items_bookings', 300, function () {
            $err = null;
            $r = WebsiteBookingsController::unpaidRequests($err);
            return $err ? null : $r;
        });
        if ($rows === null) {
            \Cache::forget('open_items_bookings');
            return self::section('Venue bookings to finish', '', url('/website-carts?tab=bookings'), [], 'Could not reach the website right now.');
        }
        $out = [];
        foreach ($rows as $b) {
            if (preg_match('/^(admin|test)/i', trim($b['customerName'])) || preg_match('/^test/i', trim($b['customerEmail']))) {
                continue;
            }
            $out[] = self::row($b['customerName'] ?: $b['customerEmail'], trim($b['venueName'] . ' ' . $b['title']), $b['createdAt'] ?: null, url('/website-carts?tab=bookings'), 'booking:' . $b['id']);
        }
        return self::section('Venue bookings to finish', 'Call or email to finish the booking.', url('/website-carts?tab=bookings'), self::oldestFirst($out));
    }

    /** Buys left as drafts for over a day (last 7 days). */
    public static function draftBuys(int $business_id): array
    {
        $rows = \DB::table('buy_customer_offers as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
            ->leftJoin('business_locations as l', 'l.id', '=', 'b.location_id')
            ->where('b.business_id', $business_id)
            ->where('b.status', 'draft')
            ->where('b.created_at', '<', now()->subDay())
            ->where('b.created_at', '>=', now()->subDays(7))
            // Skip drafts for a seller who was bought from (or turned down) later.
            ->whereNotExists(function ($q) {
                $q->select(\DB::raw(1))->from('buy_customer_offers as d')
                    ->whereColumn('d.business_id', 'b.business_id')
                    ->whereIn('d.status', ['accepted', 'rejected'])
                    ->whereColumn('d.created_at', '>=', 'b.created_at')
                    ->where(function ($w) {
                        $w->where(function ($x) {
                            $x->whereNotNull('b.seller_phone')->where('b.seller_phone', '!=', '')->whereColumn('d.seller_phone', 'b.seller_phone');
                        })->orWhere(function ($x) {
                            $x->whereNotNull('b.contact_id')->whereColumn('d.contact_id', 'b.contact_id');
                        });
                    });
            })
            ->orderBy('b.created_at')
            ->limit(100)
            ->get(['b.id', 'b.seller_name', 'b.seller_phone', 'b.final_offer_cash', 'b.created_at', 'u.first_name', 'l.name as store']);
        $out = [];
        foreach ($rows as $r) {
            $what = 'Draft by ' . ($r->first_name ?: '?') . ($r->store ? ' at ' . $r->store : '');
            $cash = (float) $r->final_offer_cash;
            if ($cash > 0 && $cash < 20000) {
                $what .= ', $' . number_format($cash, 2) . ' cash';
            }
            $out[] = self::row($r->seller_name ?: $r->seller_phone, $what, $r->created_at, url('/buy-from-customer/' . $r->id . '/edit'), 'buy:' . $r->id);
        }
        return self::section('Buys to finish', 'Ask the cashier, then Accept or Reject.', url('/buy-from-customer/history'), self::oldestFirst($out));
    }

    /** Event prep steps past their due day for events in the next 2 weeks. */
    public static function eventPrep(int $business_id): array
    {
        $data = EventsController::load($business_id);
        $today = Carbon::now(self::TZ)->startOfDay();
        $out = [];
        foreach ((array) ($data['items'] ?? []) as $id => $ev) {
            // The prep checklist (giveaway box, playback link) is for listening parties.
            if (!is_array($ev) || ($ev['eventType'] ?? '') !== 'listening_party') {
                continue;
            }
            $date = (string) ($ev['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $evDay = Carbon::parse($date, self::TZ)->startOfDay();
            if ($evDay->lt($today) || $evDay->gt($today->copy()->addDays(14))) {
                continue;
            }
            $done = (array) ($ev['prepChecklist'] ?? []);
            $late = [];
            foreach (EventsController::prepItemsForEvent($ev) as $it) {
                if (!empty($done[$it['id']]['done'])) {
                    continue;
                }
                if ($evDay->copy()->subDays((int) $it['due'])->lte($today)) {
                    $late[] = $it['label'];
                }
            }
            if ($late) {
                $eid = (string) ($ev['id'] ?? $id);
                $name = (string) ($ev['name'] ?? $ev['title'] ?? 'Event');
                $what = count($late) . ' step' . (count($late) === 1 ? '' : 's') . ' due: ' . implode(' / ', $late);
                $r = self::row($name, $what, null, url('/events/' . $eid . '/edit'), 'prep:' . $eid . ':' . $today->format('Y-m-d'));
                $r['since'] = $evDay->format('m/d/y');
                $r['age'] = $evDay->isSameDay($today) ? 'today' : ($today->diffInDays($evDay) === 1 ? 'tomorrow' : 'in ' . $today->diffInDays($evDay) . ' days');
                $r['hours'] = -$today->diffInDays($evDay);
                $out[] = $r;
            }
        }
        return self::section('Listening party prep', 'Tick the step on the event page.', url('/events'), self::oldestFirst($out));
    }

    /**
     * Anyone on the Sling schedule in the next 14 days who can't work their
     * shift yet: no ERP login, or no time clock PIN. Plus onboarding runs
     * logged in the last 60 days with steps still unticked.
     */
    public static function staffNotReady(int $business_id): array
    {
        $tz = self::TZ;
        $now = Carbon::now($tz);
        $shifts = \DB::table('sling_shifts')
            ->where('event_type', 'shift')
            ->whereBetween('dtstart', [$now->copy()->startOfDay()->format('Y-m-d H:i:s'), $now->copy()->addDays(14)->format('Y-m-d H:i:s')])
            ->orderBy('dtstart')
            ->get(['erp_user_id', 'user_email', 'user_name', 'dtstart', 'location_name']);
        $users = \DB::table('users')->where('business_id', $business_id)->whereNull('deleted_at')
            ->get(['id', 'email', 'first_name', 'last_name', 'allow_login', 'created_at']);
        $byId = [];
        $byEmail = [];
        $byName = [];
        $firstCount = [];
        foreach ($users as $u) {
            $byId[(int) $u->id] = $u;
            if ($u->email) {
                $byEmail[strtolower(trim($u->email))] = $u;
            }
            $full = strtolower(trim($u->first_name . ' ' . $u->last_name));
            if ($full !== '') {
                $byName[$full] = $u;
            }
            $f = strtolower(trim((string) $u->first_name));
            if ($f !== '') {
                $firstCount[$f][] = $u;
            }
        }
        $pins = array_flip(array_map('intval', \DB::table('time_clock_pins')->where('business_id', $business_id)->pluck('user_id')->all()));

        $seen = [];
        $out = [];
        foreach ($shifts as $s) {
            $key = $s->erp_user_id ?: strtolower(trim((string) ($s->user_email ?: $s->user_name)));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $u = $s->erp_user_id ? ($byId[(int) $s->erp_user_id] ?? null) : null;
            if (!$u && $s->user_email) {
                $u = $byEmail[strtolower(trim($s->user_email))] ?? null;
            }
            $sn = strtolower(trim((string) $s->user_name));
            if (!$u && $sn !== '') {
                $u = $byName[$sn] ?? null;
                // Sling often shows just a first name; match it if only one person has it.
                if (!$u && isset($firstCount[$sn]) && count($firstCount[$sn]) === 1) {
                    $u = $firstCount[$sn][0];
                }
            }
            // This box is for new hires only. Anyone with an ERP account older than 30 days is not new.
            if ($u && $u->created_at && Carbon::parse($u->created_at)->lt($now->copy()->subDays(30))) {
                continue;
            }
            $start = Carbon::parse($s->dtstart)->setTimezone($tz);
            $when = 'next shift ' . $start->format('m/d/y g:i A') . ($s->location_name ? ' at ' . $s->location_name : '');
            $missing = [];
            if (!$u || !(int) $u->allow_login) {
                $missing[] = 'no ERP login';
            }
            if (!$u || !isset($pins[(int) $u->id])) {
                $missing[] = 'no time clock PIN';
            }
            if (!$missing) {
                continue;
            }
            $name = $u ? trim($u->first_name . ' ' . $u->last_name) : (string) ($s->user_name ?: $s->user_email);
            $r = self::row($name, ucfirst(implode(', ', $missing)) . ', ' . $when, null, url('/time-clock/settings'), 'staff:' . $key);
            $hrs = $now->diffInHours($start, false);
            $r['since'] = $start->format('m/d/y');
            $r['age'] = $hrs <= 24 ? 'within 24h' : 'in ' . (int) ceil($hrs / 24) . ' days';
            $r['hours'] = 1000 - $hrs; // soonest shift first, flagged red inside 24h
            $r['late'] = $hrs <= 24;
            $out[] = $r;
        }

        // Onboarding runs with steps left (last 60 days).
        $cut = $now->copy()->subDays(60)->format('Y-m-d');
        $labels = \App\Http\Controllers\EmployeeChecklistController::allItems('onboarding');
        foreach (\App\Http\Controllers\EmployeeChecklistController::readAll() as $run) {
            if (($run['type'] ?? '') !== 'onboarding' || ($run['date'] ?? '') < $cut || empty($run['missed'])) {
                continue;
            }
            $steps = array_map(function ($k) use ($labels) {
                $l = (string) ($labels[$k] ?? $k);
                $l = trim(preg_split('/\s+[\x{2014}\x{2013}]\s+|\s+-\s+|\s*\(/u', $l)[0]); // short name only, before any dash or ( )
                return rtrim($l, '.:;,');
            }, (array) $run['missed']);
            $n = count($steps);
            $r = self::row((string) ($run['employee_name'] ?? ''), $n . ' onboarding ' . ($n === 1 ? 'step' : 'steps') . ' left: ' . implode('; ', $steps) . '.', $run['date'], url('/employee-checklist?type=onboarding'), 'onboard:' . ($run['id'] ?? ''));
            $out[] = $r;
        }

        usort($out, fn ($a, $b) => $b['hours'] <=> $a['hours']);
        return self::section('Get new teammates ready', 'Set up their login and time clock PIN.', url('/employee-checklist'), $out);
    }

    /* ---------- Fixed it / ERP bug ---------- */

    // ---- Scoreboard: points for real fixes, lost again if a fix bounces back ----
    const SCORE_FILE = 'open_items_score.json';
    const DAILY_TARGET = 8;
    const CLEAR_BONUS = 25;
    const FAST_BONUS = 5;
    const BUG_POINTS = 3;
    const POINTS = ['unhappy' => 20, 'sellers' => 15, 'orders' => 15, 'discogs' => 10, 'staff' => 10, 'events' => 10, 'bookings' => 10, 'customers' => 5, 'contact' => 5, 'buys' => 5];
    const LEVELS = [0 => 'Rookie', 250 => 'Crate Digger', 750 => 'Selector', 1500 => 'Headliner', 3000 => 'Legend'];

    // Kept simple on purpose: every real fix counts as 1.
    public static function pointsFor(string $section, int $hours = 9999): int
    {
        return 1;
    }

    public static function readScore(): array
    {
        $f = storage_path('app/' . self::SCORE_FILE);
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
        return is_array($d) ? $d : [];
    }

    /** Appends one score event. $once makes it a no-op if that id was already logged. */
    public static function addPoints(string $who, int $pts, string $why, ?string $once = null): bool
    {
        $f = storage_path('app/' . self::SCORE_FILE);
        $fp = fopen($f, 'c+');
        if (!$fp) {
            return false;
        }
        flock($fp, LOCK_EX);
        $d = json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
        $added = true;
        if ($once !== null) {
            foreach ($d as $e) {
                if (($e['once'] ?? null) === $once) {
                    $added = false;
                    break;
                }
            }
        }
        if ($added) {
            $now = Carbon::now(self::TZ);
            $d[] = array_filter(['d' => $now->format('Y-m-d'), 'at' => $now->toIso8601String(), 'who' => $who, 'pts' => $pts, 'why' => $why, 'once' => $once], fn ($v) => $v !== null);
            $cut = $now->copy()->subDays(400)->format('Y-m-d');
            $d = array_values(array_filter($d, fn ($e) => ($e['d'] ?? '') >= $cut));
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return $added;
    }

    /** Today, streak, best day, level and this week for one person. */
    public static function scoreboard(string $who): array
    {
        $now = Carbon::now(self::TZ);
        $today = $now->format('Y-m-d');
        $byDay = [];
        $total = 0;
        $feed = [];
        $seen = [];
        foreach (self::readScore() as $e) {
            if (($e['who'] ?? '') !== $who) {
                continue;
            }
            if (!empty($e['once'])) {
                $seen[$e['once']] = true;
            }
            $byDay[$e['d']] = ($byDay[$e['d']] ?? 0) + (int) $e['pts'];
            $total += (int) $e['pts'];
            if ($e['d'] === $today) {
                $feed[] = $e;
            }
        }
        // Everything he finished before the scoreboard existed also counts:
        // his "Fixed it" history and messages he resolved in the Communications Hub.
        $recent = [];
        foreach (self::readActions() as $k => $a) {
            if (($a['action'] ?? '') !== 'fixed' || ($a['by'] ?? '') !== $who || empty($a['at'])) {
                continue;
            }
            $at = Carbon::parse($a['at'])->setTimezone(self::TZ);
            $recent[] = ['label' => (string) ($a['label'] ?? ''), 'note' => (string) ($a['note'] ?? ''), 'at' => $at->toIso8601String()];
            if (!isset($seen['fix:' . $k . ':' . $at->format('Y-m-d')])) {
                $byDay[$at->format('Y-m-d')] = ($byDay[$at->format('Y-m-d')] ?? 0) + 1;
                $total++;
            }
            $seen['comm-key:' . $k] = true;
        }
        try {
            $uid = \DB::table('users')->where('first_name', $who)->whereNull('deleted_at')->value('id');
            if ($uid) {
                $res = \DB::table('communications')->where('resolved_by', $uid)->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', $now->copy()->subDays(60)->format('Y-m-d H:i:s'))
                    ->orderByDesc('resolved_at')->limit(500)->get(['id', 'customer_name', 'contact_info', 'resolution_notes', 'resolved_at']);
                foreach ($res as $c) {
                    if (isset($seen['comm-key:comm:' . $c->id])) {
                        continue; // already counted through Fixed it
                    }
                    $at = Carbon::parse($c->resolved_at, config('app.timezone'))->setTimezone(self::TZ);
                    $byDay[$at->format('Y-m-d')] = ($byDay[$at->format('Y-m-d')] ?? 0) + 1;
                    $total++;
                    $last = trim((string) last(preg_split('/\n\s*\n|(?=\[\d{1,2}\/\d{1,2}\s)/', trim(preg_replace('/<!--.*?-->/s', '', (string) $c->resolution_notes)))));
                    $recent[] = ['label' => 'Replied to ' . ($c->customer_name ?: $c->contact_info), 'note' => mb_strimwidth(preg_replace('/^\[[^\]]*\]\s*/', '', $last), 0, 90, '...'), 'at' => $at->toIso8601String()];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('scoreboard hub wins: ' . $e->getMessage());
        }
        // His daily checklist: every task he ticked off counts.
        try {
            $cut = $now->copy()->subDays(60)->format('Y-m-d');
            foreach (\App\Http\Controllers\DailyChecklistController::readAll() as $day) {
                if (stripos((string) ($day['user_name'] ?? ''), $who) !== 0 || ($day['date'] ?? '') < $cut) {
                    continue;
                }
                $n = count((array) ($day['checked'] ?? []));
                if ($n === 0) {
                    continue;
                }
                $byDay[$day['date']] = ($byDay[$day['date']] ?? 0) + $n;
                $total += $n;
                $at = Carbon::parse(($day['updated_at'] ?? $day['date']), self::TZ);
                $recent[] = ['label' => 'Daily checklist', 'note' => $n . ' of ' . (int) ($day['total'] ?? $n) . ' tasks done', 'at' => $at->toIso8601String()];
            }
        } catch (\Throwable $e) {
            \Log::warning('scoreboard checklist wins: ' . $e->getMessage());
        }
        usort($recent, fn ($x, $y) => strcmp($y['at'], $x['at']));
        // Streak: days he hit the target in a row. Days he didn't work (no points) are skipped, not counted against him.
        $streak = 0;
        $days = array_keys($byDay);
        rsort($days);
        foreach ($days as $d) {
            if ($d === $today && $byDay[$d] < self::DAILY_TARGET) {
                continue; // today isn't over yet
            }
            if ($byDay[$d] >= self::DAILY_TARGET) {
                $streak++;
            } elseif ($byDay[$d] > 0) {
                break;
            }
        }
        $monday = $now->copy()->startOfWeek()->format('Y-m-d');
        $lastMonday = $now->copy()->startOfWeek()->subWeek()->format('Y-m-d');
        $week = $lastWeek = 0;
        foreach ($byDay as $d => $p) {
            if ($d >= $monday) {
                $week += $p;
            } elseif ($d >= $lastMonday) {
                $lastWeek += $p;
            }
        }
        $total = max(0, $total);
        $level = 'Rookie';
        $next = null;
        $levelFrom = 0;
        foreach (self::LEVELS as $min => $name) {
            if ($total >= $min) {
                $level = $name;
                $levelFrom = $min;
            } elseif ($next === null) {
                $next = ['name' => $name, 'at' => $min];
            }
        }
        $pastDays = array_diff_key($byDay, [$today => 1]);
        usort($feed, fn ($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
        return [
            'today' => $byDay[$today] ?? 0,
            'target' => self::DAILY_TARGET,
            'streak' => $streak,
            'best' => $pastDays ? max($pastDays) : 0,
            'week' => $week,
            'lastWeek' => $lastWeek,
            'total' => $total,
            'level' => $level,
            'levelFrom' => $levelFrom,
            'next' => $next,
            'feed' => array_slice($feed, 0, 6),
            'recent' => array_slice($recent, 0, 12),
        ];
    }

    const ACTIONS_FILE = 'open_items_actions.json';
    const RECHECK_HOURS = 48;

    public static function readActions(): array
    {
        $f = storage_path('app/' . self::ACTIONS_FILE);
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
        return is_array($d) ? $d : [];
    }

    public static function saveAction(string $key, string $action, string $note, string $who, string $label, int $pts = 0): void
    {
        $f = storage_path('app/' . self::ACTIONS_FILE);
        $fp = fopen($f, 'c+');
        if (!$fp) {
            return;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $d = json_decode($raw ?: '[]', true) ?: [];
        $d[$key] = ['action' => $action, 'note' => $note, 'by' => $who, 'label' => $label, 'pts' => $pts, 'at' => Carbon::now(self::TZ)->toIso8601String()];
        // Keep the file small: drop entries older than 90 days.
        $cut = Carbon::now(self::TZ)->subDays(90);
        foreach ($d as $k => $v) {
            if (empty($v['at']) || Carbon::parse($v['at'])->lt($cut)) {
                unset($d[$k]);
            }
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Applies what he already did. "Fixed it" hides a row for 48 hours; if the
     * problem is still open after that, it comes back showing his note, so a
     * fix only counts when the problem is actually gone. "ERP bug" files it in
     * the Bug Fixes queue and gets the same 48-hour recheck.
     * Returns [sections, doneToday].
     */
    public static function applyActions(array $sections): array
    {
        $acts = self::readActions();
        $now = Carbon::now(self::TZ);
        foreach ($sections as $sk => $s) {
            $keep = [];
            foreach ($s['rows'] as $r) {
                $a = ($r['key'] ?? '') !== '' ? ($acts[$r['key']] ?? null) : null;
                if ($a) {
                    $at = Carbon::parse($a['at'])->setTimezone(self::TZ);
                    if ($at->diffInHours($now) < self::RECHECK_HOURS) {
                        continue;
                    }
                    // An unpaid booking stays "unpaid" even after the person says no,
                    // so once he has dealt with it, it stays gone.
                    if ($a['action'] === 'fixed' && strpos($r['key'], 'booking:') === 0) {
                        continue;
                    }
                    $r['reopened'] = ($a['action'] === 'bug' ? 'Reported as an ERP bug ' : 'Marked fixed ')
                        . $at->format('m/d/y') . ' ("' . $a['note'] . '") and it is back. One more push to finish it.';
                    if ($a['action'] === 'fixed' && !empty($a['by']) && !empty($a['pts'])) {
                        $lost = (int) $a['pts'];
                        if (self::addPoints($a['by'], -$lost, 'Came back: ' . ($a['label'] ?? $r['key']), 'back:' . $r['key'] . ':' . $a['at'])) {
                            \Log::info('open-items bounce-back -' . $lost . ' ' . $r['key']);
                        }
                        $r['reopened'] .= ' Finish it to count it again.';
                    }
                }
                $keep[] = $r;
            }
            $sections[$sk]['rows'] = $keep;
        }
        $done = [];
        foreach ($acts as $k => $a) {
            if (in_array($a['action'] ?? '', ['fixed', 'bug'], true) && Carbon::parse($a['at'])->setTimezone(self::TZ)->isSameDay($now)) {
                $done[] = $a;
            }
        }
        usort($done, fn ($x, $y) => strcmp($y['at'], $x['at']));
        return [$sections, $done];
    }
}


