/**
 * Bình luận hiển thị ngay trước khi Inertia / Socket.IO phản hồi.
 */
export function authorFromPageUser(user) {
    const emp = user?.employee;
    return {
        id: emp?.id ?? user?.employee_id ?? null,
        name: emp?.name ?? user?.name ?? 'Bạn',
        avatar_path: emp?.avatar_path ?? null,
    };
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
