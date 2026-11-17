<?php

namespace App\Services;

use App\Business;
use App\Communication;
use Carbon\Carbon;

/**
 * Brings the channels that live on the website into the Communications Hub
 * (Sarah 2026-11-17: one hub, all communications): Discogs order messages
 * (whole thread, both sides) and website contact form messages. Runs on a
 * schedule; safe to re-run, rows are matched by external_id.
 */
class HubImportService
{
    private static function base(): array
    {
        $business_id = optional(Business::first())->id;
        $uid = $business_id ? optional(\DB::table('users')->where('business_id', $business_id)->orderBy('id')->first())->id : null;
        return [$business_id, $uid];
    }

    public static function discogs(): array
    {
        [$business_id, $uid] = self::base();
        if (!$business_id) return ['ok' => false, 'msg' => 'No business.'];
        $resp = app(\App\Http\Controllers\WebsiteOrdersController::class)->bridgeGet('/erp/discogs/messages', 25);
        if (!is_array($resp) || empty($resp['success'])) return ['ok' => false, 'msg' => 'Could not reach the website.'];
        $tz = 'America/Los_Angeles';
        $n = 0;
        foreach ((array) ($resp['data'] ?? []) as $o) {
            $in = []; $out = []; $lastTheirs = false; $first = null;
            foreach ((array) ($o['thread'] ?? []) as $m) {
                $at = Carbon::parse($m['at'] ?? 'now')->setTimezone($tz);
                $first = $first ?: $at;
                $line = '[' . $at->format('n/j g:ia') . '] ' . trim((string) ($m['text'] ?? ''));
                if (!empty($m['ours'])) { $out[] = $line; } else { $in[] = $line; }
                $lastTheirs = empty($m['ours']);
            }
            if (!$in) continue;
            $ext = 'discogs-order-' . $o['id'];
            $c = Communication::where('business_id', $business_id)->where('external_id', $ext)->first();
            $isNew = !$c;
            if ($isNew) {
                $c = new Communication();
                $c->business_id = $business_id;
                $c->channel = 'discogs';
                $c->external_id = $ext;
                $c->created_by = $uid;
                $c->status = $lastTheirs ? 'pending' : 'resolved';
                $c->created_at = $first;
            } elseif ($lastTheirs && $c->status === 'resolved' && $c->message !== implode("\n", $in)) {
                $c->status = 'pending'; // they wrote again
            }
            $c->customer_name = trim((string) ($o['buyer'] ?? '')) ?: 'Discogs buyer';
            $c->contact_info = 'Discogs order ' . $o['id'] . (!empty($o['items']) ? ' (' . mb_strimwidth(implode(', ', (array) $o['items']), 0, 120, '...') . ')' : '');
            $c->message = implode("\n", $in);
            $c->resolution_notes = $out ? implode("\n", $out) : null;
            $c->topic = $c->topic ?: Communication::guessTopic(implode(' ', $in));
            $c->save();
            $n++;
        }
        return ['ok' => true, 'msg' => $n . ' Discogs conversations synced.'];
    }

    public static function website(): array
    {
        [$business_id, $uid] = self::base();
        if (!$business_id) return ['ok' => false, 'msg' => 'No business.'];
        $resp = WebsiteBridge::admin('GET', '/customer/messages');
        if (empty($resp['success'])) return ['ok' => false, 'msg' => 'Could not reach the website.'];
        $n = 0;
        foreach ((array) ($resp['data'] ?? []) as $m) {
            if (!is_array($m) || empty($m['_id'])) continue;
            $at = strtotime((string) ($m['createdAt'] ?? '')) ?: 0;
            if ($at < now()->subDays(60)->timestamp) continue;
            $ext = 'web-contact-' . $m['_id'];
            $c = Communication::where('business_id', $business_id)->where('external_id', $ext)->first();
            $answered = ($m['status'] ?? 'new') !== 'new';
            if (!$c) {
                $c = new Communication();
                $c->business_id = $business_id;
                $c->channel = 'website';
                $c->external_id = $ext;
                $c->created_by = $uid;
                $c->created_at = Carbon::createFromTimestamp($at);
                $c->customer_name = trim((string) ($m['name'] ?? '')) ?: null;
                $c->contact_info = trim((string) ($m['email'] ?? '') . (!empty($m['phone']) ? ' / ' . $m['phone'] : ''));
                $c->message = trim(($m['subject'] ?? '') !== '' ? $m['subject'] . ': ' . ($m['message'] ?? '') : (string) ($m['message'] ?? ''));
                $c->topic = Communication::guessTopic($c->message);
                $c->status = $answered ? 'resolved' : 'pending';
                $c->save();
                $n++;
            } elseif ($answered && $c->status !== 'resolved') {
                $c->status = 'resolved';
                $c->save();
            }
        }
        return ['ok' => true, 'msg' => $n . ' new website messages.'];
    }
}
