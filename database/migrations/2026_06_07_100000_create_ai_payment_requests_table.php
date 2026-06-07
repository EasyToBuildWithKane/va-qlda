<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_purchase_proposal_id')
                ->unique('ai_pr_proposal_unique')
                ->constrained('ai_purchase_proposals')
                ->cascadeOnDelete();
            $table->string('payment_request_code')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_document_paths')->nullable();
            $table->timestamps();

            $table->index('status', 'ai_pr_status_idx');
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $proposals = DB::table('ai_purchase_proposals')
            ->whereIn('status', ['approved', 'purchased', 'active', 'expired'])
            ->get(['id', 'cost_amount', 'status', 'reviewed_at', 'created_by', 'ai_account_id']);

        $date = now()->format('Ymd');
        $seq = 0;

        foreach ($proposals as $p) {
            if (DB::table('ai_payment_requests')->where('ai_purchase_proposal_id', $p->id)->exists()) {
                continue;
            }

            $seq++;
            $code = 'DNTT-'.$date.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $isLinked = ! empty($p->ai_account_id);
            $status = $isLinked ? 'paid' : 'approved';
            $paidAt = $isLinked ? ($p->reviewed_at ?? now()->toDateTimeString()) : null;

            DB::table('ai_payment_requests')->insert([
                'id' => Str::uuid()->toString(),
                'ai_purchase_proposal_id' => $p->id,
                'payment_request_code' => $code,
                'amount' => $p->cost_amount,
                'status' => $status,
                'created_by' => $p->created_by,
                'reviewed_by' => null,
                'reviewed_at' => $p->reviewed_at,
                'rejection_reason' => null,
                'paid_at' => $paidAt,
                'payment_document_paths' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_payment_requests');
    }
};
