/**
 * Hành động nhanh cho trợ lý mascot cổng Phòng Công Nghệ.
 */
export function congngheMascotActions({ onProposalPage = false } = {}) {
    const section = (id, label) => ({
        key: id,
        label,
        href: `/congnghe#${id}`,
        kind: 'section',
    });

    const items = [];

    if (!onProposalPage) {
        items.push({
            key: 'proposal',
            label: 'Đề xuất giải pháp phần mềm',
            href: '/congnghe/de-xuat',
            kind: 'primary',
        });
    } else {
        items.push({
            key: 'portal',
            label: 'Về trang Phòng Công Nghệ',
            href: '/congnghe',
            kind: 'primary',
        });
    }

    items.push(
        section('gioi-thieu', 'Giới thiệu phòng'),
        section('san-pham', 'Hệ sinh thái sản phẩm'),
        section('du-an', 'Dự án triển khai'),
        section('lo-trinh', 'Lộ trình 2026–2027'),
        {
            key: 'email',
            label: 'Email phòng Công nghệ',
            href: 'mailto:phongcongnghe@vaschools.edu.vn',
            kind: 'mailto',
        },
    );

    return items;
}
