<?php

namespace App\Services\AiAccount;

/**
 * Tổng hợp chi phí theo nhóm — nguồn: cost_amount trên tài khoản AI.
 */
class AiAccountCostSummaryBuilder
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
    ) {}

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\AiAccount>  $accounts
     * @return array<string, mixed>
     */
    public function build(\Illuminate\Support\Collection $accounts): array
    {
        return $this->grouper->summary($accounts);
    }
}
