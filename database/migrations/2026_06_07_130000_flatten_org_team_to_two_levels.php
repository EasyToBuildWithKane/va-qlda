<?php

use App\Models\OrgTeam;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            OrgTeam::query()
                ->where('level', 3)
                ->orderBy('id')
                ->each(function (OrgTeam $team) {
                    $middle = $team->parent;
                    $newParentId = $middle?->parent_id;

                    $team->update([
                        'parent_id' => $newParentId,
                        'level' => 2,
                    ]);
                });

            OrgTeam::query()
                ->where('level', 2)
                ->whereDoesntHave('children')
                ->whereHas('parent', fn ($q) => $q->where('level', 1))
                ->each(function (OrgTeam $middle) {
                    if ($middle->members()->exists() || $middle->leader_id) {
                        return;
                    }

                    $middle->delete();
                });

            OrgTeam::query()->where('level', '>', 2)->update(['level' => 2]);
        });
    }

    public function down(): void
    {
        // Không hoàn tác — cấu trúc cũ 3 cấp không khôi phục tự động.
    }
};
