<?php

/*
|--------------------------------------------------------------------------
| Nội dung trang giới thiệu Phòng Công Nghệ (/congnghe)
|--------------------------------------------------------------------------
|
| Đây là NGUỒN MẶC ĐỊNH cho toàn bộ nội dung "tĩnh" của landing /congnghe.
| Admin có thể ghi đè từng mục tại /congnghe/quan-tri; bản ghi đè lưu ở bảng
| va_prd_congnghe_sections và được merge chồng lên các default này lúc đọc
| (xem App\Support\Congnghe\CongngheContentRepository). Bảng trống ⇒ trang
| hiển thị y hệt các giá trị bên dưới.
|
| Mỗi section gồm:
|   label      — tên hiển thị trong trang quản trị
|   icon       — AppIcon name dùng cho danh sách section ở admin
|   orderable  — true nếu section nằm trong luồng <main> (đổi thứ tự được)
|   position   — thứ tự mặc định (chỉ áp dụng cho section orderable)
|   content    — payload nội dung (text + danh sách item) có thể chỉnh
|
| Field "icon" trong các item là KEY vào catalog App\Support\Congnghe\
| CongngheIcons (không phải SVG path thô). Field "key" trong hero/impact là
| key của một chỉ số trong mảng $metrics do controller tính (xem
| CongngheContentSchema::METRIC_KEYS).
|
*/

return [

    // ── Chrome: thẻ <Head> ────────────────────────────────────────────
    'meta' => [
        'label' => 'Thẻ trang (SEO)',
        'icon' => 'settings',
        'orderable' => false,
        'position' => 0,
        'content' => [
            'title' => 'Phòng Công Nghệ',
        ],
    ],

    // ── Chrome: thanh điều hướng ──────────────────────────────────────
    'nav' => [
        'label' => 'Thanh điều hướng',
        'icon' => 'overview',
        'orderable' => false,
        'position' => 0,
        'content' => [
            'brand_name' => 'Phòng Công Nghệ',
            'brand_tagline' => 'VAS · TECH PORTAL',
            'links' => [
                ['label' => 'Giới thiệu', 'anchor' => '#gioi-thieu'],
                ['label' => 'Thành tựu', 'anchor' => '#thanh-tuu'],
                ['label' => 'Sản phẩm', 'anchor' => '#san-pham'],
                ['label' => 'Tổ chức', 'anchor' => '#to-chuc'],
                ['label' => 'Dự án', 'anchor' => '#du-an'],
                ['label' => 'Lộ trình', 'anchor' => '#lo-trinh'],
            ],
        ],
    ],

    // ── Hero (luôn ghim đầu trang) ────────────────────────────────────
    'hero' => [
        'label' => 'Hero (đầu trang)',
        'icon' => 'rocket',
        'orderable' => false,
        'position' => 0,
        'content' => [
            'title_prefix' => 'Kiến tạo',
            'title_highlight' => 'nền tảng số',
            'title_suffix' => 'cho giáo dục tương lai',
            'description' => 'Phòng Công Nghệ xây dựng hạ tầng dữ liệu, sản phẩm phần mềm và năng lực AI phục vụ toàn hệ thống — biến mỗi quy trình thành sản phẩm thật, vận hành và đo lường được.',
            'cta_primary' => ['label' => 'Khám phá hệ sinh thái', 'anchor' => '#san-pham'],
            'cta_secondary' => ['label' => 'Đội ngũ & tổ chức', 'anchor' => '#to-chuc'],
            'highlights' => [
                ['key' => 'projects', 'label' => 'Dự án', 'suffix' => '+'],
                ['key' => 'orgPeople', 'label' => 'Nhân sự sơ đồ', 'suffix' => ''],
                ['key' => 'departments', 'label' => 'Phòng ban', 'suffix' => ''],
            ],
        ],
    ],

    // ── Giới thiệu ────────────────────────────────────────────────────
    'about' => [
        'label' => 'Giới thiệu',
        'icon' => 'knowledge',
        'orderable' => true,
        'position' => 1,
        'content' => [
            'heading' => [
                'eyebrow' => 'Giới thiệu',
                'title' => 'Kim chỉ nam cho mọi hoạt động',
                'subtitle' => 'Đơn vị kiến tạo hạ tầng số, sản phẩm phần mềm và năng lực AI cho toàn bộ hệ thống Vietnam America Schools.',
            ],
            'pillars' => [
                [
                    'tag' => 'Sứ mệnh',
                    'title' => 'Đưa công nghệ vào mọi quy trình giáo dục',
                    'body' => 'Số hoá và tự động hoá các nghiệp vụ cốt lõi, để giáo viên và đội ngũ vận hành tập trung vào điều quan trọng nhất: người học.',
                    'icon' => 'layers',
                ],
                [
                    'tag' => 'Tầm nhìn',
                    'title' => 'Nền tảng dữ liệu hợp nhất cho toàn hệ thống',
                    'body' => 'Trở thành trung tâm công nghệ dẫn dắt chuyển đổi số của nhà trường, nơi mọi quyết định đều dựa trên dữ liệu thật, tức thời.',
                    'icon' => 'eye',
                ],
                [
                    'tag' => 'Giá trị',
                    'title' => 'Sản phẩm thật · Đo lường được · Tử tế',
                    'body' => 'Chúng tôi làm ra thứ dùng được mỗi ngày, đo bằng kết quả chứ không bằng lời hứa, và luôn đặt trải nghiệm con người làm trọng tâm.',
                    'icon' => 'heart',
                ],
            ],
        ],
    ],

    // ── Thành tựu / số liệu ───────────────────────────────────────────
    'impact' => [
        'label' => 'Thành tựu (số liệu)',
        'icon' => 'performance',
        'orderable' => true,
        'position' => 2,
        'content' => [
            'heading' => [
                'eyebrow' => 'Thành tựu nổi bật',
                'title' => 'Những con số biết nói',
                'subtitle' => 'Tổng hợp trực tiếp từ dữ liệu vận hành — cập nhật theo thời gian thực.',
            ],
            'live_badge' => 'LIVE DATA',
            'stats' => [
                ['key' => 'projects', 'label' => 'Dự án triển khai', 'sub' => 'Đang & hoàn thành', 'tone' => 'brand', 'suffix' => '+'],
                ['key' => 'orgPeople', 'label' => 'Nhân sự sơ đồ', 'sub' => 'Phòng Công nghệ', 'tone' => 'cyan', 'suffix' => ''],
                ['key' => 'doneTasks', 'label' => 'Task hoàn thành', 'sub' => 'Theo QLDA', 'tone' => 'emerald', 'suffix' => '+'],
                ['key' => 'departments', 'label' => 'Phòng ban', 'sub' => 'Liên phòng ban', 'tone' => 'violet', 'suffix' => ''],
                ['key' => 'orgTeams', 'label' => 'Nhóm tổ chức', 'sub' => 'Nhánh & đơn vị', 'tone' => 'amber', 'suffix' => ''],
                ['key' => 'aiAccounts', 'label' => 'Tài khoản AI', 'sub' => 'Quản lý tập trung', 'tone' => 'rose', 'suffix' => ''],
            ],
        ],
    ],

    // ── Hệ sinh thái sản phẩm (dữ liệu thật) ──────────────────────────
    'products' => [
        'label' => 'Hệ sinh thái sản phẩm',
        'icon' => 'all-projects',
        'orderable' => true,
        'position' => 3,
        'content' => [
            'heading' => [
                'eyebrow' => 'Hệ sinh thái sản phẩm',
                'title' => 'Những nền tảng đã hoàn thành',
                'subtitle' => 'Thư viện sản phẩm nghiệm thu — lướt ngang để duyệt toàn bộ; bấm thẻ để xem mô tả và ảnh (khác mục hành trình theo giai đoạn bên dưới).',
            ],
        ],
    ],

    // ── Công nghệ vận hành ────────────────────────────────────────────
    'tech' => [
        'label' => 'Công nghệ vận hành',
        'icon' => 'template',
        'orderable' => true,
        'position' => 4,
        'content' => [
            'heading' => [
                'eyebrow' => 'Công nghệ vận hành',
                'title' => 'Bộ công cụ đằng sau sản phẩm',
                'subtitle' => 'Một nền tảng hiện đại, mã nguồn mở và có thể mở rộng — nền móng cho mọi sản phẩm chúng tôi xây dựng.',
            ],
            'groups' => [
                ['title' => 'Backend', 'tag' => 'core', 'items' => ['Laravel 10', 'PHP 8', 'MySQL', 'Redis', 'Inertia']],
                ['title' => 'Frontend', 'tag' => 'ui', 'items' => ['Vue 3', 'Inertia.js', 'Tailwind CSS', 'Vite', 'Pinia']],
                ['title' => 'Dữ liệu & AI', 'tag' => 'intelligence', 'items' => ['Python', 'OpenAI / Claude', 'Pandas', 'ETL', 'BI Dashboards']],
                ['title' => 'Hạ tầng & DevOps', 'tag' => 'infra', 'items' => ['Docker', 'GitHub Actions', 'Nginx', 'CI/CD', 'Cloud']],
            ],
        ],
    ],

    // ── Sơ đồ tổ chức (dữ liệu thật) ──────────────────────────────────
    'org' => [
        'label' => 'Sơ đồ tổ chức',
        'icon' => 'org-teams',
        'orderable' => true,
        'position' => 5,
        'content' => [
            'heading' => [
                'eyebrow' => 'Đội ngũ · Sơ đồ tổ chức',
                'title' => 'Cấu trúc vận hành',
                'subtitle' => 'Quản lý trên cùng — Trưởng ban / Phó phòng theo hàng; nhánh chuyên môn có Trưởng nhóm.',
            ],
        ],
    ],

    // ── Vòng đời sản phẩm (dữ liệu thật) ──────────────────────────────
    'lifecycle' => [
        'label' => 'Vòng đời sản phẩm',
        'icon' => 'projects',
        'orderable' => true,
        'position' => 6,
        'content' => [
            'heading' => [
                'eyebrow' => 'Vòng đời sản phẩm số',
                'title' => 'Hành trình của mỗi sản phẩm',
                'subtitle' => 'Ba giai đoạn cốt lõi — lướt slide để xem từng dự án và người phụ trách; chọn giai đoạn để chuyển chương.',
            ],
            'phase_hints' => [
                'rnd' => 'Khám phá ý tưởng, thử nghiệm POC và xây dựng MVP trước khi mở rộng quy mô.',
                'deployment' => 'Triển khai, kiểm thử tích hợp và nghiệm thu với các bên liên quan.',
                'operation' => 'Vận hành ổn định, theo dõi chất lượng và cải tiến liên tục theo phản hồi thực tế.',
            ],
        ],
    ],

    // ── AI · Innovation Lab ───────────────────────────────────────────
    'ai_lab' => [
        'label' => 'AI · Innovation Lab',
        'icon' => 'cost',
        'orderable' => true,
        'position' => 7,
        'content' => [
            'heading' => [
                'eyebrow' => 'AI · Innovation Lab',
                'title' => 'Đặt AI vào trung tâm vận hành',
                'subtitle' => 'Chúng tôi không chạy theo xu hướng — chúng tôi đưa AI vào những bài toán thật, tạo ra giá trị đo lường được mỗi ngày.',
            ],
            'initiatives' => [
                [
                    'title' => 'Trợ lý AI nội bộ',
                    'body' => 'Tự động hoá soạn thảo, tổng hợp báo cáo và hỗ trợ tra cứu tri thức cho đội ngũ.',
                    'icon' => 'brain',
                ],
                [
                    'title' => 'Phân tích dữ liệu',
                    'body' => 'Khai thác dữ liệu vận hành để phát hiện điểm nghẽn và đề xuất cải tiến.',
                    'icon' => 'chart-up',
                ],
                [
                    'title' => 'Quản trị tài khoản AI',
                    'body' => 'Cấp phát, theo dõi chi phí và tối ưu việc sử dụng các công cụ AI trong toàn hệ thống.',
                    'icon' => 'check',
                ],
            ],
        ],
    ],

    // ── Văn hoá ───────────────────────────────────────────────────────
    'culture' => [
        'label' => 'Văn hoá',
        'icon' => 'members',
        'orderable' => true,
        'position' => 8,
        'content' => [
            'heading' => [
                'eyebrow' => 'Văn hoá',
                'title' => 'Cách chúng tôi làm việc cùng nhau',
                'subtitle' => 'Sáu nguyên tắc định hình tinh thần của Phòng Công Nghệ.',
            ],
            'values' => [
                ['title' => 'Ship thật', 'body' => 'Ưu tiên thứ chạy được trong tay người dùng hơn bản trình chiếu hoàn hảo.', 'icon' => 'arrow-right'],
                ['title' => 'Học liên tục', 'body' => 'Mỗi sprint là một cơ hội để giỏi hơn hôm qua.', 'icon' => 'book'],
                ['title' => 'Minh bạch', 'body' => 'Dữ liệu mở, quyết định rõ ràng, phản hồi thẳng thắn và tử tế.', 'icon' => 'lens'],
                ['title' => 'Lấy người dùng làm gốc', 'body' => 'Mọi tính năng bắt đầu từ một nhu cầu thật của đồng nghiệp.', 'icon' => 'user'],
                ['title' => 'Tự động hoá', 'body' => 'Việc lặp lại thì để máy làm, con người dành sức cho việc khó.', 'icon' => 'sun'],
                ['title' => 'Đồng đội', 'body' => 'Thành công của sản phẩm là thành công của cả nhóm.', 'icon' => 'users'],
            ],
        ],
    ],

    // ── Lộ trình ──────────────────────────────────────────────────────
    'roadmap' => [
        'label' => 'Lộ trình',
        'icon' => 'weekly',
        'orderable' => true,
        'position' => 9,
        'content' => [
            'heading' => [
                'eyebrow' => 'Lộ trình 2026 — 2027',
                'title' => 'Chặng đường phía trước',
                'subtitle' => 'Định hướng phát triển sản phẩm và năng lực công nghệ trong 18 tháng tới — đồng hành cùng hệ sinh thái VAS.',
            ],
            'guide_label' => 'Người dẫn đường',
            'companion_note' => 'Linh vật VAS đồng hành cùng từng mốc lộ trình.',
            'milestones' => [
                [
                    'period' => 'Quý III · 2026',
                    'title' => 'Hợp nhất nền tảng dữ liệu',
                    'body' => 'Đồng bộ dữ liệu nhân sự, dự án và vận hành về một nguồn duy nhất.',
                    'icon' => 'layers',
                    'state' => 'Đang triển khai',
                    'progress' => 65,
                    'live' => true,
                ],
                [
                    'period' => 'Quý IV · 2026',
                    'title' => 'Trợ lý AI nội bộ giai đoạn 1',
                    'body' => 'Tích hợp AI vào báo cáo ngày, tri thức và hỗ trợ tra cứu.',
                    'icon' => 'brain',
                    'state' => 'Sắp tới',
                    'progress' => 20,
                    'live' => false,
                ],
                [
                    'period' => 'Quý I · 2027',
                    'title' => 'Cổng dịch vụ số toàn trường',
                    'body' => 'Mở rộng nền tảng phục vụ giáo viên và các phòng ban khác.',
                    'icon' => 'arrow-right',
                    'state' => 'Kế hoạch',
                    'progress' => 0,
                    'live' => false,
                ],
                [
                    'period' => 'Quý II · 2027',
                    'title' => 'Phân tích dự đoán',
                    'body' => 'Mô hình dự báo hỗ trợ ra quyết định vận hành dựa trên dữ liệu.',
                    'icon' => 'chart-up',
                    'state' => 'Kế hoạch',
                    'progress' => 0,
                    'live' => false,
                ],
            ],
        ],
    ],

    // ── Chrome: chân trang ────────────────────────────────────────────
    'footer' => [
        'label' => 'Chân trang',
        'icon' => 'overview',
        'orderable' => false,
        'position' => 0,
        'content' => [
            'brand_title' => 'Vietnam America Schools',
            'brand_tagline' => 'Kiến tạo nền tảng số & AI cho toàn hệ thống.',
            'brand_desc' => 'Hạ tầng dữ liệu, sản phẩm phần mềm và trí tuệ nhân tạo — giải pháp thật, đo lường được.',
            'explore_links' => [
                ['label' => 'Đề xuất phần mềm', 'href' => '/congnghe/de-xuat'],
                ['label' => 'Giới thiệu', 'href' => '#gioi-thieu'],
                ['label' => 'Lộ trình 2026–2027', 'href' => '#lo-trinh'],
                ['label' => 'Hệ sinh thái sản phẩm', 'href' => '#san-pham'],
                ['label' => 'Sơ đồ tổ chức', 'href' => '#to-chuc'],
                ['label' => 'Dự án triển khai', 'href' => '#du-an'],
            ],
            'contact_links' => [
                ['label' => 'phongcongnghe@vaschools.edu.vn', 'href' => 'mailto:phongcongnghe@vaschools.edu.vn'],
                ['label' => 'Danh bạ nội bộ', 'href' => '/members'],
                ['label' => 'Liên hệ theo sơ đồ tổ chức', 'href' => '#to-chuc'],
            ],
            'copyright' => 'Bản quyền thuộc về Phòng Công Nghệ — Hệ thống Trường Quốc tế Việt Mỹ',
            'portal_label' => 'Cổng thông tin nội bộ',
        ],
    ],

];
