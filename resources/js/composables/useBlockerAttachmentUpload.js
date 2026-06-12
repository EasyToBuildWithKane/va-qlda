import { router } from '@inertiajs/vue3';

/** Khớp `BlockerAttachmentController` — max file mỗi request. */
export const BLOCKER_ATTACHMENTS_MAX_PER_REQUEST = 10;

/**
 * @param {File[]} files
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
 * Tải file lên một vướng mắc (tự chia batch nếu >10 file).
 *
 * @param {number} blockerId
 * @param {File[]} files
 * @param {{ onFinish?: () => void, onPartialError?: () => void }} hooks
 */
export function uploadFilesToBlocker(blockerId, files, hooks = {}) {
    const list = [...files];
    if (!blockerId || !list.length) {
        hooks.onFinish?.();
        return;
    }

    const batches = chunkFiles(list, BLOCKER_ATTACHMENTS_MAX_PER_REQUEST);
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
            `/blockers/${blockerId}/attachments`,
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

/**
 * Tải cùng bộ file lên lần lượt cho nhiều vướng mắc (sau bulk-create).
 *
 * @param {number[]} blockerIds
 * @param {File[]} files
 * @param {{ onFinish?: () => void, onPartialError?: () => void }} hooks
 */
export function uploadFilesToBlockers(blockerIds, files, hooks = {}) {
    const ids = [...blockerIds];
    const list = [...files];
    if (!ids.length || !list.length) {
        hooks.onFinish?.();
        return;
    }

    let hadError = false;

    const nextBlocker = () => {
        const id = ids.shift();
        if (id == null) {
            if (hadError) hooks.onPartialError?.();
            hooks.onFinish?.();
            return;
        }
        uploadFilesToBlocker(id, list, {
            onPartialError: () => {
                hadError = true;
            },
            onFinish: () => nextBlocker(),
        });
    };

    nextBlocker();
}
