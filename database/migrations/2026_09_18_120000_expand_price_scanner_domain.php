<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('brand')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable()->index();
            $table->string('mpn')->nullable()->index();
            $table->string('category')->nullable();
            $table->json('attributes')->nullable();
            $table->json('aliases')->nullable();
            $table->timestamps();
        });
        Schema::create('product_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('value');
            $table->string('normalized_value');
            $table->string('source')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();
            $table->unique(['type', 'normalized_value', 'canonical_product_id'], 'product_identifier_unique');
            $table->index(['type', 'normalized_value']);
        });
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->string('country', 2)->default('RO');
            $table->string('website')->nullable();
            $table->json('aliases')->nullable();
            $table->timestamps();
        });
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('canonical_product_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->after('seller')->constrained()->nullOnDelete();
            $table->string('normalized_title')->nullable()->after('title')->index();
            $table->string('brand')->nullable()->after('ean');
            $table->string('mpn')->nullable()->after('model');
            $table->unsignedBigInteger('old_price')->nullable()->after('price');
            $table->string('country', 2)->default('RO')->after('currency');
            $table->json('attributes')->nullable()->after('availability');
            $table->json('raw_metadata')->nullable()->after('attributes');
        });
        Schema::table('price_points', fn (Blueprint $table) => $table->string('currency', 3)->default('RON')->after('shipping'));
        Schema::create('offer_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('canonical_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('classification', 30)->default('unknown')->index();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('signals')->nullable();
            $table->string('review_status', 30)->default('pending')->index();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('normalized_company')->unique();
            $table->string('country', 2);
            $table->string('website')->nullable();
            $table->string('type', 30)->default('wholesaler');
            $table->boolean('b2b_registration_required')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canonical_product_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->text('source_url');
            $table->unsignedBigInteger('unit_price');
            $table->string('currency', 3);
            $table->unsignedInteger('moq')->nullable();
            $table->boolean('vat_included')->nullable();
            $table->string('stock_state')->default('unknown');
            $table->boolean('ships_to_romania')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->timestamp('last_verified_at');
            $table->timestamps();
            $table->unique(['supplier_id', 'external_id']);
        });
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('provider');
            $table->timestamp('rate_at');
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'provider', 'rate_at'], 'exchange_rate_unique');
        });
        Schema::table('source_states', function (Blueprint $table) {
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('last_error_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('source_states', fn (Blueprint $table) => $table->dropColumn(['consecutive_failures', 'last_error_code']));
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('supplier_offers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('offer_matches');
        Schema::table('price_points', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canonical_product_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn(['normalized_title', 'brand', 'mpn', 'old_price', 'country', 'attributes', 'raw_metadata']);
        });
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('product_identifiers');
        Schema::dropIfExists('canonical_products');
    }
};
