import { httpClient } from '@/shared/services/http';

export async function fetchAiAccountGrants(aiAccountId) {
    const { data } = await httpClient.get(
        route('api.ai-accounts.access-grants.index', { aiAccount: aiAccountId }),
    );
    return data?.data ?? [];
}

export async function grantAiAccountAccess(aiAccountId, payload) {
    const { data } = await httpClient.post(
        route('api.ai-accounts.access-grants.store', { aiAccount: aiAccountId }),
        payload,
    );
    return data;
}

export async function revokeAiAccountAccess(aiAccountId, grantId) {
    const { data } = await httpClient.delete(
        route('api.ai-accounts.access-grants.destroy', {
            aiAccount: aiAccountId,
            accessGrant: grantId,
        }),
    );
    return data;
}

export function useAiAccountAccess() {
    return {
        fetchGrants: fetchAiAccountGrants,
        grant: grantAiAccountAccess,
        revoke: revokeAiAccountAccess,
    };
}
