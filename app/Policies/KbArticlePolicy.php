<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\SystemAccount;
use App\Support\Enums\KbArticleStatus;
use App\Support\Enums\SystemRole;

class KbArticlePolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, KbArticle $article): bool
    {
        if ($article->status === KbArticleStatus::Published) {
            return $this->canViewPublishedCategory($account, $article);
        }

        if ($article->status === KbArticleStatus::Archived) {
            return $account->isAdminTier() || $account->allows('kb.update');
        }

        return $account->isAdminTier()
            || $account->allows('kb.update')
            || ($account->employee_id && $account->employee_id === $article->author_id);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('kb.create');
    }

    public function update(SystemAccount $account, KbArticle $article): bool
    {
        return $account->allows('kb.update')
            || ($account->employee_id && $account->employee_id === $article->author_id);
    }

    public function delete(SystemAccount $account, KbArticle $article): bool
    {
        // Admin tier may delete any article; a finer kb.delete grant is limited
        // to drafts (mirrors the previous lead-deletes-drafts-only rule).
        return $account->allows('kb.delete')
            && ($account->isAdminTier() || $article->status === KbArticleStatus::Draft);
    }

    public function publish(SystemAccount $account, KbArticle $article): bool
    {
        return $account->allows('kb.publish')
            || ($account->employee_id && $account->employee_id === $article->author_id);
    }

    private function canViewPublishedCategory(SystemAccount $account, KbArticle $article): bool
    {
        $article->loadMissing('category');
        $slug = $article->category?->slug;

        if ($slug === 'internal-docs') {
            return $account->role !== SystemRole::Viewer;
        }

        return true;
    }
}
