<?php

namespace Database\Seeders;

use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationCriterionScope;
use Illuminate\Database\Seeder;

/**
 * Seed danh mục tiêu chí "Hệ thống đánh giá nhân sự" — Phòng Hành chính Nhân sự,
 * hiệu lực 01/10/2025. Mỗi quy tắc → thang 2 mức (Không ghi nhận / Ghi nhận = điểm).
 * Idempotent — updateOrCreate theo criteria_code, an toàn chạy lại.
 */
class EvaluationCriterionHrRulesSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'HCNS';

    private const DEPARTMENT_NAME = 'Hành Chính Nhân Sự';

    public function run(): void
    {
        foreach ($this->rules() as $sortOrder => $rule) {
            $maxNote = isset($rule['max']) ? ' Tối đa '.$rule['max'].' điểm/kỳ.' : '';
            $description = rtrim((string) $rule['description'], '.');
            if ($maxNote !== '') {
                $description .= '.'.rtrim($maxNote);
            }

            EvaluationCriterion::query()->updateOrCreate(
                ['criteria_code' => $rule['code']],
                [
                    'scope' => EvaluationCriterionScope::Department,
                    'department_code' => self::DEPARTMENT_CODE,
                    'department_name' => self::DEPARTMENT_NAME,
                    'criteria_name' => "{$rule['code']} - {$rule['name']}",
                    'category' => $rule['category'],
                    'description' => $description,
                    'allow_half_score' => false,
                    'score_levels' => [
                        [
                            'code' => 'M1',
                            'label' => 'Không ghi nhận',
                            'description' => '',
                            'weight' => 0,
                        ],
                        [
                            'code' => 'M2',
                            'label' => 'Ghi nhận',
                            'description' => isset($rule['max']) ? 'Tối đa '.$rule['max'].' điểm/kỳ' : '',
                            'weight' => $rule['points'],
                        ],
                    ],
                    'sort_order' => $sortOrder + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('EvaluationCriterionHrRulesSeeder: đã seed 31 tiêu chí HCNS (thang điểm).');
    }

    /**
     * @return list<array{code: string, name: string, category: string, description: string, points: int, max?: int}>
     */
    private function rules(): array
    {
        $bonus = 'Điểm cộng — Hành vi vượt trội';
        $workResults = 'Điểm trừ — Kết quả công việc';
        $attitude = 'Điểm trừ — Thái độ & Kỷ luật';
        $collaboration = 'Điểm trừ — Hợp tác & Tương tác';
        $competence = 'Điểm trừ — Năng lực & Kỹ năng';

        return [
            // A. Điểm cộng
            ['code' => 'TCVA-A1', 'name' => 'Chủ động lập kế hoạch & đề xuất giải pháp hiệu quả', 'category' => $bonus, 'description' => 'Tự nhận diện vấn đề, xây dựng kế hoạch mà không cần chờ yêu cầu.', 'points' => 6],
            ['code' => 'TCVA-A2', 'name' => 'Chủ động nhận các công việc báo cáo chung', 'category' => $bonus, 'description' => 'Tự chủ động đăng ký nhận thêm phần công tác báo cáo, công việc chung của phòng / tiên phong đảm trách những cải tiến mới và hoàn thành.', 'points' => 3],
            ['code' => 'TCVA-A3', 'name' => 'Sáng kiến & cải tiến', 'category' => $bonus, 'description' => 'Đề xuất một ý tưởng, cải tiến nhỏ được áp dụng và mang lại hiệu quả.', 'points' => 5],
            ['code' => 'TCVA-A4', 'name' => 'Lan tỏa tích cực', 'category' => $bonus, 'description' => 'Giữ vững tinh thần lạc quan, bình tĩnh dưới áp lực cao.', 'points' => 5],
            ['code' => 'TCVA-A5', 'name' => 'Hỗ trợ đồng đội khác team', 'category' => $bonus, 'description' => 'Chủ động giúp đỡ đồng nghiệp (khác team) hoàn thành nhiệm vụ. Người cần sự hỗ trợ ghi nhận điểm trừ tương ứng.', 'points' => 3, 'max' => 9],
            ['code' => 'TCVA-A6', 'name' => 'Hỗ trợ đồng đội cùng team trước thời hạn', 'category' => $bonus, 'description' => 'Chủ động giúp đỡ đồng nghiệp (cùng team) hoàn thành nhiệm vụ trước thời hạn, thông qua đăng ký với cấp quản lý. Người cần sự hỗ trợ sẽ ghi nhận điểm trừ tương ứng.', 'points' => 2, 'max' => 10],
            ['code' => 'TCVA-A7', 'name' => 'Tham gia hoạt động, chương trình (CLB chạy bộ / giao lưu công ty)', 'category' => $bonus, 'description' => 'Câu lạc bộ chạy bộ: tham gia đúng thời gian và hoàn thành tối thiểu 5km, ghi nhận qua Strava. Hoặc tham gia các buổi giao lưu, kết nối, tiệc do công ty tổ chức chính thức và được truyền thông.', 'points' => 2],
            ['code' => 'TCVA-A8', 'name' => 'Tham gia thi đấu hoạt động của trường', 'category' => $bonus, 'description' => 'Tham gia thi đấu trong các chương trình hoạt động của trường.', 'points' => 2],
            ['code' => 'TCVA-A9', 'name' => 'Đạt thành tích Hội thi / tổ chức sự kiện công ty', 'category' => $bonus, 'description' => 'Đạt thành tích tham gia các Hội thi/Hoạt động của Công ty phát động từ Giải khuyến khích trở lên (cộng thêm so với chỉ tham gia). Hoặc tham gia tổ chức, quản lý các sự kiện tiệc, chương trình công ty ngoài giờ làm việc — nếu vừa tổ chức vừa tham gia chỉ tính mức điểm cao hơn.', 'points' => 3],
            ['code' => 'TCVA-A10', 'name' => 'Phát triển cá nhân — tự túc học khóa đào tạo ngoài', 'category' => $bonus, 'description' => 'Chủ động tự túc tham gia khóa đào tạo bên ngoài để học hỏi và áp dụng kỹ năng mới, ưu tiên khóa học liên quan công việc đang phụ trách. Gửi chứng chỉ/chứng nhận về công ty.', 'points' => 5, 'max' => 15],
            ['code' => 'TCVA-A11', 'name' => 'Duy trì thành tích cá nhân 3 tháng liên tục ≥100 điểm', 'category' => $bonus, 'description' => '3 tháng liên tục duy trì tổng điểm số trong tháng từ 100 điểm trở lên; điểm cộng được cộng vào tháng tiếp theo.', 'points' => 8],
            ['code' => 'TCVA-A12', 'name' => 'Tổ chức chuyên đề đào tạo, chia sẻ đội nhóm', 'category' => $bonus, 'description' => 'Chủ động đề xuất tổ chức 1 chuyên đề và đứng ra chia sẻ kiến thức mới/sáng kiến mới/ứng dụng công nghệ mới phục vụ cho công việc của phòng.', 'points' => 6],
            ['code' => 'TCVA-A13', 'name' => 'Tham gia đào tạo Thứ 7 của phòng (≥3 buổi/tháng)', 'category' => $bonus, 'description' => 'Tham gia buổi đào tạo Thứ 7 của phòng ban từ 3 buổi trở lên (tính lại từ đầu tháng).', 'points' => 2],

            // B. Kết quả công việc
            ['code' => 'TCVA-B1', 'name' => 'Trễ deadline nhẹ', 'category' => $workResults, 'description' => 'Trễ ≤ 1 ngày làm việc, không ảnh hưởng người khác.', 'points' => -2],
            ['code' => 'TCVA-B2', 'name' => 'Trễ deadline nặng', 'category' => $workResults, 'description' => 'Trễ > 1 ngày hoặc gây ảnh hưởng đến công việc của người khác.', 'points' => -4],
            ['code' => 'TCVA-B3', 'name' => 'Sai sót nhỏ trong công việc', 'category' => $workResults, 'description' => 'Sai sót nhỏ, có thể tự sửa chữa, không gây hậu quả.', 'points' => -2],
            ['code' => 'TCVA-B4', 'name' => 'Sai sót nghiêm trọng trong công việc', 'category' => $workResults, 'description' => 'Sai sót gây ảnh hưởng đến số liệu, chi phí, uy tín. VD: đóng dấu sai phải trình ký lại, nhầm dữ liệu chấm công dẫn đến chi lương sai, truyền thông rộng rãi nhưng thiếu dữ liệu thông tin.', 'points' => -10],
            ['code' => 'TCVA-B5', 'name' => 'Không đạt KPI — hoàn thành 85%–<100%', 'category' => $workResults, 'description' => 'Hoàn thành từ 85% đến dưới 100% khối lượng công việc.', 'points' => -3],
            ['code' => 'TCVA-B6', 'name' => 'Không đạt KPI — hoàn thành <85%', 'category' => $workResults, 'description' => 'Hoàn thành dưới 85% khối lượng công việc.', 'points' => -5],

            // C. Thái độ & Kỷ luật
            ['code' => 'TCVA-C1', 'name' => 'Vi phạm tuân thủ thời gian', 'category' => $attitude, 'description' => 'Đi trễ/về sớm không có lý do chính đáng; thiếu dữ liệu ghi nhận chấm công. Áp dụng chấm công vào/ra qua camera Lễ tân văn phòng theo khung giờ làm việc, thực hiện từ 13/10/2025.', 'points' => -2],
            ['code' => 'TCVA-C2', 'name' => 'Không tuân thủ quy trình báo cáo', 'category' => $attitude, 'description' => 'Không tuân thủ quy trình báo cáo (quên gửi, sai biểu mẫu...).', 'points' => -3],
            ['code' => 'TCVA-C3', 'name' => 'Thái độ làm việc bị động', 'category' => $attitude, 'description' => 'Bị động, phải thúc giục nhiều lần mới làm việc; chưa chủ động báo cáo công việc, khi hỏi mới báo cáo; hay bàn lùi hoặc tìm cách đẩy công việc cho người khác.', 'points' => -5],
            ['code' => 'TCVA-C4', 'name' => 'Đổ lỗi, không nhận trách nhiệm', 'category' => $attitude, 'description' => 'Có thái độ đổ lỗi, không nhận trách nhiệm về sai sót của mình.', 'points' => -7],
            ['code' => 'TCVA-C5', 'name' => 'Vắng mặt họp đã truyền thông trước', 'category' => $attitude, 'description' => 'Vắng mặt các buổi họp được truyền thông trước.', 'points' => -7],
            ['code' => 'TCVA-C6', 'name' => 'Không trung thực / tự quyết vượt thẩm quyền', 'category' => $attitude, 'description' => 'Không trung thực hoặc tự quyết định các vấn đề về chi phí mà không thuộc thẩm quyền được quyết định, hoặc giấu giếm sai phạm.', 'points' => -10],

            // D. Hợp tác & Tương tác
            ['code' => 'TCVA-D1', 'name' => 'Từ chối hỗ trợ đồng nghiệp', 'category' => $collaboration, 'description' => 'Từ chối hoặc không chủ động kết nối hợp tác cùng đồng nghiệp.', 'points' => -4],
            ['code' => 'TCVA-D2', 'name' => 'Phối hợp liên phòng ban chậm trễ', 'category' => $collaboration, 'description' => 'Phản hồi chậm trễ, gây ảnh hưởng đến tiến độ chung.', 'points' => -5],
            ['code' => 'TCVA-D3', 'name' => 'Phản ứng tiêu cực khi nhận góp ý', 'category' => $collaboration, 'description' => 'Phản ứng tiêu cực khi nhận góp ý (phòng thủ, cáu gắt, lớn tiếng,...).', 'points' => -5],
            ['code' => 'TCVA-D4', 'name' => 'Cảm xúc cá nhân ảnh hưởng không khí làm việc', 'category' => $collaboration, 'description' => 'Để cảm xúc cá nhân ảnh hưởng tiêu cực đến không khí làm việc (phản ánh của các phòng ban, đồng nghiệp, cảm xúc thay đổi thất thường,...).', 'points' => -7],

            // E. Năng lực & Kỹ năng
            ['code' => 'TCVA-E1', 'name' => 'Lặp lại sai lầm', 'category' => $competence, 'description' => 'Mắc lại lỗi đã được hướng dẫn, góp ý trước đó.', 'points' => -5],
            ['code' => 'TCVA-E2', 'name' => 'Lúng túng khi giải quyết vấn đề', 'category' => $competence, 'description' => 'Lúng túng, không thể đề xuất phương án xử lý cho vấn đề cơ bản.', 'points' => -6],
        ];
    }
}
