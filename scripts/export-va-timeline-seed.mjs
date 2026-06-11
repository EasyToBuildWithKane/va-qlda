import fs from 'fs';
import path from 'path';
import XLSX from 'xlsx';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const filesDir = path.join(__dirname, '..', 'public', 'files');
const xlsxFile = fs.readdirSync(filesDir).find((n) => n.includes('Timeline Process Plan'));
if (!xlsxFile) {
    console.error('Excel not found');
    process.exit(1);
}

function excelDate(n) {
    if (!n || typeof n !== 'number') return null;
    const d = XLSX.SSF.parse_date_code(n);
    return `${d.y}-${String(d.m).padStart(2, '0')}-${String(d.d).padStart(2, '0')}`;
}

const wb = XLSX.readFile(path.join(filesDir, xlsxFile));
const tl = XLSX.utils.sheet_to_json(wb.Sheets['VA - TIMELINES'], { header: 1, defval: '' });

// Sprints: dates/status from OVERVIEWS, tên mục tiêu từ TIMELINES
const ov = XLSX.utils.sheet_to_json(wb.Sheets['VA - OVERVIEWS'], { header: 1, defval: '' });
const ovByNum = new Map();
for (let i = 0; i < ov.length; i++) {
    const r = ov[i];
    const label = String(r[0] ?? '').trim();
    const m = label.match(/^Sprint\s*(\d+)$/i);
    if (!m) continue;
    ovByNum.set(Number(m[1]), {
        start: excelDate(r[2]),
        end: excelDate(r[3]),
        status: String(r[4] ?? '').trim(),
    });
}
const sprintGoals = new Map();
for (let i = 4; i < tl.length; i++) {
    const r = tl[i];
    const wbs = String(r[0] ?? '').trim();
    const sm = wbs.match(/^Sprint\s*(\d+)/i);
    if (!sm) continue;
    sprintGoals.set(Number(sm[1]), String(r[1] ?? '').trim());
}
const sprints = [...ovByNum.keys()].sort((a, b) => a - b).map((num) => {
    const meta = ovByNum.get(num);
    const goal = sprintGoals.get(num) || `Sprint ${num}`;
    return {
        num,
        name: `Sprint ${num} — ${goal}`,
        goal,
        start: meta.start,
        end: meta.end,
        status: meta.status,
    };
});

// Tasks from TIMELINES (canonical titles)
let sprintNum = 0;
const timelineRows = [];
for (let i = 4; i < tl.length; i++) {
    const r = tl[i];
    const wbs = String(r[0] ?? '').trim();
    const title = String(r[1] ?? '').trim();
    if (!wbs && !title) continue;
    const sm = wbs.match(/^Sprint\s*(\d+)/i);
    if (sm) {
        sprintNum = Number(sm[1]);
        continue;
    }
    if (!title) continue;
    timelineRows.push({
        wbs,
        title: title.replace(/^\s+/, ''),
        owner: String(r[2] ?? '').trim(),
        sprintNum,
        start: excelDate(r[3]),
        end: excelDate(r[4]),
        status: String(r[6] ?? '').trim(),
        depth: (wbs.match(/\./g) || []).length,
    });
}

// Enrich from TASKS sheet (estimates, actuals, long descriptions)
const tasksSheet = XLSX.utils.sheet_to_json(wb.Sheets['VA - TASKS'], { header: 1, defval: '' });
const tasksByWbs = new Map();
for (let i = 3; i < tasksSheet.length; i++) {
    const r = tasksSheet[i];
    const wbs = String(r[0] ?? '').trim();
    if (!wbs || wbs === 'WBS' || !/[0-9]/.test(wbs.charAt(0))) continue;
    const title = String(r[1] ?? '').trim();
    tasksByWbs.set(wbs, {
        wbs,
        title,
        owner: String(r[2] ?? '').trim(),
        link: String(r[3] ?? '').trim(),
        start: excelDate(r[4]),
        end: excelDate(r[5]),
        status: String(r[7] ?? '').trim(),
        estimate_hours: r[8] === '' ? null : Number(r[8]),
        actual_hours: r[9] === '' ? null : Number(r[9]),
        progress: r[10] === '' ? null : Number(r[10]),
        note: String(r[15] ?? '').trim(),
    });
}

// Merge: prefer TASKS row when WBS matches; else timeline row
const merged = [];
const seen = new Set();
for (const tr of timelineRows) {
    const extra = tasksByWbs.get(tr.wbs);
    const title = (extra?.title && extra.title.length > 2) ? extra.title : tr.title;
    merged.push({
        ...tr,
        ...extra,
        title,
        sprintNum: tr.sprintNum || Number(String(tr.wbs).split('.')[0]) || null,
    });
    seen.add(tr.wbs);
}
for (const [wbs, extra] of tasksByWbs) {
    if (seen.has(wbs)) continue;
    merged.push({
        wbs,
        title: extra.title || `(Task ${wbs})`,
        owner: extra.owner,
        sprintNum: Number(String(wbs).split('.')[0]),
        start: extra.start,
        end: extra.end,
        status: extra.status,
        depth: (wbs.match(/\./g) || []).length,
        ...extra,
    });
}

merged.sort((a, b) => String(a.wbs).localeCompare(String(b.wbs), undefined, { numeric: true }));

const outDir = path.join(__dirname, '..', 'database', 'seeders', 'data');
fs.mkdirSync(outDir, { recursive: true });
const payload = {
    project: {
        code: 'VA-DV',
        name: 'Phần mềm Điều vận VA Schools',
        description: 'Theo dõi sprint & timeline từ file Timeline Process Plan — PM Điều Vận.',
        start_date: '2026-04-01',
        due_date: '2026-06-11',
    },
    sprints,
    tasks: merged,
};
fs.writeFileSync(path.join(outDir, 'va_dieuvan_timeline.json'), JSON.stringify(payload, null, 2));
console.log('Wrote', merged.length, 'tasks,', sprints.length, 'sprints');
