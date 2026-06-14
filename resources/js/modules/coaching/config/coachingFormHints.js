/** Gợi ý & placeholder form khóa Coaching / Mentoring */

export const COACHING_FORM_HINTS = {
    name: 'Tên ngắn gọn, dễ nhận diện trên dashboard và báo cáo. Ví dụ: «Coaching Laravel nâng cao — Q2/2026».',
    description: 'Mô tả phạm vi, đối tượng, hình thức (1-1, nhóm nhỏ), công cụ liên quan.',
    objectives: 'Liệt kê 3–5 mục tiêu đo lường được sau khóa (kỹ năng, deliverable, timeline).',
    student_name: 'Nhập tên học viên tự do — không bắt buộc chọn từ danh sách nhân sự hệ thống.',
    coach_name: 'Tên coach / mentor phụ trách chính (có thể là cộng tác viên bên ngoài).',
    start_date: 'Ngày bắt đầu dự kiến; dùng để theo dõi tiến độ và báo cáo tháng.',
    end_date: 'Ngày kết thúc dự kiến; nên sau hoặc bằng ngày bắt đầu.',
    total_fee: 'Học phí dự kiến toàn khóa (VNĐ). Chỉ nhập số — hệ thống format và đọc bằng chữ khi nhập.',
    hourly_rate: 'Đơn giá dự kiến mỗi giờ coaching (VNĐ) — dùng tham chiếu khi lập buổi học.',
    total_hours: 'Tổng số giờ đào tạo dự kiến cả khóa (có thể nhập lẻ, vd. 12,5).',
    status: '«Lên kế hoạch» khi mới tạo; chuyển «Đang diễn ra» khi bắt đầu buổi đầu tiên.',
};

export const COACHING_FORM_PLACEHOLDERS = {
    name: 'VD: Mentoring Vue 3 — An',
    description: 'Khóa 1-1, 2 buổi/tuần, tập trung component design và Inertia…',
    objectives: '• Hoàn thiện module X\n• Review PR độc lập\n• Demo cuối khóa',
    student_name: 'Nguyễn Văn A',
    coach_name: 'Trần Thị B — Senior Dev',
    total_fee: '15.000.000',
    hourly_rate: '800.000',
    total_hours: '24',
};
