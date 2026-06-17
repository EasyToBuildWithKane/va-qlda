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
            contracts: members,
        };
    }).sort((a, b) => b.annualCost - a.annualCost);
}

/**
 * Dựng cây Explorer: Nhà cung cấp → Nhóm dịch vụ → Bộ hợp đồng → Hợp đồng.
 * Nhóm dịch vụ là phân loại chung (Giáo vụ số, License, …), không theo NCC.
 */
export function useContractExplorer(contractsRef, vendorsRef) {
    const tree = computed(() => {
        const contracts = toArray(unref(contractsRef));
        const vendors = toArray(unref(vendorsRef));
        const vendorById = new Map(vendors.map((v) => [v.id, v]));

        const byVendor = new Map();
        for (const contract of contracts) {
            const vendorKey = contract.vendor_id ?? '__none__';
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
            const vendor = vendorKey === '__none__' ? null : vendorById.get(vendorKey);
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

            return {
                type: 'vendor',
                id: vendor?.id ?? null,
                name: vendor?.name ?? 'Chưa gán nhà cung cấp',
                code: vendor?.code ?? null,
                categories: categoryNodes,
                count: all.length,
                categoryCount: categoryNodes.length,
                setCount,
                annualCost: sumAnnual(all),
            };
        };

        const nodes = [...byVendor.entries()]
            .map(([key, byCategory]) => buildVendorNode(key, byCategory))
            .filter((n) => n.count > 0);

        return nodes.sort((a, b) => b.annualCost - a.annualCost);
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
