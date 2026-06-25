<?php

use Illuminate\Database\Migrations\Migration;
use App\System;

class EnableOfflinePayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        System::addProperty('enable_offline_payment', '1');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        System::removeProperty('enable_offline_payment');
    }
}
