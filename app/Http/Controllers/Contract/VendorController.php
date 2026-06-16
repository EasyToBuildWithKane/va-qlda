<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreVendorRequest;
use App\Http\Requests\Contract\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $account = $request->user();

        $query = Vendor::query()
            ->withCount('contracts')
            ->withSum('contracts as contracts_sum_annual_cost', 'annual_cost')
            ->orderBy('name');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('tax_code', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%"));
        }

        return Inertia::render('Contract/Vendors', [
            'vendors' => VendorResource::collection($query->get()),
            'filters' => (object) $request->only(['q']),
            'can' => [
                'create' => $account->can('create', Vendor::class),
            ],
        ]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        Vendor::create($request->validated());

        return back()->with('success', 'Đã thêm nhà cung cấp.');
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return back()->with('success', 'Đã cập nhật nhà cung cấp.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);

        if ($vendor->contracts()->exists()) {
            return back()->with('error', 'Không thể xoá: nhà cung cấp đang có hợp đồng.');
        }

        $vendor->delete();

        return back()->with('success', 'Đã xoá nhà cung cấp.');
    }
}
