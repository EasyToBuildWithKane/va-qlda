/**
 * Standard billable hours per month — mirrors config/business.php project.monthly_hours
 */
export const PROJECT_MONTHLY_HOURS = 176;

/**
 * Maximum rows allowed per bulk import operation (mirrors server-side limit).
 */
export const MAX_IMPORT_ROWS = 200;

/**
 * Brand color tokens (mirrors Tailwind config)
 */
export const BRAND_COLOR = '#9A0036';
export const BRAND_SOFT_COLOR = '#FDF2F6';

/**
 * Excel import template constants
 */
export const EXCEL_TEMPLATE = {
    BRAND: '9A0036',
    BRAND_SOFT: 'FDF2F6',
    HEADER_ROW: 5,
    SAMPLE_ROWS: [6, 7],
    DATA_START_ROW: 8,
};
