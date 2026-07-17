<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            $this->repairPartiallyCreatedTable();

            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }

    private function repairPartiallyCreatedTable(): void
    {
        if (! Schema::hasColumn('push_subscriptions', 'endpoint_hash')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->string('endpoint_hash', 64)->nullable()->after('endpoint');
            });
        }

        DB::table('push_subscriptions')
            ->whereNull('endpoint_hash')
            ->orderBy('id')
            ->get(['id', 'endpoint'])
            ->each(function ($subscription) {
                DB::table('push_subscriptions')
                    ->where('id', $subscription->id)
                    ->update(['endpoint_hash' => hash('sha256', $subscription->endpoint)]);
            });

        if (! $this->hasIndex('push_subscriptions_endpoint_hash_unique')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->unique('endpoint_hash');
            });
        }
    }

    private function hasIndex(string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'push_subscriptions')
            ->where('index_name', $index)
            ->exists();
    }
};
