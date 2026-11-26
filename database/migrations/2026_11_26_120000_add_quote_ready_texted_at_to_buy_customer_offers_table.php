<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// When the "your quote is ready" Quo text went to the seller, so re-saving the
// buy form doesn't text them twice. See BuyFromCustomerController::textQuoteReady().
class AddQuoteReadyTextedAtToBuyCustomerOffersTable extends Migration
{
    public function up()
    {
        Schema::table('buy_customer_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('buy_customer_offers', 'quote_ready_texted_at')) {
                $table->timestamp('quote_ready_texted_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('buy_customer_offers', function (Blueprint $table) {
            if (Schema::hasColumn('buy_customer_offers', 'quote_ready_texted_at')) {
                $table->dropColumn('quote_ready_texted_at');
            }
        });
    }
}
