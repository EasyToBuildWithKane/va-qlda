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

/**
 * Dựng cây Explorer: Nhà cung cấp → Bộ hợp đồng (gốc + gia hạn/phụ lục) → Hợp đồng.
 * "Số lần ký" = số bản trong bộ. Hợp đồng không có NCC gom vào
 * "Chưa gán nhà cung cấp". Pure — không gọi API.
 */
export function useContractExplorer(contractsRef, vendorsRef) {
    const tree = computed(() => {
        const contracts = toArray(unref(contractsRef));
        const vendors = toArray(unref(vendorsRef));
        const vendorById = new Map(vendors.map((v) => [v.id, v]));

        // Gom hợp đồng theo vendor → bộ hợp đồng.
        const byVendor = new Map();
        for (const contract of contracts) {
            const vendorKey = contract.vendor_id ?? '__none__';
            if (!byVendor.has(vendorKey)) byVendor.set(vendorKey, new Map());
            const sets = byVendor.get(vendorKey);
            const sk = setKeyOf(contract);
            if (!sets.has(sk)) sets.set(sk, []);
            sets.get(sk).push(contract);
        }

        const buildVendorNode = (vendorKey, sets) => {
            const vendor = vendorKey === '__none__' ? null : vendorById.get(vendorKey);
            const setNodes = [...sets.entries()].map(([key, list]) => {
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
            });

            const all = [...sets.values()].flat();
            return {
                type: 'vendor',
                id: vendor?.id ?? null,
                name: vendor?.name ?? 'Chưa gán nhà cung cấp',
                code: vendor?.code ?? null,
                sets: setNodes.sort((a, b) => b.annualCost - a.annualCost),
                count: all.length,
                setCount: setNodes.length,
                annualCost: sumAnnual(all),
            };
        };

        const nodes = [...byVendor.entries()]
            .map(([key, sets]) => buildVendorNode(key, sets))
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
