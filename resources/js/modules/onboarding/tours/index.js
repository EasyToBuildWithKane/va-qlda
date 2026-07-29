/**
 * Tour registry — single source of truth for guided-tour content.
 *
 * Keep keys in sync with App\Services\OnboardingService::TOURS (server whitelist).
 *
 * Each step: { el, title, desc, purpose, side?, align? }
 *   el      — CSS selector of the element to spotlight. Anchor to `data-tour="..."`.
 *             Steps whose element is absent (role-filtered nav) are skipped at runtime.
 *   desc    — mô tả ngắn (1–2 câu).
 *   purpose — "mục đích sử dụng" — hiển thị nổi bật dưới mô tả.
 *   side    — vị trí popover so với element: top | right | bottom | left.
 */

const TOURS = {
    // ── Bước 1–2: Tổng quan hệ thống + khám phá menu ────────────────────────
    system_overview: {
        title: 'Tổng quan hệ thống',
        estMinutes: 6,
        steps: [
            {
                el: '[data-tour="sidebar"]',
                title: 'Thanh điều hướng',
                desc: 'Tất cả module được nhóm theo nghiệp vụ ở thanh bên trái.',
                purpose: 'Di chuyển nhanh giữa các khu vực làm việc.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-overview"]',
                title: 'Bảng điều khiển',
                desc: 'Theo dõi KPI và tổng quan toàn hệ thống trong một màn hình.',
                purpose: 'Điểm bắt đầu mỗi ngày làm việc.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-projects"]',
                title: 'Công việc & Dự án',
                desc: 'Quản lý dự án, Sprint, Task, Kanban và vướng mắc.',
                purpose: 'Nơi triển khai và theo dõi công việc.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-daily"]',
                title: 'Báo cáo ngày',
                desc: 'Viết báo cáo hôm nay, xem lịch sử và phê duyệt.',
                purpose: 'Đồng bộ tiến độ hằng ngày của cả nhóm.',
                side: 'right',
            },
            {
                el: '[data-tour="topbar-notifications"]',
                title: 'Khu vực thông báo',
                desc: 'Nhận cảnh báo deadline, phân công và yêu cầu phê duyệt.',
                purpose: 'Không bỏ lỡ việc cần xử lý.',
                side: 'bottom',
                align: 'end',
            },
            {
                el: '[data-tour="topbar-user"]',
                title: 'Hồ sơ cá nhân',
                desc: 'Xem hồ sơ, đổi thông tin và đăng xuất tại đây.',
                purpose: 'Quản lý tài khoản của bạn.',
                side: 'bottom',
                align: 'end',
            },
            {
                el: '[data-tour="help-widget"]',
                title: 'Trợ giúp mọi lúc',
                desc: 'Mở lại hướng dẫn, FAQ, thuật ngữ hoặc gửi yêu cầu hỗ trợ.',
                purpose: 'Xem lại tour này bất cứ khi nào bạn cần.',
                side: 'left',
                align: 'end',
            },
        ],
    },

    // ── Hướng dẫn theo vai trò ──────────────────────────────────────────────
    role_admin: {
        title: 'Hướng dẫn cho Quản trị viên',
        estMinutes: 5,
        steps: [
            {
                el: '[data-tour="nav-people"]',
                title: 'Tổ chức & Nhân sự',
                desc: 'Quản lý sơ đồ tổ chức, phòng ban và thành viên.',
                purpose: 'Thiết lập cơ cấu nhân sự cho hệ thống.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-settings"]',
                title: 'Cấu hình hệ thống',
                desc: 'Cấu hình chung, đăng nhập, email và phân quyền theo vai trò.',
                purpose: 'Kiểm soát toàn bộ hành vi hệ thống.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-system"]',
                title: 'Hệ thống & Vận hành',
                desc: 'Trung tâm thông báo và vận hành cho quản trị viên.',
                purpose: 'Điều phối thông báo toàn hệ thống.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-performance"]',
                title: 'Hiệu suất & Audit',
                desc: 'Bảng hiệu suất tổng hợp và nhật ký audit nhân sự.',
                purpose: 'Giám sát và đánh giá hoạt động.',
                side: 'right',
            },
        ],
    },

    role_pm: {
        title: 'Hướng dẫn cho Quản lý dự án',
        estMinutes: 5,
        steps: [
            {
                el: '[data-tour="nav-projects"]',
                title: 'Quản lý dự án',
                desc: 'Tạo dự án, lập Sprint, phân công Task và theo dõi Kanban.',
                purpose: 'Trục chính của công việc quản lý dự án.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-daily"]',
                title: 'Theo dõi báo cáo',
                desc: 'Xem báo cáo ngày của nhóm và phê duyệt.',
                purpose: 'Nắm tiến độ hằng ngày của thành viên.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-performance"]',
                title: 'Hiệu suất nhóm',
                desc: 'Đọc bảng hiệu suất và audit để đánh giá.',
                purpose: 'Cân bằng khối lượng và đánh giá KPI.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-people"]',
                title: 'Thành viên & Nguồn lực',
                desc: 'Xem cơ cấu nhóm và hồ sơ thành viên.',
                purpose: 'Phân bổ đúng người cho đúng việc.',
                side: 'right',
            },
        ],
    },

    role_lead: {
        title: 'Hướng dẫn cho Trưởng nhóm',
        estMinutes: 4,
        steps: [
            {
                el: '[data-tour="nav-projects"]',
                title: 'Phân công công việc',
                desc: 'Giao Task, đặt deadline và độ ưu tiên cho thành viên.',
                purpose: 'Tổ chức công việc cho nhóm.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-daily"]',
                title: 'Theo dõi tiến độ',
                desc: 'Duyệt báo cáo ngày và nắm tình hình nhóm.',
                purpose: 'Phát hiện sớm rủi ro và vướng mắc.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-performance"]',
                title: 'Đánh giá thành viên',
                desc: 'Dùng bảng hiệu suất để đánh giá đóng góp.',
                purpose: 'Ghi nhận và cải thiện hiệu suất.',
                side: 'right',
            },
        ],
    },

    role_member: {
        title: 'Hướng dẫn cho Thành viên',
        estMinutes: 4,
        steps: [
            {
                el: '[data-tour="nav-projects"]',
                title: 'Nhận và cập nhật Task',
                desc: 'Xem công việc được giao, cập nhật trạng thái và tiến độ.',
                purpose: 'Theo dõi việc của chính bạn.',
                side: 'right',
            },
            {
                el: '[data-tour="nav-daily"]',
                title: 'Báo cáo công việc',
                desc: 'Viết báo cáo hôm nay để đồng bộ với nhóm.',
                purpose: 'Cập nhật tiến độ hằng ngày.',
                side: 'right',
            },
        ],
    },
};

/** Map role → tour key mặc định cho "Hướng dẫn theo vai trò". */
export const ROLE_TOUR = {
    admin: 'role_admin',
    lead: 'role_pm',
    member: 'role_member',
    viewer: 'role_pm',
};

export function getTour(key) {
    return TOURS[key] || null;
}

export function tourTitle(key) {
    return TOURS[key]?.title || key;
}

export function tourEstMinutes(key) {
    return TOURS[key]?.estMinutes || 0;
}

export default TOURS;
