import { currency, date, hours as fmtHours } from '@/composables/useFormat';

export function displayCourseStudent(val) {
    const t = typeof val === 'string' ? val.trim() : val;
    return t ? t : 'Chưa gán học viên';
}

export function displayCourseCoach(val) {
    const t = typeof val === 'string' ? val.trim() : val;
    return t ? t : 'Chưa gán coach';
}

export function displayCourseProgress(val) {
    if (val != null && val !== '') return `${val}%`;
    return 'Chưa có tiến độ';
}

export function displayCourseSessionsCount(val) {
    if (val == null || Number(val) === 0) return 'Chưa có buổi học';
    return `${Number(val)} buổi`;
}

export function displayCourseDateRange(start, end) {
    const s = start ? date(start) : null;
    const e = end ? date(end) : null;
    if (s && e && s !== '—' && e !== '—') return `${s} – ${e}`;
    if (s && s !== '—') return `Từ ${s}`;
    if (e && e !== '—') return `Đến ${e}`;
    return 'Chưa có thời gian';
}

export function displayCourseFee(val) {
    if (val === null || val === undefined || val === '') return 'Chưa có học phí';
    return currency(val);
}

export function displayCourseSingleDate(val) {
    if (!val) return 'Chưa có ngày';
    const d = date(val);
    return d === '—' ? 'Chưa có ngày' : d;
}

export function displayCourseHourlyRate(val) {
    if (val === null || val === undefined || val === '') return 'Chưa có đơn giá';
    return currency(val);
}

export function displayCourseTotalHours(val) {
    if (val != null && val !== '') return fmtHours(val);
    return 'Chưa ghi giờ dự kiến';
}

export function isCoursePlaceholderText(text) {
    return typeof text === 'string' && text.startsWith('Chưa');
}
