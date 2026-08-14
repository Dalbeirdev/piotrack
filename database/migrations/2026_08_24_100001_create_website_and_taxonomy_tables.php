<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The 24 MSP service lines (SVC) as a first-class taxonomy.
        Schema::create('service_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('category')->nullable();  // managed_it|security|cloud|continuity|comms|advisory|compliance
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });

        // The 12 industry verticals (VERT).
        Schema::create('verticals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            // Vertical-specific compliance framing (VERT-020).
            $table->text('compliance_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // One page model serves the whole MSP page architecture rather than
            // three near-identical tables (WEB-012/013/014/015/016).
            $table->string('type')->default('landing'); // home|service|vertical|location|landing|campaign|resource
            $table->string('slug');
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('headline')->nullable();
            $table->string('subheadline')->nullable();
            $table->string('template')->default('standard');
            $table->string('status')->default('draft'); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);

            // Targeting bindings — what makes the taxonomy useful rather than decorative.
            $table->foreignId('service_line_id')->nullable()->constrained('service_lines')->nullOnDelete();
            $table->foreignId('vertical_id')->nullable()->constrained('verticals')->nullOnDelete();
            $table->foreignId('seo_location_id')->nullable()->constrained('seo_locations')->nullOnDelete();
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->timestamps();

            // Public pages share one URL space (/s/{slug}) across tenants, so the
            // slug must be globally unique — a per-tenant unique index would let
            // two organizations claim the same URL and make one unreachable.
            // Same convention as booking pages and public forms.
            $table->unique('slug');
            $table->index(['organization_id', 'type', 'status']);
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('site_page_id')->constrained('site_pages')->cascadeOnDelete();
            // Trust/proof blocks (WEB-023…029) are section types, not bespoke pages.
            $table->string('type'); // hero|content|services|trust|reviews|testimonials|logos|case_studies|awards|cta|faq|offer
            $table->string('heading')->nullable();
            $table->text('body')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'site_page_id', 'sort_order']);
        });

        Schema::create('site_navigation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('site_page_id')->nullable()->constrained('site_pages')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('site_navigation')->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('placement')->default('header'); // header|footer
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'placement', 'sort_order']);
        });

        // Multi-location (MLOC) extends the Stage 7 NAP location record rather
        // than introducing a second, competing notion of "location".
        Schema::table('seo_locations', function (Blueprint $table) {
            $table->string('territory')->nullable()->after('country');
            $table->string('gbp_place_id')->nullable()->after('territory');
            $table->boolean('is_active')->default(true)->after('gbp_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('seo_locations', function (Blueprint $table) {
            $table->dropColumn(['territory', 'gbp_place_id', 'is_active']);
        });

        Schema::dropIfExists('site_navigation');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('verticals');
        Schema::dropIfExists('service_lines');
    }
};
