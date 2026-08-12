<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Models\TestCase;

class TestCasePolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, TestCase $testCase): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('testcase.create');
    }

    public function update(SystemAccount $account, TestCase $testCase): bool
    {
        return $account->allows('testcase.update')
            || $this->isOwner($account, $testCase);
    }

    public function delete(SystemAccount $account, TestCase $testCase): bool
    {
        return $account->allows('testcase.delete');
    }

    public function execute(SystemAccount $account, TestCase $testCase): bool
    {
        return $account->allows('testcase.execute')
            || $account->allows('testcase.update')
            || $this->isOwner($account, $testCase);
    }

    private function isOwner(SystemAccount $account, TestCase $testCase): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $testCase->owner_id;
    }
}
