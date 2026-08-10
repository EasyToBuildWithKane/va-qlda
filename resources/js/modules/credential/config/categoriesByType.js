/**
 * Map credential_type → system_category values (mirror CredentialCategory).
 * Thêm cloud sau (Azure, GCP…): case enum PHP + đẩy value vào infrastructure[].
 */
export const CATEGORIES_BY_TYPE = {
    internal_system: [
        'cms',
        'landing_page',
        'crm',
        'erp',
        'hrm',
        'lms',
        'knowledge_base',
        'ai_platform',
    ],
    infrastructure: [
        'vps',
        'server',
        'hosting',
        'cdn',
        'dns',
        'domain',
        'database',
        'mail_server',
        'ssl',
        'aws',
        // cloud sau: azure, gcp, ...
    ],
    provider: [
        'cloud_provider',
        'hosting_provider',
        'sms_provider',
        'email_provider',
        'payment_gateway',
        'ai_services',
        'third_party_api',
        'api_key',
    ],
    working_account: ['admin_account', 'user_account', 'other'],
};

/** Flat list of all system_category values (for import validation). */
export const SYSTEM_CATEGORIES = Object.values(CATEGORIES_BY_TYPE).flat();

/** Mirror CredentialType::values() */
export const CREDENTIAL_TYPES = Object.keys(CATEGORIES_BY_TYPE);
