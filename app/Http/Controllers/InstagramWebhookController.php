<?php

namespace App\Http\Controllers;

use App\Business;
use App\Communication;
use Illuminate\Http\Request;
use Log;

/**
 * Receives Instagram DM webhook deliveries (Meta Graph API "messaging"
 * events) and logs them into the Communications Hub as pending inquiries
 * — same idea as QuoWebhookController for the phone lines.
 *
 * Meta requires this to be reachable over HTTPS with a real signature
 * check, but does NOT require App Review to actually receive messages
 * for the business's own connected Instagram account: App Review is
 * only needed before an app can message accounts other than the ones
 * with a role on that same Meta app (its Admins/Developers/Testers).
 * Since this is Nivessa's own app talking to Nivessa's own Instagram
 * account, "Development Mode" access is sufficient — no review, no
 * waiting on Meta. (Full Review only matters if this were ever meant to
 * run for OTHER businesses' Instagram accounts too.)
 *
 * Credentials (App Secret, webhook Verify Token, Page Access Token) are
 * pasted through an admin-only settings screen and stored in a
 * gitignored JSON file — same pattern as the Quo webhook key, since
 * there's no SSH access to hand-edit the server .env.
 */
class InstagramWebhookController extends Controller
{
    private function settingsFile(): string
    {
        return storage_path('app/instagram-webhook.json');
    }

    private function settings_data(): array
    {
        try {
            $file = $this->settingsFile();
            if (is_file($file)) {
                return json_decode((string) file_get_contents($file), true) ?: [];
            }
        } catch (\Throwable $e) {
        }
        return [];
    }

    private function appSecret(): string
    {
        return trim((string) ($this->settings_data()['app_secret'] ?? ''));
    }

    private function verifyToken(): string
    {
        return trim((string) ($this->settings_data()['verify_token'] ?? ''));
    }

    /**
     * Public accessor so other controllers (e.g. ReportController, for
     * pulling real Instagram Insights) can read the same stored token
     * without duplicating the storage-file logic above.
     */
    public static function storedPageAccessToken(): string
    {
        $file = storage_path('app/instagram-webhook.json');
        try {
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true) ?: [];
                return trim((string) ($data['page_access_token'] ?? ''));
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    private function pageAccessToken(): string
    {
        return trim((string) ($this->settings_data()['page_access_token'] ?? ''));
    }

    private function requireAdmin(): void
    {
        $u = auth()->user();
        $is_admin = false;
        try {
            $is_admin = $u && ($u->can('superadmin') || $u->hasAnyPermission('Admin#' . $u->business_id));
        } catch (\Throwable $e) {
        }
        if (!$is_admin) {
            abort(403, 'Unauthorized action.');
        }
    }

    /** Settings screen: paste the App Secret / Verify Token / Page Access Token (admin only). */
    public function settings()
    {
        $this->requireAdmin();
        $data = $this->settings_data();
        $webhook_url = url('/webhooks/instagram');

        return view('communications.instagram_settings', [
            'webhook_url' => $webhook_url,
            'app_secret_masked' => !empty($data['app_secret']) ? '…' . substr($data['app_secret'], -6) : '',
            'verify_token' => $data['verify_token'] ?? '',
            'page_token_masked' => !empty($data['page_access_token']) ? '…' . substr($data['page_access_token'], -8) : '',
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->requireAdmin();
        $data = $this->settings_data();

        if ($request->filled('app_secret')) {
            $data['app_secret'] = trim($request->app_secret);
        }
        if ($request->filled('verify_token')) {
            $data['verify_token'] = trim($request->verify_token);
        }
        if ($request->filled('page_access_token')) {
            $data['page_access_token'] = trim($request->page_access_token);
        }

        $file = $this->settingsFile();
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

        return redirect()->back()->with('status', ['success' => 1, 'msg' => 'Saved.']);
    }

    /** Meta's webhook verification handshake (GET, done once when you register the callback URL). */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token !== '' && hash_equals($this->verifyToken(), (string) $token)) {
            return response((string) $challenge, 200);
        }

        return response('forbidden', 403);
    }

    /** Meta's actual message delivery (POST). */
    public function webhook(Request $request)
    {
        $raw = $request->getContent();
        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (!$this->verifySignature($raw, $signatureHeader)) {
            Log::warning('Instagram webhook signature mismatch.');
            return response('forbidden', 403);
        }

        try {
            $body = json_decode($raw, true) ?: [];
            $business_id = optional(Business::first())->id;
            $system_user_id = $business_id
                ? optional(\DB::table('users')->where('business_id', $business_id)->orderBy('id')->first())->id
                : null;
            if (!$business_id || !$system_user_id) {
                return response()->json(['success' => true]);
            }

            foreach (($body['entry'] ?? []) as $entry) {
                foreach (($entry['messaging'] ?? []) as $event) {
                    $senderId = $event['sender']['id'] ?? null;
                    $text = $event['message']['text'] ?? null;
                    $messageId = $event['message']['mid'] ?? null;
                    $isEcho = !empty($event['message']['is_echo']);

                    if (!$senderId || $text === null || $text === '') {
                        continue; // attachments-only, read receipts, etc. — nothing to log yet
                    }
                    if ($isEcho) {
                        continue; // our own outbound message echoed back — not a new inquiry
                    }

                    $externalId = $messageId ? 'ig-msg-' . $messageId : null;
                    if ($externalId && Communication::where('business_id', $business_id)->where('external_id', $externalId)->exists()) {
                        continue;
                    }

                    $c = new Communication();
                    $c->business_id = $business_id;
                    $c->channel = 'instagram';
                    $c->topic = Communication::guessTopic($text);
                    $c->contact_info = 'IG user ' . $senderId;
                    $c->message = $text;
                    $c->is_priority = $c->topic === 'unhappy_customer' ? 1 : 0;
                    $c->status = 'pending';
                    $c->external_id = $externalId;
                    $c->created_by = $system_user_id;
                    $c->save();
                }
            }
        } catch (\Throwable $e) {
            Log::emergency('Instagram webhook processing failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    /**
     * Pull recent DM conversations straight from the Graph API with the saved
     * Page token (no webhook needed). One Hub row per conversation: what the
     * customer sent in `message`, what we sent in `resolution_notes`, so the
     * dashboard shows the whole thread. Safe to run again: rows are matched by
     * conversation id and only updated. A conversation whose last message is
     * from the customer goes back to pending.
     */
    public static function importConversations(int $limit = 40): array
    {
        $token = self::storedPageAccessToken();
        if ($token === '') {
            return ['ok' => false, 'msg' => 'No Instagram Page token saved yet.'];
        }
        $business_id = optional(Business::first())->id;
        $system_user_id = $business_id ? optional(\DB::table('users')->where('business_id', $business_id)->orderBy('id')->first())->id : null;
        $http = new \GuzzleHttp\Client(['timeout' => 30]);
        $get = function ($path, $query) use ($http, $token) {
            $resp = $http->get(self::graphBase($token) . $path, ['query' => $query + ['access_token' => $token], 'http_errors' => false]);
            return [$resp->getStatusCode(), json_decode((string) $resp->getBody(), true) ?: []];
        };
        try {
            $igLogin = self::isInstagramLoginToken($token);
            [$code, $me] = $igLogin ? $get('me', ['fields' => 'id,user_id,username']) : $get('me', ['fields' => 'id,name,instagram_business_account{id,username}']);
            if ($code !== 200) {
                $kind = $igLogin ? 'an Instagram token' : (stripos($token, 'EA') === 0 ? 'a Facebook Page token' : 'not a token Meta recognizes (it should start with IG or EA)');
                return ['ok' => false, 'msg' => 'Meta rejected the saved token (' . $kind . ', ' . strlen($token) . ' characters): ' . ($me['error']['message'] ?? 'HTTP ' . $code)];
            }
            $igId = $igLogin ? ($me['user_id'] ?? $me['id'] ?? null) : ($me['instagram_business_account']['id'] ?? null);
            $igName = $igLogin ? ($me['username'] ?? null) : ($me['instagram_business_account']['username'] ?? null);
            [$code, $convs] = $get('me/conversations', ['platform' => 'instagram', 'limit' => $limit, 'fields' => 'id,updated_time,participants,messages.limit(25){id,message,created_time,from}']);
            if ($code !== 200) {
                return ['ok' => false, 'msg' => 'Connected to ' . ($me['name'] ?? 'the Page') . ', but Meta would not share DMs: ' . ($convs['error']['message'] ?? 'HTTP ' . $code)];
            }
            $tz = 'America/Los_Angeles';
            $new = 0; $updated = 0;
            foreach (($convs['data'] ?? []) as $cv) {
                $msgs = array_reverse((array) ($cv['messages']['data'] ?? [])); // oldest first
                $them = null;
                foreach ((array) ($cv['participants']['data'] ?? []) as $p) {
                    if (($p['id'] ?? '') !== $igId && ($p['username'] ?? '') !== $igName) { $them = $p; }
                }
                if (!$them || !$msgs) {
                    continue;
                }
                $in = []; $out = []; $lastFromThem = false; $first = null;
                foreach ($msgs as $m) {
                    $txt = trim((string) ($m['message'] ?? ''));
                    if ($txt === '') { $txt = '(photo or attachment)'; }
                    $at = \Carbon\Carbon::parse($m['created_time'])->setTimezone($tz);
                    $first = $first ?: $at;
                    $line = '[' . $at->format('n/j g:ia') . '] ' . $txt;
                    $fromThem = ($m['from']['id'] ?? '') === ($them['id'] ?? '-');
                    if ($fromThem) { $in[] = $line; } else { $out[] = $line; }
                    $lastFromThem = $fromThem;
                }
                if (!$in) {
                    continue; // we started it and they never wrote back
                }
                $ext = 'ig-conv-' . $cv['id'];
                $c = Communication::where('business_id', $business_id)->where('external_id', $ext)->first();
                $isNew = !$c;
                if ($isNew) {
                    $c = new Communication();
                    $c->business_id = $business_id;
                    $c->channel = 'instagram';
                    $c->external_id = $ext;
                    $c->created_by = $system_user_id;
                    $c->status = 'pending';
                    $c->created_at = $first;
                }
                $c->customer_name = '@' . ($them['username'] ?? $them['id']);
                $c->contact_info = 'IG user ' . $them['id'];
                $c->message = implode("\n", $in);
                $c->resolution_notes = $out ? implode("\n", $out) : null;
                $c->topic = $c->topic ?: Communication::guessTopic(implode(' ', $in));
                if ($lastFromThem && !$isNew && $c->status === 'resolved') {
                    $c->status = 'pending';
                }
                $c->save();
                $isNew ? $new++ : $updated++;
            }
            return ['ok' => true, 'msg' => 'Instagram (@' . ($igName ?: '?') . '): ' . $new . ' new conversations, ' . $updated . ' updated.'];
        } catch (\Throwable $e) {
            Log::warning('Instagram import failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Could not reach Instagram right now.'];
        }
    }

    public static function isInstagramLoginToken(string $token): bool
    {
        return stripos($token, 'IG') === 0;
    }

    /** Instagram-login tokens (IG...) use graph.instagram.com; Facebook Page tokens (EA...) use graph.facebook.com. */
    public static function graphBase(string $token): string
    {
        return self::isInstagramLoginToken($token) ? 'https://graph.instagram.com/v21.0/' : 'https://graph.facebook.com/v21.0/';
    }

    public function importNow()
    {
        $this->requireAdmin();
        $r = self::importConversations();
        if (request()->wantsJson()) {
            return response()->json($r);
        }
        return redirect()->back()->with('status', ['success' => $r['ok'] ? 1 : 0, 'msg' => $r['msg']]);
    }

    private function verifySignature(string $raw, string $header): bool
    {
        $secret = $this->appSecret();
        if ($secret === '' || $header === '') {
            return false;
        }
        if (strpos($header, 'sha256=') !== 0) {
            return false;
        }
        $expected = hash_hmac('sha256', $raw, $secret);
        return hash_equals($expected, substr($header, 7));
    }
}
