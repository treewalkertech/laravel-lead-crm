<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('stage_entered_at')->nullable()->after('closed_at');
        });

        /**
         * Backfill existing leads so "days in stage" has something to work with immediately,
         * rather than treating every pre-existing lead as having just entered its current stage.
         */
        DB::table('leads')->update([
            'stage_entered_at' => DB::raw(DB::getTablePrefix().'created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('stage_entered_at');
        });
    }
};
