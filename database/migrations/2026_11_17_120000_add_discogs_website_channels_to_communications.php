<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One hub for every channel (Sarah 2026-11-17): Discogs buyer messages and website contact messages. */
class AddDiscogsWebsiteChannelsToCommunications extends Migration
{
    public function up()
    {
        if (Schema::hasTable('communications')) {
            DB::statement("ALTER TABLE communications MODIFY COLUMN channel ENUM('phone_1', 'phone_2', 'instagram', 'whatsapp', 'facebook', 'tiktok', 'email', 'discogs', 'website', 'other') NOT NULL DEFAULT 'other'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('communications')) {
            DB::statement("UPDATE communications SET channel = 'other' WHERE channel IN ('discogs', 'website')");
            DB::statement("ALTER TABLE communications MODIFY COLUMN channel ENUM('phone_1', 'phone_2', 'instagram', 'whatsapp', 'facebook', 'tiktok', 'email', 'other') NOT NULL DEFAULT 'other'");
        }
    }
}
