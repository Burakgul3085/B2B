<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active')->index();
            $table->string('status_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            // Varsayılan sevk deposu Aşama 3'te depolar tablosuyla birlikte bağlanır.
            $table->unsignedBigInteger('default_warehouse_id')->nullable();
            $table->enum('stock_display', ['exact', 'level'])->default('level');
            $table->enum('ledger_post_on', ['approval', 'completion'])->default('approval');
            $table->unsignedSmallInteger('default_payment_term_days')->default(30);
            $table->string('order_no_prefix', 10)->default('SIP');
            $table->enum('price_display', ['excl_vat', 'incl_vat'])->default('excl_vat');
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 40);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_dealers')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'ends_on']);
        });

        Schema::create('business_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 40);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'module_key']);
        });

        // K12: her işletmede tek giriş hesabı.
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->enum('status', ['pending_activation', 'active', 'disabled'])->default('pending_activation');
            $table->timestamp('activated_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('support_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('business_users');
            $table->string('reason', 500);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'expires_at']);
        });

        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->enum('guard', ['platform', 'business', 'dealer']);
            $table->unsignedBigInteger('user_id');
            $table->enum('purpose', ['activation', 'password_reset']);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['guard', 'user_id', 'purpose']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('error_events', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('level', 20);
            $table->string('exception_class')->nullable();
            $table->string('message', 1000);
            $table->string('location', 500)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['error_events', 'system_settings', 'audit_logs', 'auth_tokens', 'support_access_grants',
            'business_users', 'business_modules', 'licenses', 'business_settings', 'businesses', 'platform_admins'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
