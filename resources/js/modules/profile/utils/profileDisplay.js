/** Text hiển thị khi field hồ sơ chưa có dữ liệu */
export const PROFILE_EMPTY_LABEL = '( chưa cập nhật )';

export function profileDisplayValue(value) {
    if (value === null || value === undefined || value === '') {
        return PROFILE_EMPTY_LABEL;
    }
    return value;
}

export function isProfileEmpty(value) {
    return value === null || value === undefined || value === '';
}

export function profileFieldState(value) {
    const empty = isProfileEmpty(value);
    return {
        text: empty ? PROFILE_EMPTY_LABEL : value,
        empty,
    };
}
