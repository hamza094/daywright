<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeMobileToStringInUserInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('mobile', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Normalize mobile values by stripping non-numeric characters
        // before converting back to unsignedBigInteger
        DB::table('user_infos')->get()->each(function ($userInfo) {
            if ($userInfo->mobile) {
                $cleaned = preg_replace('/[^0-9]/', '', $userInfo->mobile);
                DB::table('user_infos')
                    ->where('id', $userInfo->id)
                    ->update(['mobile' => $cleaned]);
            }
        });

        Schema::table('user_infos', function (Blueprint $table) {
            $table->unsignedBigInteger('mobile')->nullable()->change();
        });
    }
}
