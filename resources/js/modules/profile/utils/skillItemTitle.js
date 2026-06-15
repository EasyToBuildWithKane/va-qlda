export function skillItemTitle(item) {
    const name = (item?.name ?? '').trim();
    if (name) {
        return name;
    }
    const title = (item?.title ?? '').trim();
    if (title) {
        return title;
    }
    const note = (item?.note ?? '').trim();
    return note || null;
}
