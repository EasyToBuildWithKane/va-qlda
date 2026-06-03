<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use Illuminate\Support\Collection;

/**
 * Tổng hợp chi phí theo nhóm — nguồn chi phí: phiếu đề xuất đã duyệt (xem AiAccountGrouper::summary).
 */
class AiAccountCostSummaryBuilder
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
    ) {}

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<string, mixed>
     */
    public function build(Collection $accounts): array
    {
        return $this->grouper->summary($accounts);
    }
}
