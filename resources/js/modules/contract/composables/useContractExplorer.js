import { computed, unref } from 'vue';

/** Inertia/JSON đôi khi trả collection PHP thành object — luôn chuẩn hoá mảng trước .map/.for..of. */
function toArray(value) {
    if (Array.isArray(value)) return value;
    if (value == null) return [];
    if (typeof value === 'object' && Array.isArray(value.data)) return value.data;
    return Object.values(value);
}

/**
 * Dựng cây Explorer 3 cấp: Nhà cung cấp → Nhóm dịch vụ → Hợp đồng.
 * Hợp đồng không có nhóm nằm trực tiếp dưới NCC; không có NCC gom vào
 * "Chưa gán nhà cung cấp". Pure — không gọi API.
 */
export function useContractExplorer(contractsRef, vendorsRef, categoriesRef) {
    const tree = computed(() => {
        const contracts = toArray(unref(contractsRef));
        const vendors = toArray(unref(vendorsRef));
        const categories = toArray(unref(categoriesRef));

        const categoryById = new Map(categories.map((c) => [c.id, c]));

        // Gom hợp đồng theo vendor → category
        const byVendor = new Map();
        const ensureVendor = (id) => {
            if (!byVendor.has(id)) {
                byVendor.set(id, { categories: new Map(), loose: [] });
            }
            return byVendor.get(id);
        };

        for (const contract of contracts) {
            const vendorKey = contract.vendor_id ?? '__none__';
            const bucket = ensureVendor(vendorKey);
            if (contract.category_id && categoryById.has(contract.category_id)) {
                if (!bucket.categories.has(contract.category_id)) {
                    bucket.categories.set(contract.category_id, []);
                }
                bucket.categories.get(contract.category_id).push(contract);
            } else {
                bucket.loose.push(contract);
            }
        }

        const buildVendorNode = (vendor, key) => {
            const bucket = byVendor.get(key) || { categories: new Map(), loose: [] };
            const categoryNodes = [...bucket.categories.entries()].map(([catId, list]) => ({
                type: 'category',
                id: catId,
                name: categoryById.get(catId)?.name ?? 'Nhóm dịch vụ',
                contracts: sortContracts(list),
                annualCost: sumAnnual(list),
            }));

            const allContracts = [...bucket.loose, ...[...bucket.categories.values()].flat()];

            return {
                type: 'vendor',
                id: vendor?.id ?? null,
                name: vendor?.name ?? 'Chưa gán nhà cung cấp',
                code: vendor?.code ?? null,
                categories: categoryNodes.sort((a, b) => a.name.localeCompare(b.name, 'vi')),
                looseContracts: sortContracts(bucket.loose),
                count: allContracts.length,
                annualCost: sumAnnual(allContracts),
            };
        };

        const nodes = vendors
            .map((v) => buildVendorNode(v, v.id))
            .filter((n) => n.count > 0);

        if (byVendor.has('__none__')) {
            nodes.push(buildVendorNode(null, '__none__'));
        }

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

function sortContracts(list) {
    return [...list].sort((a, b) => (a.name || '').localeCompare(b.name || '', 'vi'));
}

function sumAnnual(list) {
    return list.reduce((sum, c) => sum + Number(c.annual_cost || 0), 0);
}
