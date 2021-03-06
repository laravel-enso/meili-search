<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeilisearchSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('meilisearch_settings', function (Blueprint $table) {
            $table->increments('id');

            $table->boolean('enabled');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('meilisearch_settings');
    }
}
