import { computed, unref } from 'vue';

/** Inertia/JSON đôi khi trả collection PHP thành object — luôn chuẩn hoá mảng trước .map/.for..of. */
function toArray(value) {
    if (Array.isArray(value)) return value;
    if (value == null) return [];
    if (typeof value === 'object' && Array.isArray(value.data)) return value.data;
    return Object.values(value);
}

function resolvedAnnual(c) {
    return Number(c.annual_cost_resolved ?? c.annual_cost ?? 0);
}

function sumAnnual(list) {
    return list.reduce((sum, c) => sum + resolvedAnnual(c), 0);
}

function sumMonthly(list) {
    return list.reduce((sum, c) => sum + Number(c.monthly_cost ?? 0), 0);
}

function sumLifecycle(list) {
    return list.reduce((sum, c) => sum + Number(c.lifecycle_cost ?? 0), 0);
}

/** Map key thống nhất — JSON/Inertia đôi khi trả vendor_id dạng chuỗi. */
function vendorMapKey(vendorId) {
    if (vendorId == null || vendorId === '') return '__none__';
    const n = Number(vendorId);
    return Number.isNaN(n) ? String(vendorId) : n;
}

function lookupVendor(vendorById, vendorId) {
    if (vendorId == null) return null;
    return vendorById.get(vendorMapKey(vendorId)) ?? null;
}

/** Khoá bộ hợp đồng: hợp đồng gốc (root_contract_id = null) dùng id của chính nó. */
function setKeyOf(c) {
    return c.root_contract_id ?? c.id;
}

/** Sắp xếp trong bộ: gốc trước, sau đó theo ngày ký tăng dần (số lần ký). */
function sortSetMembers(list) {
    return [...list].sort((a, b) => {
        const aRoot = a.root_contract_id == null ? 0 : 1;
        const bRoot = b.root_contract_id == null ? 0 : 1;
        if (aRoot !== bRoot) return aRoot - bRoot;
        return String(a.signed_date || '').localeCompare(String(b.signed_date || ''));
    });
}

function categoryKeyOf(contract) {
    const name = contract.category?.name;
    if (name) return `n:${name}`;
    if (contract.category_id != null) return `id:${contract.category_id}`;
    return '__none__';
}

function categoryLabel(contract) {
    return contract.category?.name ?? 'Chưa phân nhóm';
}

function buildSetNodes(sets) {
    return [...sets.entries()].map(([key, list]) => {
        const members = sortSetMembers(list);
        const root = members.find((c) => c.root_contract_id == null) ?? members[0];
        return {
            key,
            name: root?.name ?? 'Hợp đồng',
            code: root?.code ?? null,
            signingCount: members.length,
            annualCost: sumAnnual(members),
            monthlyCost: sumMonthly(members),
            lifecycleCost: sumLifecycle(members),
            contracts: members,
        };
    }).sort((a, b) => b.annualCost - a.annualCost);
}

function hasNarrowingListFilters(filters) {
    if (!filters || typeof filters !== 'object') return false;
    return Boolean(
        filters.q || filters.status || filters.payment_status || filters.category_id,
    );
}

function emptyVendorNode(vendor) {
    return {
        type: 'vendor',
        id: vendor?.id ?? null,
        name: vendor?.name ?? 'Chưa gán nhà cung cấp',
        code: vendor?.code ?? null,
        categories: [],
        items: [],
        count: 0,
        categoryCount: 0,
        setCount: 0,
        annualCost: 0,
        monthlyCost: 0,
        lifecycleCost: 0,
    };
}

/**
 * Dựng cây Explorer: Nhà cung cấp → Nhóm dịch vụ → Bộ hợp đồng → Hợp đồng.
 * Nhóm dịch vụ là phân loại chung (Giáo vụ số, License, …), không theo NCC.
 */
export function useContractExplorer(contractsRef, vendorsRef, filtersRef = null) {
    const tree = computed(() => {
        const contracts = toArray(unref(contractsRef));
        const vendors = toArray(unref(vendorsRef));
        const filters = unref(filtersRef);
        const vendorById = new Map(vendors.map((v) => [vendorMapKey(v.id), v]));

        const byVendor = new Map();
        for (const contract of contracts) {
            const vendorKey = vendorMapKey(contract.vendor_id);
            if (!byVendor.has(vendorKey)) byVendor.set(vendorKey, new Map());
            const byCategory = byVendor.get(vendorKey);
            const catKey = categoryKeyOf(contract);
            if (!byCategory.has(catKey)) byCategory.set(catKey, new Map());
            const sets = byCategory.get(catKey);
            const sk = setKeyOf(contract);
            if (!sets.has(sk)) sets.set(sk, []);
            sets.get(sk).push(contract);
        }

        const buildVendorNode = (vendorKey, byCategory) => {
            const vendor = vendorKey === '__none__'
                ? null
                : (vendorById.get(vendorKey) ?? lookupVendor(vendorById, vendorKey));
            const categoryNodes = [...byCategory.entries()].map(([catKey, sets]) => {
                const sample = [...sets.values()].flat()[0];
                const setNodes = buildSetNodes(sets);
                const allInCat = [...sets.values()].flat();
                return {
                    key: catKey,
                    id: sample?.category?.id ?? sample?.category_id ?? null,
                    name: sample ? categoryLabel(sample) : 'Chưa phân nhóm',
                    sets: setNodes,
                    count: allInCat.length,
                    setCount: setNodes.length,
                    annualCost: sumAnnual(allInCat),
                };
            }).sort((a, b) => b.annualCost - a.annualCost);

            const all = categoryNodes.flatMap((c) => c.sets.flatMap((s) => s.contracts));
            const setCount = categoryNodes.reduce((n, c) => n + c.setCount, 0);

            const items = categoryNodes.flatMap((c) => c.sets.flatMap((s) => s.contracts));
            const vendorName = vendor?.name
                ?? items.find((c) => c.vendor?.name)?.vendor?.name
                ?? lookupVendor(vendorById, items[0]?.vendor_id)?.name
                ?? 'Chưa gán nhà cung cấp';

            return {
                type: 'vendor',
                id: vendor?.id ?? null,
                name: vendorName,
                code: vendor?.code ?? null,
                categories: categoryNodes,
                items,
                count: all.length,
                categoryCount: categoryNodes.length,
                setCount,
                annualCost: sumAnnual(all),
                monthlyCost: sumMonthly(all),
                lifecycleCost: sumLifecycle(all),
            };
        };

        let nodes = [...byVendor.entries()]
            .map(([key, byCategory]) => buildVendorNode(key, byCategory))
            .filter((n) => n.count > 0);

        const vendorIdFilter = filters?.vendor_id ? Number(filters.vendor_id) : null;
        const narrowing = hasNarrowingListFilters(filters);

        if (vendorIdFilter && !Number.isNaN(vendorIdFilter)) {
            if (!nodes.some((n) => Number(n.id) === vendorIdFilter)) {
                const v = vendorById.get(vendorIdFilter);
                if (v) nodes.push(emptyVendorNode(v));
            }
        } else if (!narrowing) {
            for (const v of vendors) {
                if (!nodes.some((n) => Number(n.id) === Number(v.id))) {
                    nodes.push(emptyVendorNode(v));
                }
            }
        }

        return nodes.sort((a, b) => {
            if (a.count === 0 && b.count !== 0) return 1;
            if (b.count === 0 && a.count !== 0) return -1;
            return b.annualCost - a.annualCost || String(a.name).localeCompare(String(b.name), 'vi');
        });
    });

    const totals = computed(() => {
        const contracts = toArray(unref(contractsRef));
        return {
            contracts: contracts.length,
            annualCost: sumAnnual(contracts),
        };
    });

    return { tree, totals };
}
