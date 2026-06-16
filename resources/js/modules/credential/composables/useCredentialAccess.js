import { httpClient } from '@/shared/services/http';

export function useCredentialAccess(credentialId) {
    async function grant(payload) {
        const { data } = await httpClient.post(
            route('api.credentials.access-grants.store', { credential: credentialId }),
            payload,
        );
        return data;
    }

    async function revoke(grantId) {
        const { data } = await httpClient.delete(
            route('api.credentials.access-grants.destroy', {
                credential: credentialId,
                accessGrant: grantId,
            }),
        );
        return data;
    }

    async function requestAccess(payload) {
        const { data } = await httpClient.post(
            route('api.credentials.access-requests.store', { credential: credentialId }),
            payload,
        );
        return data;
    }

    async function respondAccessRequest(requestId, decision) {
        const { data } = await httpClient.put(
            route('api.credentials.access-requests.respond', {
                credential: credentialId,
                accessRequest: requestId,
            }),
            { decision },
        );
        return data;
    }

    return { grant, revoke, requestAccess, respondAccessRequest };
}
