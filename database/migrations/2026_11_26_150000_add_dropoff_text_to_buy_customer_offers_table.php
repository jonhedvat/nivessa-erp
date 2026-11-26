<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Drop-off wait-time text on the buy form: when it went out, the rough item
// count and the wait quoted. See BuyFromCustomerController::dropoffText().
class AddDropoffTextToBuyCustomerOffersTable extends Migration
{
    public function up()
    {
        Schema::table('buy_customer_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('buy_customer_offers', 'dropoff_texted_at')) {
                $table->timestamp('dropoff_texted_at')->nullable();
                $table->unsignedInteger('dropoff_item_count')->nullable();
                $table->string('dropoff_wait', 16)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('buy_customer_offers', function (Blueprint $table) {
            foreach (['dropoff_texted_at', 'dropoff_item_count', 'dropoff_wait'] as $c) {
                if (Schema::hasColumn('buy_customer_offers', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
