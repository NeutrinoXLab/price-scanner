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
        Schema::table('offers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('category')->nullable()->after('description');
            $table->string('channel', 3)->default('B2C')->after('country');
            $table->boolean('vat_included')->nullable()->after('channel');
            $table->unsignedInteger('moq')->nullable()->after('vat_included');
            $table->unsignedInteger('pack_quantity')->nullable()->after('moq');
            $table->text('image_url')->nullable()->after('url');
            $table->text('notes')->nullable()->after('raw_metadata');
            $table->timestamp('source_updated_at')->nullable()->after('checked_at');
            $table->index(['country', 'channel', 'availability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['country', 'channel', 'availability']);
            $table->dropColumn(['description', 'category', 'channel', 'vat_included', 'moq', 'pack_quantity', 'image_url', 'notes', 'source_updated_at']);
        });
    }
};
