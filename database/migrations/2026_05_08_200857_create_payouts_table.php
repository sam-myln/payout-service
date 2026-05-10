<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('provider', 64)->index();
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('amount_minor');
            $table->char('currency', 8);
            $table->string('wallet');
            $table->string('external_reference')->index();
            $table->string('status');
            $table->string('provider_payout_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
