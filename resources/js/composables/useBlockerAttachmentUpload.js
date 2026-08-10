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
 * Tải file lên một test case (tự chia batch nếu >10 file).
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
 * Tải file chờ lưu lên từng test case (cùng thứ tự sau bulk-create).
 *
 * @param {{ pendingFiles?: { file: File }[] }[]} rows
 * @param {number[]} blockerIds
 * @param {{ onFinish?: () => void, onPartialError?: () => void }} hooks
 */
export function uploadAttachmentsForCreatedBlockers(rows, blockerIds, hooks = {}) {
    const pairs = rows.map((row, index) => ({
        blockerId: blockerIds[index],
        files: (row.pendingFiles ?? []).map((p) => p.file).filter(Boolean),
    })).filter((p) => p.blockerId != null && p.files.length > 0);

    if (!pairs.length) {
        hooks.onFinish?.();
        return;
    }

    let hadError = false;
    let pairIndex = 0;

    const nextPair = () => {
        if (pairIndex >= pairs.length) {
            if (hadError) hooks.onPartialError?.();
            hooks.onFinish?.();
            return;
        }
        const { blockerId, files } = pairs[pairIndex];
        pairIndex += 1;
        uploadFilesToBlocker(blockerId, files, {
            onPartialError: () => {
                hadError = true;
            },
            onFinish: () => nextPair(),
        });
    };

    nextPair();
}
