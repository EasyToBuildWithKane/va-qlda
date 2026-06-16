/**
 * Bình luận hiển thị ngay trước khi Inertia / Socket.IO phản hồi.
 */
export function authorFromPageUser(user) {
    const emp = user?.employee;
    return {
        id: emp?.id ?? user?.employee_id ?? null,
        name: (emp?.full_name ?? emp?.name ?? user?.name ?? 'Bạn').trim(),
        avatar_path: emp?.avatar_path ?? null,
    };
}

export function normalizeCommentBody(body) {
    return String(body ?? '').trim();
}

/** Bình luận từ server thay thế bản optimistic (cùng nội dung / cùng tác giả). */
export function serverCommentMatchesPending(pending, serverComment) {
    if (!isPendingComment(pending) || isPendingComment(serverComment)) {
        return false;
    }
    if (normalizeCommentBody(pending.body) !== normalizeCommentBody(serverComment.body)) {
        return false;
    }
    const pendingAuthorId = pending.author?.id;
    const serverAuthorId = serverComment.author?.id;
    if (pendingAuthorId != null && serverAuthorId != null) {
        return pendingAuthorId === serverAuthorId;
    }
    return true;
}

export function findServerCommentForPending(serverComments, pending) {
    if (!isPendingComment(pending)) {
        return null;
    }
    const list = Array.isArray(serverComments) ? serverComments : [];
    return list.find((s) => serverCommentMatchesPending(pending, s)) ?? null;
}

export function createPendingComment(body, parentId, author) {
    return {
        id: `pending-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        body,
        parent_id: parentId,
        created_at: new Date().toISOString(),
        author,
        reactions: {},
        _pending: true,
    };
}

export function isPendingComment(comment) {
    return comment?._pending === true || String(comment?.id ?? '').startsWith('pending-');
}
