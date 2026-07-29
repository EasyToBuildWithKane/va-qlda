<?php

namespace Database\Seeders;

use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateCriterion;
use App\Support\Enums\EvaluationTemplateType;
use Illuminate\Database\Seeder;

class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $hcns = EvaluationTemplate::query()->updateOrCreate(
            ['name' => 'Mẫu Điểm Cộng/Trừ HCNS', 'template_type' => EvaluationTemplateType::PointSystem],
            [
                'description' => 'Bộ quy tắc điểm cộng/trừ từ 100 điểm khởi đầu — áp dụng phòng Hành Chính Nhân Sự.',
                'is_system' => true,
            ],
        );

        $this->seedCriteria($hcns->id, $this->hcnsCriteria());

        $cntt = EvaluationTemplate::query()->updateOrCreate(
            ['name' => 'Phiếu tiêu chí CNTT', 'template_type' => EvaluationTemplateType::Scorecard],
            [
                'description' => 'Phiếu đánh giá sự hài lòng / tiêu chí có trọng số — mẫu phòng Công Nghệ Thông Tin.',
                'is_system' => true,
            ],
        );

        $this->seedCriteria($cntt->id, $this->cnttCriteria());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedCriteria(int $templateId, array $rows): void
    {
        EvaluationTemplateCriterion::query()->where('template_id', $templateId)->delete();

        foreach ($rows as $i => $row) {
            EvaluationTemplateCriterion::query()->create([
                ...$row,
                'template_id' => $templateId,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hcnsCriteria(): array
    {
        return [
            [
                'criteria_code' => 'A1',
                'criteria_name' => 'Chủ động Lập kế hoạch & Đề xuất Giải pháp hiệu quả',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Tự nhận diện vấn đề, xây dựng kế hoạch mà không cần chờ yêu cầu.',
                'point_value' => 6,
                'max_points' => null,
                'max_frequency' => null,
            ],
            [
                'criteria_code' => 'A2',
                'criteria_name' => 'Chủ động nhận các công việc báo cáo chung',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Tự chủ động đăng ký nhận thêm phần công tác báo cáo, công việc chung của phòng/Tiên phong đảm trách những cải tiến mới và hoàn thành.',
                'point_value' => 3,
            ],
            [
                'criteria_code' => 'A3',
                'criteria_name' => 'Sáng kiến & Cải tiến',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Đề xuất một ý tưởng, cải tiến nhỏ được áp dụng và mang lại hiệu quả.',
                'point_value' => 5,
            ],
            [
                'criteria_code' => 'A4',
                'criteria_name' => 'Lan tỏa tích cực',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Giữ vững tinh thần lạc quan, bình tĩnh dưới áp lực cao.',
                'point_value' => 5,
            ],
            [
                'criteria_code' => 'A5',
                'criteria_name' => 'Hỗ trợ Đồng đội (khác team)',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Chủ động giúp đỡ đồng nghiệp (khác team) hoàn thành nhiệm vụ. Người cần sự hỗ trợ ghi nhận điểm trừ tương ứng.',
                'point_value' => 3,
                'max_points' => 9,
            ],
            [
                'criteria_code' => 'A6',
                'criteria_name' => 'Hỗ trợ Đồng đội (cùng team)',
                'category' => 'Điểm Cộng — Hành vi Vượt trội',
                'description' => 'Chủ động giúp đỡ đồng nghiệp (cùng team) hoàn thành nhiệm vụ trước thời hạn, thông qua đăng ký với cấp quản lý.',
                'point_value' => 2,
                'max_points' => 10,
            ],
            [
                'criteria_code' => 'A7',
                'criteria_name' => 'Tham gia hoạt động, chương trình',
                'category' => 'Điểm Cộng — Tham gia hoạt động',
                'description' => 'Câu lạc bộ chạy bộ (Strava ≥5km) hoặc tham gia giao lưu/tiệc do công ty tổ chức chính thức.',
                'point_value' => 2,
            ],
            [
                'criteria_code' => 'A8',
                'criteria_name' => 'Tham gia thi đấu chương trình hoạt động của trường',
                'category' => 'Điểm Cộng — Tham gia hoạt động',
                'description' => 'Tham gia thi đấu trong các chương trình hoạt động của trường.',
                'point_value' => 2,
            ],
            [
                'criteria_code' => 'A9',
                'criteria_name' => 'Đạt thành tích Hội thi / Tổ chức sự kiện',
                'category' => 'Điểm Cộng — Tham gia hoạt động',
                'description' => 'Đạt thành tích từ giải khuyến khích trở lên, hoặc tham gia tổ chức sự kiện ngoài giờ. Nếu vừa tham gia vừa tổ chức chỉ tính mức cao hơn.',
                'point_value' => 3,
            ],
            [
                'criteria_code' => 'A10',
                'criteria_name' => 'Phát triển cá nhân',
                'category' => 'Điểm Cộng — Phát triển',
                'description' => 'Chủ động tự túc tham gia khóa đào tạo bên ngoài, ưu tiên liên quan công việc; gửi chứng chỉ về công ty.',
                'point_value' => 5,
                'max_points' => 15,
            ],
            [
                'criteria_code' => 'A11',
                'criteria_name' => 'Duy trì thành tích cá nhân',
                'category' => 'Điểm Cộng — Phát triển',
                'description' => '3 tháng liên tục duy trì tổng điểm số trong tháng từ 100 trở lên; điểm cộng vào tháng tiếp theo.',
                'point_value' => 8,
            ],
            [
                'criteria_code' => 'A12',
                'criteria_name' => 'Tổ chức chuyên đề đào tạo, chia sẻ đội nhóm',
                'category' => 'Điểm Cộng — Phát triển',
                'description' => 'Chủ động đề xuất tổ chức 1 chuyên đề và đứng ra chia sẻ kiến thức/sáng kiến/ứng dụng công nghệ mới phục vụ công việc phòng.',
                'point_value' => 6,
            ],
            [
                'criteria_code' => 'A13',
                'criteria_name' => 'Tham gia đào tạo của phòng',
                'category' => 'Điểm Cộng — Phát triển',
                'description' => 'Tham gia buổi đào tạo Thứ 7 của phòng ban từ 3 buổi trở lên (tính lại từ đầu tháng).',
                'point_value' => 2,
            ],
            [
                'criteria_code' => 'B1',
                'criteria_name' => 'Trễ Deadline (Nhẹ)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Trễ ≤ 1 ngày làm việc, không ảnh hưởng người khác.',
                'point_value' => -2,
            ],
            [
                'criteria_code' => 'B2',
                'criteria_name' => 'Trễ Deadline (Nặng)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Trễ > 1 ngày hoặc gây ảnh hưởng đến công việc của người khác.',
                'point_value' => -4,
            ],
            [
                'criteria_code' => 'B3',
                'criteria_name' => 'Sai sót trong công việc (Nhỏ)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Sai sót nhỏ, có thể tự sửa chữa, không gây hậu quả.',
                'point_value' => -2,
            ],
            [
                'criteria_code' => 'B4',
                'criteria_name' => 'Sai sót trong công việc (Nghiêm trọng)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Sai sót gây ảnh hưởng đến số liệu, chi phí, uy tín.',
                'point_value' => -10,
            ],
            [
                'criteria_code' => 'B5',
                'criteria_name' => 'Không đạt KPI (85%–dưới 100%)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Hoàn thành từ 85% đến dưới 100% khối lượng công việc.',
                'point_value' => -3,
            ],
            [
                'criteria_code' => 'B6',
                'criteria_name' => 'Không đạt KPI (dưới 85%)',
                'category' => 'Điểm Trừ — Kết quả Công việc',
                'description' => 'Hoàn thành dưới 85% khối lượng công việc.',
                'point_value' => -5,
            ],
            [
                'criteria_code' => 'C1',
                'criteria_name' => 'Tuân thủ thời gian',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Đi trễ/về sớm không lý do chính đáng; thiếu dữ liệu ghi nhận chấm công.',
                'point_value' => -2,
            ],
            [
                'criteria_code' => 'C2',
                'criteria_name' => 'Báo cáo & Quy trình',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Không tuân thủ quy trình báo cáo (quên gửi, sai biểu mẫu…).',
                'point_value' => -3,
            ],
            [
                'criteria_code' => 'C3',
                'criteria_name' => 'Thái độ làm việc (bị động)',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Bị động, phải thúc giục nhiều lần; chưa chủ động báo cáo; hay bàn lùi hoặc đẩy việc.',
                'point_value' => -5,
            ],
            [
                'criteria_code' => 'C4',
                'criteria_name' => 'Thái độ đổ lỗi',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Có thái độ đổ lỗi, không nhận trách nhiệm về sai sót của mình.',
                'point_value' => -7,
            ],
            [
                'criteria_code' => 'C5',
                'criteria_name' => 'Vắng mặt buổi họp',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Vắng mặt các buổi họp được truyền thông trước.',
                'point_value' => -7,
            ],
            [
                'criteria_code' => 'C6',
                'criteria_name' => 'Không trung thực / vượt thẩm quyền chi phí',
                'category' => 'Điểm Trừ — Thái độ & Kỷ luật',
                'description' => 'Không trung thực hoặc tự quyết định vấn đề chi phí ngoài thẩm quyền, hoặc giấu giếm sai phạm.',
                'point_value' => -10,
            ],
            [
                'criteria_code' => 'D1',
                'criteria_name' => 'Hỗ trợ đồng nghiệp',
                'category' => 'Điểm Trừ — Hợp tác & Tương tác',
                'description' => 'Từ chối hoặc không chủ động kết nối hợp tác cùng đồng nghiệp.',
                'point_value' => -4,
            ],
            [
                'criteria_code' => 'D2',
                'criteria_name' => 'Phối hợp liên phòng ban',
                'category' => 'Điểm Trừ — Hợp tác & Tương tác',
                'description' => 'Phản hồi chậm trễ, gây ảnh hưởng đến tiến độ chung.',
                'point_value' => -5,
            ],
            [
                'criteria_code' => 'D3',
                'criteria_name' => 'Quản trị cảm xúc (phản ứng tiêu cực)',
                'category' => 'Điểm Trừ — Hợp tác & Tương tác',
                'description' => 'Phản ứng tiêu cực khi nhận góp ý (phòng thủ, cáu gắt, lớn tiếng…).',
                'point_value' => -5,
            ],
            [
                'criteria_code' => 'D4',
                'criteria_name' => 'Quản trị cảm xúc (ảnh hưởng không khí)',
                'category' => 'Điểm Trừ — Hợp tác & Tương tác',
                'description' => 'Để cảm xúc cá nhân ảnh hưởng tiêu cực đến không khí làm việc.',
                'point_value' => -7,
            ],
            [
                'criteria_code' => 'E1',
                'criteria_name' => 'Lặp lại sai lầm',
                'category' => 'Điểm Trừ — Năng lực & Kỹ năng',
                'description' => 'Mắc lại lỗi đã được hướng dẫn, góp ý trước đó.',
                'point_value' => -5,
            ],
            [
                'criteria_code' => 'E2',
                'criteria_name' => 'Giải quyết vấn đề',
                'category' => 'Điểm Trừ — Năng lực & Kỹ năng',
                'description' => 'Lúng túng, không thể đề xuất phương án xử lý cho vấn đề cơ bản.',
                'point_value' => -6,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cnttCriteria(): array
    {
        $attitude = [
            ['TCMS1', 'Hỗ trợ xử lý lỗi máy tính người dùng'],
            ['TCMS2', 'Xử lý sự cố máy in, máy photocopy'],
            ['TCMS3', 'Đổ mực máy in đến các phòng ban'],
            ['TCMS4', 'Xử lý sự cố mạng internet và wifi, kênh truyền VPN, cáp quang'],
            ['94', 'Xử lý sự cố Camera và hỗ trợ người dùng về camera'],
            ['95', 'Xử lý thiết bị CNTT khác (máy chấm công, điện thoại bàn, máy chiếu…)'],
        ];
        $competence = [
            ['96', 'Hỗ trợ xử lý lỗi máy tính người dùng'],
            ['97', 'Xử lý sự cố máy in, máy photocopy'],
            ['98', 'Đổ mực máy in đến các phòng ban'],
            ['99', 'Xử lý sự cố mạng internet và wifi, kênh truyền VPN, cáp quang'],
        ];

        $rows = [];
        foreach ($attitude as [$code, $name]) {
            $rows[] = [
                'criteria_code' => $code,
                'criteria_name' => $name,
                'category' => 'Thái độ',
                'description' => null,
                'weight' => 1,
                'required_score' => 5,
                'importance' => 'Khá quan trọng',
                'point_value' => null,
            ];
        }
        foreach ($competence as [$code, $name]) {
            $rows[] = [
                'criteria_code' => $code,
                'criteria_name' => $name,
                'category' => 'Năng lực chuyên môn',
                'description' => null,
                'weight' => 1,
                'required_score' => 5,
                'importance' => 'Khá quan trọng',
                'point_value' => null,
            ];
        }

        return $rows;
    }
}
