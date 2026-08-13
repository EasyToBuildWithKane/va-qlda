import { router } from '@inertiajs/vue3';

export const TEST_CASE_ATTACHMENTS_MAX_PER_REQUEST = 10;
export const TEST_CASE_ATTACHMENTS_MAX_PENDING = 20;
export const TEST_CASE_ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;


/**
 * @param {File[]} files
 * @param {number} size
 * @returns {File[][]}
 */
function chunkFiles(files, size) {
    const chunks = [];
    for (let i = 0; i < files.length; i += size) {
        chunks.push(files.slice(i, i + size));
    }
    return chunks;
}

/**
 * @param {number} testCaseId
 * @param {File[]} files
 * @param {{ onFinish?: () => void, onPartialError?: () => void }} hooks
 */
export function uploadFilesToTestCase(testCaseId, files, hooks = {}) {
    const list = [...files];
    if (!testCaseId || !list.length) {
        hooks.onFinish?.();
        return;
    }

    const batches = chunkFiles(list, TEST_CASE_ATTACHMENTS_MAX_PER_REQUEST);
    let hadError = false;
    let batchIndex = 0;

    const nextBatch = () => {
        if (batchIndex >= batches.length) {
            if (hadError) hooks.onPartialError?.();
            hooks.onFinish?.();
            return;
        }
        const batch = batches[batchIndex];
        batchIndex += 1;
        router.post(
            route('test-cases.attachments.store', { testCase: testCaseId }),
            { files: batch },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: () => {
                    hadError = true;
                },
                onFinish: () => nextBatch(),
            },
        );
    };

    nextBatch();
}
