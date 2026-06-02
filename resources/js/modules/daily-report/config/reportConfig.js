// Cấu hình Báo cáo công việc hằng ngày — chuẩn HORENSO (報・連・相).
//
//   報告 Hōkoku  → BÁO CÁO  : báo cáo kết quả, tiến độ công việc.
//   連絡 Renraku → LIÊN LẠC : chia sẻ thông tin, vướng mắc, rủi ro.
//   相談 Sōdan   → TRAO ĐỔI : xin ý kiến, đề xuất, nhờ hỗ trợ.
//   計画 Keikaku → KẾ HOẠCH : định hướng ngày mai.
//
// Nội dung là HTML (trình soạn thảo Tiptap) — các mẫu dưới đây là HTML.

export const pillars = [
    {
        key: 'baocao', jp: '報告', romaji: 'Hōkoku', title: 'Báo cáo',
        subtitle: 'Kết quả & tiến độ công việc',
        desc: 'Báo cáo những việc đã làm, kết quả đạt được và tiến độ so với kế hoạch ban đầu.',
        color: 'brand',
    },
    {
        key: 'lienlac', jp: '連絡', romaji: 'Renraku', title: 'Liên lạc',
        subtitle: 'Thông tin cần chia sẻ',
        desc: 'Chia sẻ thông tin quan trọng, vướng mắc hoặc rủi ro để cả nhóm cùng nắm và xử lý kịp thời.',
        color: 'amber',
    },
    {
        key: 'traodoi', jp: '相談', romaji: 'Sōdan', title: 'Trao đổi',
        subtitle: 'Đề xuất & cần hỗ trợ',
        desc: 'Xin ý kiến, đề xuất cải tiến hoặc nhờ hỗ trợ từ quản lý / đồng nghiệp khi cần.',
        color: 'emerald',
    },
    {
        key: 'kehoach', jp: '計画', romaji: 'Keikaku', title: 'Kế hoạch',
        subtitle: 'Định hướng ngày mai',
        desc: 'Dự kiến các đầu việc ưu tiên cho ngày làm việc tiếp theo.',
        color: 'sky',
    },
];

export const fields = [
    {
        key: 'goals_today', pillar: 'baocao', label: 'Mục tiêu hôm nay', required: true,
        hint: 'Hôm nay bạn đặt ra những mục tiêu gì? Liệt kê 2–4 mục tiêu chính.',
        tooltip: 'Mục tiêu nên cụ thể, đo lường được (SMART). VD: “Hoàn thành API đăng nhập và viết unit test”.',
        placeholder: 'Nhập các mục tiêu chính trong ngày…',
    },
    {
        key: 'progress_update', pillar: 'baocao', label: 'Tiến độ thực hiện', required: true,
        hint: 'Bạn đã làm được những gì? Tiến độ đạt bao nhiêu % so với mục tiêu?',
        tooltip: 'Mô tả công việc đã thực hiện kèm trạng thái: đã xong, đang làm, tạm dừng.',
        placeholder: 'Mô tả công việc đã làm và tiến độ…',
    },
    {
        key: 'results_impact', pillar: 'baocao', label: 'Kết quả & tác động', required: true,
        hint: 'Kết quả cụ thể là gì và mang lại giá trị / ảnh hưởng ra sao?',
        tooltip: 'Gắn kết quả với con số hoặc tác động thực tế. VD: “Giảm 30% thời gian tải trang”.',
        placeholder: 'Kết quả cụ thể và giá trị mang lại…',
    },
    {
        key: 'highlights', pillar: 'baocao', label: 'Điểm nổi bật', required: false,
        hint: 'Thành tựu, điều học được hoặc khoảnh khắc đáng ghi nhận trong ngày (không bắt buộc).',
        tooltip: 'Ghi nhận điểm tích cực giúp duy trì động lực và chia sẻ kinh nghiệm cho nhóm.',
        placeholder: 'Thành tựu hoặc điều học được (không bắt buộc)…',
    },
    {
        key: 'blockers', pillar: 'lienlac', label: 'Khó khăn & vướng mắc', required: false,
        hint: 'Bạn đang gặp trở ngại gì? Ai/điều gì đang khiến công việc bị chậm? (không bắt buộc)',
        tooltip: 'Nêu rõ vướng mắc + mức độ ảnh hưởng + bạn cần gì để gỡ. Báo sớm để được hỗ trợ kịp thời.',
        placeholder: 'Khó khăn, vướng mắc, rủi ro cần chia sẻ (không bắt buộc)…',
    },
    {
        key: 'improvement_suggestions', pillar: 'traodoi', label: 'Đề xuất cải tiến', required: false,
        hint: 'Bạn có đề xuất gì để công việc / quy trình tốt hơn? Cần trao đổi điều gì? (không bắt buộc)',
        tooltip: 'Tinh thần Kaizen (改善): mọi đề xuất cải tiến nhỏ đều đáng giá. Nêu vấn đề kèm giải pháp.',
        placeholder: 'Đề xuất cải tiến hoặc nội dung cần trao đổi (không bắt buộc)…',
    },
    {
        key: 'plan_tomorrow', pillar: 'kehoach', label: 'Kế hoạch ngày mai', required: true,
        hint: 'Ngày mai bạn dự định làm gì? Sắp xếp theo thứ tự ưu tiên.',
        tooltip: 'Liệt kê đầu việc ưu tiên cho ngày tiếp theo để chủ động và liền mạch công việc.',
        placeholder: 'Các đầu việc dự kiến cho ngày mai…',
    },
];

// Mẫu báo cáo dựng sẵn — bấm “Áp dụng” để điền nhanh (nội dung HTML).
export const builtinTemplates = [
    {
        id: 'horenso-full',
        name: 'Mẫu chuẩn Horenso (đầy đủ)',
        badge: 'Khuyến nghị',
        desc: 'Khung báo cáo đầy đủ theo 報・連・相, phù hợp cho hầu hết công việc.',
        content: {
            goals_today: '<ul><li>Mục tiêu 1: </li><li>Mục tiêu 2: </li><li>Mục tiêu 3: </li></ul>',
            progress_update: '<ul><li>Đã hoàn thành: </li><li>Đang thực hiện (… %): </li><li>Chưa bắt đầu: </li></ul>',
            results_impact: '<ul><li><strong>Kết quả:</strong> </li><li><strong>Tác động:</strong> </li><li><strong>Số liệu:</strong> </li></ul>',
            highlights: '<ul><li>Điểm nổi bật: </li><li>Bài học rút ra: </li></ul>',
            blockers: '<ul><li>Vướng mắc: </li><li>Ảnh hưởng: </li><li>Cần hỗ trợ: </li></ul>',
            improvement_suggestions: '<ul><li>Đề xuất: </li><li>Lý do: </li><li>Lợi ích kỳ vọng: </li></ul>',
            plan_tomorrow: '<ol><li>(Ưu tiên cao) </li><li>(Ưu tiên trung bình) </li><li>(Nếu còn thời gian) </li></ol>',
        },
    },
    {
        id: 'short',
        name: 'Mẫu ngắn gọn',
        badge: 'Nhanh',
        desc: 'Tối giản cho ngày làm việc đơn giản — chỉ những mục cốt lõi.',
        content: {
            goals_today: '<ul><li></li></ul>',
            progress_update: '<ul><li></li></ul>',
            results_impact: '<p></p>',
            highlights: '',
            blockers: '',
            improvement_suggestions: '',
            plan_tomorrow: '<ol><li></li></ol>',
        },
    },
    {
        id: 'engineering',
        name: 'Mẫu Lập trình / Kỹ thuật',
        badge: 'Dev',
        desc: 'Dành cho lập trình viên: commit, pull request, kiểm thử, môi trường.',
        content: {
            goals_today: '<ul><li>Tính năng / Ticket: </li><li>Mục tiêu kỹ thuật: </li></ul>',
            progress_update: '<ul><li>Code: </li><li>Pull Request: #</li><li>Unit test (độ phủ … %): </li><li>Review: </li></ul>',
            results_impact: '<ul><li><strong>Tính năng hoàn thành:</strong> </li><li><strong>Hiệu năng / Chất lượng:</strong> </li><li><strong>Nợ kỹ thuật xử lý:</strong> </li></ul>',
            highlights: '<ul><li>Kỹ thuật / thư viện mới áp dụng: </li></ul>',
            blockers: '<ul><li>Lỗi / Bug đang chặn: </li><li>Phụ thuộc (API/đội khác): </li><li>Cần hỗ trợ: </li></ul>',
            improvement_suggestions: '<ul><li>Cải tiến code / quy trình: </li><li>Refactor đề xuất: </li></ul>',
            plan_tomorrow: '<ol><li>(Ưu tiên cao) </li><li></li></ol>',
        },
    },
    {
        id: 'incident',
        name: 'Mẫu xử lý sự cố / Blocker',
        badge: 'Sự cố',
        desc: 'Khi ngày làm việc tập trung xử lý sự cố hoặc vướng mắc lớn.',
        content: {
            goals_today: '<ul><li>Sự cố cần xử lý: </li><li>Mục tiêu khắc phục: </li></ul>',
            progress_update: '<ul><li>Thời điểm phát hiện: </li><li>Nguyên nhân (đã xác định / đang điều tra): </li><li>Biện pháp đã thực hiện: </li></ul>',
            results_impact: '<ul><li><strong>Trạng thái hiện tại:</strong> </li><li><strong>Phạm vi ảnh hưởng:</strong> </li><li><strong>Mức độ nghiêm trọng:</strong> </li></ul>',
            highlights: '',
            blockers: '<ul><li>Vấn đề còn tồn đọng: </li><li>Rủi ro nếu chưa xử lý: </li><li>Cần hỗ trợ gấp từ: </li></ul>',
            improvement_suggestions: '<ul><li>Phòng ngừa tái diễn: </li><li>Cập nhật quy trình / checklist: </li></ul>',
            plan_tomorrow: '<ol><li>Theo dõi &amp; xác nhận khắc phục triệt để</li><li>Viết báo cáo sự cố (post-mortem)</li><li></li></ol>',
        },
    },
];
