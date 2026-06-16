<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Vui lòng nhập tên nhóm dịch vụ.',
        ]);

        ContractCategory::create($data);

        return back()->with('success', 'Đã thêm nhóm dịch vụ.');
    }

    public function update(Request $request, ContractCategory $category): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Vui lòng nhập tên nhóm dịch vụ.',
        ]);

        $category->update($data);

        return back()->with('success', 'Đã cập nhật nhóm dịch vụ.');
    }

    public function destroy(ContractCategory $category): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        if ($category->contracts()->exists()) {
            return back()->with('error', 'Không thể xoá: nhóm đang có hợp đồng.');
        }

        $category->delete();

        return back()->with('success', 'Đã xoá nhóm dịch vụ.');
    }
}
