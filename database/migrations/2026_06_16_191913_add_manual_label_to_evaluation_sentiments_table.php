<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluation_sentiments', function (Blueprint $table) {
            $table->string('manual_label')->nullable()->after('dt_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_sentiments', function (Blueprint $table) {
            $table->dropColumn('manual_label');
        });
    }
};
