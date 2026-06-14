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
            return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
        }

        return $account->role === SystemRole::Admin
            || $account->role === SystemRole::Lead
            || ($account->employee_id && $account->employee_id === $article->author_id);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->role !== SystemRole::Viewer;
    }

    public function update(SystemAccount $account, KbArticle $article): bool
    {
        if ($account->role === SystemRole::Admin || $account->role === SystemRole::Lead) {
            return true;
        }

        return $account->employee_id && $account->employee_id === $article->author_id;
    }

    public function delete(SystemAccount $account, KbArticle $article): bool
    {
        return $account->role === SystemRole::Admin
            || ($account->role === SystemRole::Lead && $article->status === KbArticleStatus::Draft);
    }

    public function publish(SystemAccount $account, KbArticle $article): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true)
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
