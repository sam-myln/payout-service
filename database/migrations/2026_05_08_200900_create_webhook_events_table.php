<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('event_id')->primary();

            /*
             * WARNING — `payload` is `json` which MySQL CANONICALIZES on insert:
             * whitespace stripped, key order normalized, duplicate keys collapsed.
             * The bytes you read back are NOT the bytes the provider sent.
             *
             * HMAC-SHA256 is computed over the original raw byte sequence.
             * After canonicalization the signature CANNOT be re-verified against
             * the stored `payload`. The `signature` column is therefore a
             * forensic/audit artifact only — useful for "did we accept a request
             * claiming this signature?" but not for "is this row's payload
             * genuinely from the provider?"
             *
             * This is acceptable for v1 because:
             * (a) verification happens once, synchronously, in WebhookController
             *     before insert;
             * (b) the inbox is the trust anchor — anything in the table already
             *     passed signature check;
             * (c) we never re-run verification on stored rows.
             *
             * DO NOT add any code path that re-verifies HMAC against
             * webhook_events.payload. It will fail intermittently (key order
             * varies by client/proxy) and the failures will look like attacks.
             *
             * If you find yourself wanting to re-verify (e.g. for a re-queue /
             * replay-from-inbox feature, audit-trail integrity check, or dispute
             * resolution against the provider), the column type must change first:
             *   - switch `payload` to LONGTEXT (or LONGBLOB if binary safety
             *     matters), storing the exact $rawBody string verbatim — no JSON
             *     parsing on write
             *   - keep a separate `payload_decoded` json column if you still
             *     want SQL-level querying on fields
             *   - only then is hash_hmac_equals(secret, payload, signature) safe
             *     to call on a stored row
             */
            $table->json('payload');
            $table->string('provider_payout_id')->index();
            $table->string('signature');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
