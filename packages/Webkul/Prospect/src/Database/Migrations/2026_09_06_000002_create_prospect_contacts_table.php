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
        Schema::create('prospect_contacts', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('prospect_id')->unsigned();
            $table->foreign('prospect_id')->references('id')->on('prospects')->onDelete('cascade');

            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();

            /**
             * 'new' means no call attempt has been made yet, distinct from 'call_not_picked' (an
             * attempt was made and failed to connect).
             */
            $table->string('status')->default('new');
            $table->text('comment')->nullable();

            $table->integer('converted_lead_id')->unsigned()->nullable();
            $table->foreign('converted_lead_id')->references('id')->on('leads')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prospect_contacts');
    }
};
