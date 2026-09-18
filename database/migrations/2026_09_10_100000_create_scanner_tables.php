<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->string('source');
            $t->string('external_id');
            $t->string('seller');
            $t->string('title');
            $t->text('url');
            $t->string('ean')->nullable()->index();
            $t->string('model')->nullable();
            $t->string('sku')->nullable();
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('shipping')->nullable();
            $t->string('currency', 3)->default('RON');
            $t->string('availability')->default('unknown');
            $t->timestamp('checked_at');
            $t->timestamps();
            $t->unique(['source', 'external_id', 'seller']);
        });
        Schema::create('price_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('shipping')->nullable();
            $t->string('availability');
            $t->timestamp('observed_at');
        });
        Schema::create('watches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('threshold');
            $t->boolean('below')->default(false);
            $t->unsignedInteger('episode')->default(0);
            $t->timestamps();
        });
        Schema::create('price_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('watch_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('episode');
            $t->unsignedBigInteger('total');
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['watch_id', 'episode']);
        });
        Schema::create('source_states', function (Blueprint $t) {
            $t->string('source')->primary();
            $t->string('status')->default('disabled');
            $t->string('message')->nullable();
            $t->string('etag')->nullable();
            $t->string('last_modified')->nullable();
            $t->timestamp('last_attempt')->nullable();
            $t->timestamp('last_success')->nullable();
            $t->timestamp('next_attempt')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['price_alerts', 'watches', 'price_points', 'offers', 'source_states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
