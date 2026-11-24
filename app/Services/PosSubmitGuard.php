<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One cart = one sale (Sarah 2026-11-24).
 *
 * Every POS cart carries a random pos_submit_token (made on page load, renewed
 * only when the cart is cleared after a saved sale). The server claims the
 * token in a UNIQUE-keyed row before saving, so a second Cash/Card click, a
 * retry, or two requests that land in the same second can never create a
 * second sale for the same cart — the duplicate gets "already saved" instead.
 *
 * Before this, one double-click could ring the same sale 2-7 times: extra
 * stock taken off, extra reward points, store credit spent again (AJ Girard,
 * 4/16: one $45 sale saved 7x, -$14 credit, +294 points).
 */
class PosSubmitGuard
{
    const TABLE = 'pos_submit_tokens';

    protected static $ready = null;

    public static function ensureTable()
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            if (!Schema::hasTable(self::TABLE)) {
                Schema::create(self::TABLE, function (Blueprint $t) {
                    $t->bigIncrements('id');
                    $t->string('token', 64)->unique();
                    $t->unsignedInteger('business_id')->nullable();
                    $t->unsignedInteger('user_id')->nullable();
                    $t->unsignedInteger('transaction_id')->nullable();
                    $t->timestamps();
                    $t->index('created_at');
                });
            }
            self::$ready = true;
        } catch (\Throwable $e) {
            \Log::warning('PosSubmitGuard table unavailable: ' . $e->getMessage());
            self::$ready = false;
        }
        return self::$ready;
    }

    public static function clean($token)
    {
        return substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $token), 0, 64);
    }

    /**
     * Claim the token. Returns true when this request owns it (go ahead and
     * save), or the existing row when the token was already used (duplicate).
     * Returns null when the guard can't run (no token / no table) — the sale
     * then proceeds exactly as before.
     */
    public static function claim($token, $businessId, $userId)
    {
        $token = self::clean($token);
        if ($token === '' || !self::ensureTable()) {
            return null;
        }
        try {
            DB::table(self::TABLE)->insert([
                'token' => $token,
                'business_id' => $businessId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // A save that died mid-way (PHP timeout/crash) never released its
            // claim. After 2 minutes with no sale attached, let this request
            // take it over so the cart isn't stuck on "already being saved".
            $took = DB::table(self::TABLE)->where('token', $token)->whereNull('transaction_id')
                ->where('updated_at', '<', now()->subSeconds(120))
                ->update(['user_id' => $userId, 'updated_at' => now()]);
            if ($took) {
                return true;
            }
            $row = DB::table(self::TABLE)->where('token', $token)->first();
            if ($row) {
                return $row;
            }
            \Log::warning('PosSubmitGuard claim failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function markSaved($token, $transactionId)
    {
        try {
            DB::table(self::TABLE)->where('token', self::clean($token))
                ->update(['transaction_id' => $transactionId, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            \Log::warning('PosSubmitGuard markSaved failed: ' . $e->getMessage());
        }
    }

    /** The save failed — free the token so the cashier can try again. */
    public static function release($token)
    {
        try {
            DB::table(self::TABLE)->where('token', self::clean($token))
                ->whereNull('transaction_id')->delete();
        } catch (\Throwable $e) {
            \Log::warning('PosSubmitGuard release failed: ' . $e->getMessage());
        }
    }

    /** Response for a duplicate submit of an already-used cart token. */
    public static function duplicateResponse($row)
    {
        if (!empty($row->transaction_id)) {
            $inv = DB::table('transactions')->where('id', $row->transaction_id)->value('invoice_no');
            return [
                'success' => 0,
                'already_saved' => 1,
                'transaction_id' => (int) $row->transaction_id,
                'msg' => 'This sale was already saved' . ($inv ? ' (invoice ' . $inv . ')' : '') . ' — not ringing it twice.',
            ];
        }
        return [
            'success' => 0,
            'already_saving' => 1,
            'msg' => 'This sale is already being saved — please wait a moment.',
        ];
    }
}
