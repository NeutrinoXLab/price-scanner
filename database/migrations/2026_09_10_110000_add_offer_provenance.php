<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', fn (Blueprint $t) => $t->string('access_method')->default('feed'));
    }

    public function down(): void
    {
        Schema::table('offers', fn (Blueprint $t) => $t->dropColumn('access_method'));
    }
};
