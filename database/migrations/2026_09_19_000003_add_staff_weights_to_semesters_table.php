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
        Schema::table('semesters', function (Blueprint $table) {
            if (! Schema::hasColumn('semesters', 'staff_overall_max_points')) {
                $table->decimal('staff_overall_max_points', 8, 2)->default(100.00)->after('staff_max_points');
            }
            if (! Schema::hasColumn('semesters', 'staff_head_weight')) {
                $table->decimal('staff_head_weight', 5, 2)->default(50.00)->after('staff_overall_max_points');
            }
            if (! Schema::hasColumn('semesters', 'staff_peer_weight')) {
                $table->decimal('staff_peer_weight', 5, 2)->default(30.00)->after('staff_head_weight');
            }
            if (! Schema::hasColumn('semesters', 'staff_self_weight')) {
                $table->decimal('staff_self_weight', 5, 2)->default(20.00)->after('staff_peer_weight');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn([
                'staff_overall_max_points',
                'staff_head_weight',
                'staff_peer_weight',
                'staff_self_weight',
            ]);
        });
    }
};
