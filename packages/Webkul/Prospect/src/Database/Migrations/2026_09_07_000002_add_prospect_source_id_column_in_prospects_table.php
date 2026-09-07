<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('prospects', function (Blueprint $table) {
            $table->integer('prospect_source_id')->unsigned()->nullable()->after('industry');
            $table->foreign('prospect_source_id')->references('id')->on('prospect_sources')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['prospect_source_id']);
            $table->dropColumn('prospect_source_id');
        });
    }
};
