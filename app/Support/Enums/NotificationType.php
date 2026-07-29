<?php

namespace App\Support\Enums;

enum NotificationType: string
{
    // Task
    case TaskAssigned = 'task_assigned';
    case TaskUpdated = 'task_updated';
    case TaskDeadlineChanged = 'task_deadline_changed';
    case TaskOverdue = 'task_overdue';
    case TaskDueSoon = 'task_due_soon';
    case TaskBlocked = 'task_blocked';
    case TaskCompleted = 'task_completed';
    case TaskStatusChanged = 'task_status_changed';
    case TaskCreated = 'task_created';

    // Sprint
    case SprintCreated = 'sprint_created';
    case SprintStarted = 'sprint_started';
    case SprintUpdated = 'sprint_updated';
    case SprintEnded = 'sprint_ended';
    case SprintDeleted = 'sprint_deleted';
    case SprintBehind = 'sprint_behind';
    case SprintOverCapacity = 'sprint_over_capacity';

    // Project
    case ProjectCreated = 'project_created';
    case ProjectStatusChanged = 'project_status_changed';
    case ProjectPmChanged = 'project_pm_changed';
    case ProjectDeadlineChanged = 'project_deadline_changed';
    case ProjectOverdue = 'project_overdue';
    case ProjectMemberAdded = 'project_member_added';
    case ProjectArchived = 'project_archived';

    // Blocker
    case BlockerCreated = 'blocker_created';
    case BlockerUpdated = 'blocker_updated';

    // Feedback
    case FeedbackCreated = 'feedback_created';
    case FeedbackUpdated = 'feedback_updated';
    case CommentFeedbackThread = 'comment_feedback_thread';

    // Weekly Report
    case WeeklyReportGenerated = 'weekly_report_generated';
    case WeeklyReportSubmitted = 'weekly_report_submitted';
    case WeeklyReportApproved = 'weekly_report_approved';
    case WeeklyReportRejected = 'weekly_report_rejected';
    case WeeklyReportRegenerationAvailable = 'weekly_report_regeneration_available';

    // Document
    case DocumentUploaded = 'document_uploaded';
    case DocumentBrdUpdated = 'document_brd_updated';
    case DocumentFrsUpdated = 'document_frs_updated';
    case DocumentSrsUpdated = 'document_srs_updated';
    case DocumentUiUpdated = 'document_ui_updated';
    case DocumentClientUploaded = 'document_client_uploaded';
    case DocumentDeleted = 'document_deleted';

    // Comment
    case CommentMention = 'comment_mention';
    case CommentReply = 'comment_reply';
    case CommentTaskThread = 'comment_task_thread';
    case CommentSprintThread = 'comment_sprint_thread';
    case CommentBlockerThread = 'comment_blocker_thread';

    // System
    case SystemImport = 'system_import';
    case SystemExport = 'system_export';
    case SystemBackup = 'system_backup';
    case SystemRestore = 'system_restore';
    case SystemSync = 'system_sync';
    case SystemError = 'system_error';
    case SystemAiAccountExpiry = 'system_ai_account_expiry';
    case SystemAiAccountRenewalUnpaid = 'system_ai_account_renewal_unpaid';
    case SystemContractExpiry = 'system_contract_expiry';
    case SystemContractExpired = 'system_contract_expired';
    case SystemContractCreated = 'system_contract_created';
    case SystemContractUpdated = 'system_contract_updated';
    case SystemContractRenewed = 'system_contract_renewed';
    case SystemContractVendorReview = 'system_contract_vendor_review';

    // AI Proposals
    case AiProposalApproved = 'ai_proposal_approved';
    case AiProposalRejected = 'ai_proposal_rejected';

    // Daily report
    case DailyReportSubmitted = 'daily_report_submitted';
    case DailyReportRecalled = 'daily_report_recalled';
    case DailyReportScored = 'daily_report_scored';
    case DailyReportRejected = 'daily_report_rejected';

    // Admin feed
    case AdminUserAction = 'admin_user_action';
    case AdminApiError = 'admin_api_error';
    case AdminValidationError = 'admin_validation_error';
    case AdminJobFailed = 'admin_job_failed';
    case AdminQueueFailed = 'admin_queue_failed';
    case AdminUploadFailed = 'admin_upload_failed';
    case AdminImportFailed = 'admin_import_failed';
    case AdminPermissionError = 'admin_permission_error';
    case AdminDataAnomaly = 'admin_data_anomaly';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::TaskAssigned, self::TaskUpdated, self::TaskDeadlineChanged,
            self::TaskOverdue, self::TaskDueSoon, self::TaskBlocked,
            self::TaskCompleted, self::TaskStatusChanged, self::TaskCreated => NotificationCategory::Task,

            self::SprintCreated, self::SprintStarted, self::SprintUpdated,
            self::SprintEnded, self::SprintDeleted,
            self::SprintBehind, self::SprintOverCapacity => NotificationCategory::Sprint,

            self::ProjectCreated, self::ProjectStatusChanged, self::ProjectPmChanged,
            self::ProjectDeadlineChanged, self::ProjectOverdue,
            self::ProjectMemberAdded, self::ProjectArchived => NotificationCategory::Project,

            self::BlockerCreated, self::BlockerUpdated => NotificationCategory::Project,

            self::FeedbackCreated, self::FeedbackUpdated => NotificationCategory::Project,

            self::WeeklyReportGenerated, self::WeeklyReportSubmitted,
            self::WeeklyReportApproved, self::WeeklyReportRejected,
            self::WeeklyReportRegenerationAvailable => NotificationCategory::Project,

            self::DocumentUploaded, self::DocumentBrdUpdated, self::DocumentFrsUpdated,
            self::DocumentSrsUpdated, self::DocumentUiUpdated, self::DocumentClientUploaded,
            self::DocumentDeleted => NotificationCategory::Document,

            self::CommentMention, self::CommentReply, self::CommentTaskThread,
            self::CommentSprintThread, self::CommentBlockerThread,
            self::CommentFeedbackThread => NotificationCategory::Comment,

            self::SystemImport, self::SystemExport, self::SystemBackup,
            self::SystemRestore, self::SystemSync, self::SystemError,
            self::SystemAiAccountExpiry, self::SystemAiAccountRenewalUnpaid,
            self::SystemContractExpiry, self::SystemContractExpired,
            self::SystemContractCreated, self::SystemContractUpdated,
            self::SystemContractRenewed, self::SystemContractVendorReview,
            self::AiProposalApproved, self::AiProposalRejected => NotificationCategory::System,

            self::DailyReportSubmitted, self::DailyReportRecalled,
            self::DailyReportScored,
            self::DailyReportRejected => NotificationCategory::DailyReport,

            default => NotificationCategory::Admin,
        };
    }

    public function defaultPriority(): NotificationPriority
    {
        return match ($this) {
            self::TaskOverdue, self::ProjectOverdue, self::SprintBehind,
            self::SystemError, self::AdminApiError, self::AdminJobFailed,
            self::AdminQueueFailed, self::AdminImportFailed,
            self::SystemContractExpired => NotificationPriority::Critical,

            self::TaskDueSoon, self::TaskBlocked, self::TaskAssigned,
            self::SystemAiAccountExpiry, self::SystemAiAccountRenewalUnpaid,
            self::SystemContractExpiry,
            self::SprintOverCapacity, self::AdminDataAnomaly,
            self::AdminUploadFailed, self::AdminPermissionError,
            self::DailyReportRejected,
            self::AiProposalApproved, self::AiProposalRejected => NotificationPriority::High,

            self::CommentMention, self::TaskDeadlineChanged,
            self::ProjectDeadlineChanged, self::ProjectMemberAdded,
            self::DailyReportSubmitted,
            self::WeeklyReportSubmitted, self::WeeklyReportRejected => NotificationPriority::High,

            default => NotificationPriority::Medium,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Công việc được giao',
            self::TaskUpdated => 'Công việc được cập nhật',
            self::TaskDeadlineChanged => 'Thay đổi hạn công việc',
            self::TaskOverdue => 'Công việc quá hạn',
            self::TaskDueSoon => 'Công việc sắp đến hạn',
            self::TaskBlocked => 'Công việc bị chặn',
            self::TaskCompleted => 'Công việc hoàn thành',
            self::TaskStatusChanged => 'Thay đổi trạng thái công việc',
            self::TaskCreated => 'Công việc mới',
            self::SprintCreated => 'Sprint mới',
            self::SprintStarted => 'Sprint bắt đầu',
            self::SprintEnded => 'Sprint kết thúc',
            self::SprintBehind => 'Sprint trễ tiến độ',
            self::SprintOverCapacity => 'Sprint vượt capacity',
            self::ProjectCreated => 'Dự án mới',
            self::ProjectStatusChanged => 'Thay đổi trạng thái dự án',
            self::ProjectPmChanged => 'Thay đổi PM dự án',
            self::ProjectDeadlineChanged => 'Thay đổi deadline dự án',
            self::ProjectOverdue => 'Dự án quá hạn',
            self::DocumentUploaded => 'Tài liệu mới',
            self::DocumentBrdUpdated => 'Cập nhật BRD',
            self::DocumentFrsUpdated => 'Cập nhật FRS',
            self::DocumentSrsUpdated => 'Cập nhật SRS',
            self::DocumentUiUpdated => 'Cập nhật UI Design',
            self::DocumentClientUploaded => 'Tài liệu khách hàng',
            self::DocumentDeleted => 'Tài liệu bị xoá',
            self::CommentMention => 'Được nhắc đến',
            self::CommentReply => 'Phản hồi mới',
            self::CommentTaskThread => 'Trao đổi Task',
            self::CommentSprintThread => 'Trao đổi Sprint',
            self::CommentBlockerThread => 'Trao đổi vướng mắc',
            self::SystemImport => 'Nhập dữ liệu',
            self::SystemExport => 'Xuất dữ liệu',
            self::SystemBackup => 'Backup',
            self::SystemRestore => 'Restore',
            self::SystemSync => 'Đồng bộ dữ liệu',
            self::SystemError => 'Lỗi hệ thống',
            self::SystemAiAccountExpiry => 'Tài khoản AI sắp hết hạn',
            self::SystemAiAccountRenewalUnpaid => 'Chưa thanh toán gia hạn AI',
            self::SystemContractExpiry => 'Hợp đồng sắp hết hạn',
            self::SystemContractExpired => 'Hợp đồng đã hết hạn',
            self::SystemContractCreated => 'Tạo hợp đồng',
            self::SystemContractUpdated => 'Cập nhật hợp đồng',
            self::SystemContractRenewed => 'Gia hạn hợp đồng',
            self::SystemContractVendorReview => 'Đánh giá nhà cung cấp',
            self::AiProposalApproved => 'Phiếu đề xuất AI được duyệt',
            self::AiProposalRejected => 'Phiếu đề xuất AI bị từ chối',
            self::DailyReportSubmitted => 'Báo cáo ngày chờ duyệt',
            self::DailyReportRecalled => 'Báo cáo ngày được rút lại',
            self::DailyReportScored => 'Báo cáo ngày đã được chấm',
            self::DailyReportRejected => 'Báo cáo ngày bị trả lại',
            self::SprintUpdated => 'Sprint được cập nhật',
            self::SprintDeleted => 'Sprint bị xoá',
            self::ProjectMemberAdded => 'Được thêm vào dự án',
            self::ProjectArchived => 'Dự án đã lưu trữ',
            self::BlockerCreated => 'Vướng mắc mới',
            self::BlockerUpdated => 'Cập nhật vướng mắc',
            self::FeedbackCreated => 'Phản hồi mới',
            self::FeedbackUpdated => 'Cập nhật phản hồi',
            self::CommentFeedbackThread => 'Trao đổi phản hồi',
            self::WeeklyReportGenerated => 'Báo cáo tuần đã tạo',
            self::WeeklyReportSubmitted => 'Báo cáo tuần chờ duyệt',
            self::WeeklyReportApproved => 'Báo cáo tuần đã duyệt',
            self::WeeklyReportRejected => 'Báo cáo tuần bị trả lại',
            self::WeeklyReportRegenerationAvailable => 'Báo cáo tuần cần cập nhật',
            self::AdminUserAction => 'Hoạt động người dùng',
            self::AdminApiError => 'API Error',
            self::AdminValidationError => 'Validation Error',
            self::AdminJobFailed => 'Job Failed',
            self::AdminQueueFailed => 'Queue Failed',
            self::AdminUploadFailed => 'Upload Failed',
            self::AdminImportFailed => 'Import Failed',
            self::AdminPermissionError => 'Permission Error',
            self::AdminDataAnomaly => 'Dữ liệu bất thường',
        };
    }
}
