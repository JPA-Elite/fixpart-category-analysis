<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('type', 20)->index();          // part | appliance
            $table->string('name');
            $table->string('slug');
            $table->string('path')->index();              // dot path: mechanisch.gehauseteil.tur
            $table->unsignedSmallInteger('depth');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('meta')->nullable();            // synonyms, seo copy, icon, source ids
            $table->timestamps();

            $table->unique(['type', 'parent_id', 'slug']);
            $table->index(['type', 'depth']);
        });

        // Prefix search for subtrees (path LIKE 'mechanisch.gehauseteil.%')
        DB::statement('create index categories_path_prefix_idx on categories (path varchar_pattern_ops)');

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appliance_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('type_number');                // PNC / Typennummer, e.g. WGG244Z40
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'appliance_category_id', 'type_number']);
            $table->index('type_number');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();              // internal article number
            $table->string('mpn')->nullable();            // manufacturer number
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('condition', 20)->default('original'); // original|aftermarket|refurbished
            $table->unsignedInteger('price_cents')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('attributes')->nullable();
            $table->timestamps();

            $table->index(['condition', 'is_active']);
            $table->index('price_cents');
        });

        Schema::create('product_category', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('source', 20)->default('feed'); // feed|manual|rule
            $table->primary(['product_id', 'category_id']);
            $table->index(['category_id', 'is_primary']);
        });

        Schema::create('product_model', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('model_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('feed');
            $table->primary(['product_id', 'model_id']);
            $table->index('model_id');
        });

        // Supplier path -> our category, so re-imports stay stable.
        Schema::create('category_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);                 // euras|supplier-x
            $table->string('source_path');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source', 'source_path']);
        });

        // Generated landing pages (appliance x brand x part) without duplicating products.
        Schema::create('seo_landings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appliance_category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('path')->unique();
            $table->string('title');
            $table->text('meta_description')->nullable();
            $table->text('intro')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_landings');
        Schema::dropIfExists('category_aliases');
        Schema::dropIfExists('product_model');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('products');
        Schema::dropIfExists('models');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
