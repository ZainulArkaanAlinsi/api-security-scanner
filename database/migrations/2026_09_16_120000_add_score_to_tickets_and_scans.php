<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('grade', 1)->nullable();
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('grade', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['score', 'grade']);
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['score', 'grade']);
        });
    }
};
