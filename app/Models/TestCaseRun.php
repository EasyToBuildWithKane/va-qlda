<?php

namespace App\Models;

use App\Support\Enums\TestCaseRunResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $test_case_id
 * @property TestCaseRunResult $result
 * @property string|null $actual_result
 * @property string|null $note
 * @property int|null $executed_by_id
 * @property \Illuminate\Support\Carbon $executed_at
 * @property int|null $blocker_id
 */
class TestCaseRun extends Model
{
    protected $fillable = [
        'test_case_id',
        'result',
        'actual_result',
        'note',
        'executed_by_id',
        'executed_at',
        'blocker_id',
    ];

    protected $casts = [
        'result' => TestCaseRunResult::class,
        'executed_at' => 'datetime',
    ];

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'executed_by_id');
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Blocker::class);
    }
}
