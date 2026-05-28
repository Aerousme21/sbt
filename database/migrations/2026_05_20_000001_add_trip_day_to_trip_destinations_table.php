<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->integer('trip_day')->default(1)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->dropColumn('trip_day');
        });
    }
};
