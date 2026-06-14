const SAMPLE_TASKS_TABLE = '<table style="width:100%;border:1px solid #e2e8f0;border-collapse:collapse;">'
    + '<thead><tr style="background:#FDF2F6;">'
    + '<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Công việc</th>'
    + '<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Trạng thái</th>'
    + '<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Hạn</th>'
    + '</tr></thead><tbody>'
    + '<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Thiết kế màn Sprint</td>'
    + '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Đang làm</td>'
    + '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">20/06/2026</td></tr>'
    + '<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Review API task</td>'
    + '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Cần làm</td>'
    + '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">21/06/2026</td></tr>'
    + '</tbody></table>';

export const EMAIL_TEMPLATE_SAMPLE_VARS = {
    assignee_name: 'Nguyễn Văn A',
    task_name: 'Thiết kế màn hình Sprint',
    project_name: 'Dự án QLDA mẫu',
    sprint_name: 'Sprint 1',
    due_date: '20/06/2026',
    task_url: '#',
    date: '14/06/2026',
    task_count: '2',
    tasks_table: SAMPLE_TASKS_TABLE,
};

export function replaceTemplatePlaceholders(text, vars = EMAIL_TEMPLATE_SAMPLE_VARS) {
    let out = text ?? '';
    Object.entries(vars).forEach(([key, val]) => {
        out = out.split(`{{${key}}}`).join(String(val ?? ''));
    });
    return out.replace(/\{\{[a-z_]+\}\}/g, '');
}

/**
 * Mirrors {@see EmailBrandLayout} for admin live preview.
 */
export function wrapEmailBrandPreview(innerHtml, { appName = 'VAschools QLDA', preheader = '' } = {}) {
    const year = new Date().getFullYear();
    const pre = preheader
        ? `<div style="display:none;max-height:0;overflow:hidden;">${preheader.replace(/<[^>]+>/g, '')}</div>`
        : '';

    return `<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"></head>`
        + '<body style="margin:0;padding:0;background:#f1f5f9;font-family:system-ui,sans-serif;">'
        + pre
        + '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:16px 8px;">'
        + '<tr><td align="center"><table role="presentation" width="100%" style="max-width:560px;">'
        + '<tr><td style="background:#9A0036;border-radius:12px 12px 0 0;padding:16px 20px;">'
        + `<p style="margin:0;font-size:17px;font-weight:700;color:#fff;">${appName}</p>`
        + '<p style="margin:4px 0 0;font-size:11px;color:rgba(255,255,255,0.85);">Thông báo công việc · QLDA</p>'
        + '</td></tr>'
        + '<tr><td style="background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:20px;font-size:14px;line-height:1.55;color:#1e293b;">'
        + innerHtml
        + '</td></tr>'
        + `<tr><td style="padding:12px 4px 0;text-align:center;font-size:10px;color:#94a3b8;">Email tự động · © ${year} VAschools</td></tr>`
        + '</table></td></tr></table></body></html>';
}

export function buildTemplatePreviewDocument(subject, bodyHtml, appName) {
    const inner = replaceTemplatePlaceholders(bodyHtml);
    const preheader = replaceTemplatePlaceholders(subject);

    return {
        subject: preheader,
        html: wrapEmailBrandPreview(inner, { appName, preheader }),
    };
}
