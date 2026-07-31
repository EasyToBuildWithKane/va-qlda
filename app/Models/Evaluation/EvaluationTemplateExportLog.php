<?php

namespace App\Models\Evaluation;

use App\Models\SystemAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $exported_by
 * @property string $scope
 * @property string $format
 * @property int $row_count
 * @property array|null $columns
 * @property array|null $filters
 * @property string|null $filename
 */
class EvaluationTemplateExportLog extends Model
{
    protected $table = 'evaluation_template_export_logs';

    protected $fillable = [
        'exported_by',
        'scope',
        'format',
        'row_count',
        'columns',
        'filters',
        'filename',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'columns' => 'array',
        'filters' => 'array',
    ];

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'exported_by');
    }
}
