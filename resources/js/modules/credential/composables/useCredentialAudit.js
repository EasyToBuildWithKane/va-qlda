import axios from 'axios';

export async function fetchCredentialAuditLogs(credentialId, page = 1) {
    const { data } = await axios.get(
        route('api.credentials.audit-logs', { credential: credentialId }),
        { params: { page } },
    );
    return data;
}
