import {
    SpreadsheetFile,
    Workbook,
} from 'file:///C:/Users/avnish.kumar/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs';
import fs from 'node:fs/promises';
import path from 'node:path';

const workbook = Workbook.create();
const formula = workbook.worksheets.add('Formula Catalog');
const sql = workbook.worksheets.add('MySQL Queries');

const navy = '#063B50';
const cyan = '#1FA3C4';
const pale = '#EAF6FA';
const orange = '#F59E0B';
const ink = '#102A43';
const white = '#FFFFFF';
const green = '#DCFCE7';

for (const s of [formula, sql]) {
    s.showGridLines = false;
}

formula.getRange('A1:H1').merge();
formula.getRange('A1').values = [['GROUP OVERVIEW — FORMULA & DATA LOGIC CATALOG']];
formula.getRange('A1:H1').format = { fill: navy, font: { bold: true, color: white, size: 16 }, rowHeight: 32, verticalAlignment: 'center' };
formula.getRange('A2:H2').merge();
formula.getRange('A2').values = [
    [
        'Dashboard formulas mirror the current Sales & Distribution / Group Overview implementation. Amounts are dynamically recalculated from the database.',
    ],
];
formula.getRange('A2:H2').format = { fill: pale, font: { color: ink, italic: true }, rowHeight: 28, wrapText: true, verticalAlignment: 'center' };

formula.getRange('A4:B9').values = [
    ['Parameter', 'Example / rule'],
    ['Report year', 2026],
    ['Report month', 10],
    ['Company filter', 'NULL = All companies; 1000 = APL; 5000 = KPL'],
    ['Fiscal year', '2026-27'],
    ['Unit conversion', '1 lakh = ₹100,000; 1 crore = ₹10,000,000 = 100 lakh'],
];
formula.getRange('A4:B4').format = { fill: cyan, font: { bold: true, color: white } };
formula.getRange('A4:B9').format.borders = {
    top: { color: '#B8D8E5', style: 'thin' },
    bottom: { color: '#B8D8E5', style: 'thin' },
    left: { color: '#B8D8E5', style: 'thin' },
    right: { color: '#B8D8E5', style: 'thin' },
    insideHorizontal: { color: '#B8D8E5', style: 'thin' },
    insideVertical: { color: '#B8D8E5', style: 'thin' },
};
formula.getRange('A4:A9').format.font = { bold: true, color: ink };

const formulaHeaders = [
    [
        'Section',
        'Dashboard metric',
        'Business formula',
        'Display unit',
        'Source table(s)',
        'Period / status filters',
        'Company filter logic',
        'Notes / visible example',
    ],
];
formula.getRange('A11:H11').values = formulaHeaders;
formula.getRange('A11:H11').format = { fill: navy, font: { bold: true, color: white }, rowHeight: 30, wrapText: true, verticalAlignment: 'center' };

const rows = [
    [
        'Top KPI',
        'Net sales — MTD',
        'Current report-month net_sales ÷ 10,000,000',
        'Crore',
        'tbl_monthly_sales',
        'year = report year; month = report month',
        'Selected company code; ALL row when no company is selected',
        'Screenshot: ₹117.41 Cr',
    ],
    [
        'Top KPI',
        'Net sales — YTD',
        'SUM(net_sales from Jan through report month) ÷ 10,000,000',
        'Crore',
        'tbl_monthly_sales',
        'year = report year; month <= report month',
        'Selected company code; ALL row when no company is selected',
        'Screenshot: ₹1,260.15 Cr',
    ],
    [
        'Top KPI',
        'Collections — MTD',
        'SUM(amount_received for report month) ÷ 10,000,000',
        'Crore',
        'tbl_customer_collection_transactions',
        'status = 1; posting year/month = report year/month',
        'Customer must map to selected company; all customers for NULL',
        'Screenshot: ₹107.13 Cr',
    ],
    [
        'Top KPI',
        'Collections — YTD',
        'SUM(amount_received from Jan through report month) ÷ 10,000,000',
        'Crore',
        'tbl_customer_collection_transactions',
        'status = 1; posting year = report year; month <= report month',
        'Customer must map to selected company; all customers for NULL',
        'Performance card example: ₹344.31 Cr',
    ],
    [
        'Top KPI',
        'Month-to-date value',
        'Same current-month net sales formula as Net sales MTD',
        'Crore',
        'tbl_monthly_sales',
        'year/month = report year/month',
        'Same company rule as Net sales MTD',
        'Do not sum YTD here',
    ],
    [
        'Top KPI',
        'Receivables overdue',
        'SUM(current_month_overdue) ÷ 10,000,000',
        'Crore',
        'tbl_customer_sales_summary + tbl_users',
        'summary.status = 1; customer.status = 1; user_type = USR4',
        'Customer-company map EXISTS for selected company',
        'Screenshot: ₹744.40 Cr',
    ],
    [
        'Inventory',
        'Inventory value',
        'SUM(stock_value) ÷ 10,000,000',
        'Crore',
        'tbl_material_stocks',
        'stock.status = 1',
        'Stock plant must belong to selected company; all plants for NULL',
        'Screenshot: ₹258.04 Cr',
    ],
    [
        'Inventory',
        'ZRAW inventory',
        'SUM(stock_value where material type = ZRAW) ÷ 10,000,000',
        'Crore',
        'tbl_material_stocks + tbl_materials',
        'active stock/material; material type normalized with UPPER/TRIM',
        'Selected company plant list',
        'Screenshot: ₹53.98 Cr',
    ],
    [
        'Inventory',
        'YRAW inventory',
        'SUM(stock_value where material type = YRAW) ÷ 10,000,000',
        'Crore',
        'tbl_material_stocks + tbl_materials',
        'active stock/material; material type normalized with UPPER/TRIM',
        'Selected company plant list',
        'Screenshot: ₹11.05 Cr',
    ],
    [
        'Inventory',
        'Days of cover',
        'SUM(current_stock) ÷ SUM(auto_perday_consumption); unavailable when consumption <= 0',
        'Days',
        'tbl_material_stocks',
        'status = 1',
        'Selected company plant list',
        'Screenshot: 74.6 days',
    ],
    [
        'Priority',
        'Open priority items',
        'COUNT(active high/critical tickets not in resolved/closed/cancelled)',
        'Count',
        'tbl_support_tickets',
        'status = 1; priority IN high,critical; open status',
        'Company-aware only if ticket schema carries company scope',
        'Screenshot: 0',
    ],
    [
        'Priority',
        'Require action today',
        'Open priority items with due_at <= end of current day',
        'Count',
        'tbl_support_tickets',
        'same open filters plus due_at <= today 23:59:59',
        'Same as open priority',
        'Screenshot: 0',
    ],
    [
        'Trend',
        'Full-year sales card',
        'SUM(monthly net sales values available for report year)',
        'Crore',
        'tbl_monthly_sales',
        'year = report year; months through current data',
        'Selected company / ALL row',
        'Chart summary',
    ],
    [
        'Trend',
        'Full-year collections card',
        'SUM(monthly collections values available for report year)',
        'Crore',
        'tbl_customer_collection_transactions',
        'status = 1; report year',
        'Mapped customers for selected company',
        'Chart summary',
    ],
    [
        'Target',
        'Yearly target',
        'SUM(active product target total_sale) ÷ 100',
        'Crore',
        'tbl_product_targets + tbl_customer_company_map',
        'status = 1; fiscal_year = selected FY',
        'Target customer maps to selected company; all mapped companies for NULL',
        'total_sale is stored in lakh; screenshot ₹1,055.85 Cr',
    ],
    [
        'Target',
        'Monthly target',
        'If month-specific target exists: month SUM(total_sale); otherwise yearly target lakh ÷ 12',
        'Lakh',
        'tbl_product_targets + tbl_customer_company_map',
        'active; selected fiscal year',
        'Grouped by company and optionally month',
        'APL example: ₹8,798.75 L',
    ],
    [
        'Trend',
        'Monthly sales line',
        'For each Jan–Dec month: net_sales ÷ 10,000,000; missing months = blank',
        'Crore',
        'tbl_monthly_sales',
        'year = report year',
        'Selected company / ALL row',
        'Do not convert missing future months to misleading zero',
    ],
    [
        'Trend',
        'Monthly collections line',
        'For each Jan–Dec month: SUM(amount_received) ÷ 10,000,000',
        'Crore',
        'tbl_customer_collection_transactions',
        'status = 1; report year',
        'Mapped customers for selected company',
        'Jan–Dec series',
    ],
    [
        'Trend',
        'Monthly target line',
        'Month-specific target ÷ 100 crore; fallback yearly target crore ÷ 12',
        'Crore',
        'tbl_product_targets + tbl_customer_company_map',
        'active; fiscal year',
        'Selected company or all companies',
        'Target line may be flat only when annual target is evenly allocated',
    ],
    [
        'Scorecard',
        'Company MTD sales',
        'Current-month net_sales ÷ 100,000',
        'Lakh',
        'tbl_monthly_sales',
        'report year/month; company_code <> ALL',
        'GROUP BY company_code',
        'APL ₹9,134.32 L; KPL ₹2,607.17 L',
    ],
    [
        'Scorecard',
        'Company monthly target',
        'SUM(total_sale) ÷ 12',
        'Lakh',
        'tbl_product_targets + customer-company map',
        'active; selected fiscal year',
        'GROUP BY company_code',
        'Use month-specific total when monthly target records exist',
    ],
    [
        'Scorecard',
        'Target achieved',
        'IF(monthly target = 0, 0, MTD sales ÷ monthly target × 100)',
        'Percent',
        'Derived',
        'same month/FY scope',
        'Per company',
        'APL 103.8%; KPL 0.0% when target is 0',
    ],
    [
        'Scorecard',
        'Collections',
        'SUM(current_month_payment_received) ÷ 100,000',
        'Lakh',
        'tbl_customer_sales_summary + customer-company map',
        'status = 1',
        'GROUP BY mapped company',
        'APL ₹8,457.41 L; KPL ₹2,277.19 L',
    ],
    [
        'Scorecard',
        'Current overdue',
        'SUM(current_month_overdue) ÷ 100,000',
        'Lakh',
        'tbl_customer_sales_summary + customer-company map',
        'status = 1',
        'GROUP BY mapped company',
        'APL ₹41,778.14 L; KPL ₹23,776.8 L',
    ],
    [
        'Scorecard',
        'Active customers',
        'COUNT(DISTINCT customer_number)',
        'Count',
        'tbl_customer_sales_summary + customer-company map',
        'summary.status = 1',
        'GROUP BY mapped company',
        'APL 938; KPL 512',
    ],
];
formula.getRange(`A12:H${11 + rows.length}`).values = rows;
formula.getRange(`A12:H${11 + rows.length}`).format = { wrapText: true, verticalAlignment: 'top', rowHeight: 58 };
formula.getRange(`A12:H${11 + rows.length}`).format.borders = {
    top: { color: '#D6E3EA', style: 'thin' },
    bottom: { color: '#D6E3EA', style: 'thin' },
    left: { color: '#D6E3EA', style: 'thin' },
    right: { color: '#D6E3EA', style: 'thin' },
    insideHorizontal: { color: '#D6E3EA', style: 'thin' },
    insideVertical: { color: '#D6E3EA', style: 'thin' },
};
for (let r = 12; r <= 11 + rows.length; r++) if (r % 2 === 0) formula.getRange(`A${r}:H${r}`).format.fill = '#F8FBFD';

const exStart = 13 + rows.length;
formula.getRange(`A${exStart}:F${exStart}`).merge();
formula.getRange(`A${exStart}`).values = [['WORKED EXAMPLES — LIVE EXCEL FORMULAS']];
formula.getRange(`A${exStart}:F${exStart}`).format = { fill: orange, font: { bold: true, color: white }, rowHeight: 26 };
formula.getRange(`A${exStart + 1}:F${exStart + 1}`).values = [['Example', 'Input 1', 'Input 2', 'Excel formula result', 'Unit', 'Formula meaning']];
formula.getRange(`A${exStart + 1}:F${exStart + 1}`).format = { fill: navy, font: { bold: true, color: white }, wrapText: true };
formula.getRange(`A${exStart + 2}:C${exStart + 5}`).values = [
    ['APL target achieved', 9134.32, 8798.75],
    ['KPL zero-target safeguard', 2607.17, 0],
    ['Monthly target from annual', 1055.85, 12],
    ['Days of cover', 7460, 100],
];
formula.getRange(`D${exStart + 2}`).formulas = [[`=IF(C${exStart + 2}=0,0,B${exStart + 2}/C${exStart + 2})`]];
formula.getRange(`D${exStart + 3}`).formulas = [[`=IF(C${exStart + 3}=0,0,B${exStart + 3}/C${exStart + 3})`]];
formula.getRange(`D${exStart + 4}`).formulas = [[`=B${exStart + 4}/C${exStart + 4}`]];
formula.getRange(`D${exStart + 5}`).formulas = [[`=IF(C${exStart + 5}<=0,0,B${exStart + 5}/C${exStart + 5})`]];
formula.getRange(`E${exStart + 2}:F${exStart + 5}`).values = [
    ['%', 'MTD actual ÷ monthly target'],
    ['%', 'Returns 0 when target is zero'],
    ['Crore', 'Annual target ÷ 12'],
    ['Days', 'Current stock ÷ daily consumption'],
];
formula.getRange(`D${exStart + 2}:D${exStart + 3}`).format.numberFormat = '0.0%';
formula.getRange(`D${exStart + 4}:D${exStart + 5}`).format.numberFormat = '0.00';
formula.getRange(`A${exStart + 1}:F${exStart + 5}`).format.borders = {
    top: { color: '#C7D7E0', style: 'thin' },
    bottom: { color: '#C7D7E0', style: 'thin' },
    left: { color: '#C7D7E0', style: 'thin' },
    right: { color: '#C7D7E0', style: 'thin' },
    insideHorizontal: { color: '#C7D7E0', style: 'thin' },
    insideVertical: { color: '#C7D7E0', style: 'thin' },
};
formula.getRange(`D${exStart + 2}:D${exStart + 5}`).format.fill = green;

formula.getRange('A:A').format.columnWidth = 16;
formula.getRange('B:B').format.columnWidth = 27;
formula.getRange('C:C').format.columnWidth = 48;
formula.getRange('D:D').format.columnWidth = 14;
formula.getRange('E:E').format.columnWidth = 37;
formula.getRange('F:F').format.columnWidth = 40;
formula.getRange('G:G').format.columnWidth = 43;
formula.getRange('H:H').format.columnWidth = 38;
formula.freezePanes.freezeRows(11);

sql.getRange('A1:E1').merge();
sql.getRange('A1').values = [['GROUP OVERVIEW — EXECUTABLE MYSQL 8 QUERY CATALOG']];
sql.getRange('A1:E1').format = { fill: navy, font: { bold: true, color: white, size: 16 }, rowHeight: 32, verticalAlignment: 'center' };
sql.getRange('A2:E2').merge();
sql.getRange('A2').values = [
    ["Set @company_code to NULL for all companies, '1000' for APL, or '5000' for KPL. Queries return live database values."],
];
sql.getRange('A2:E2').format = { fill: pale, font: { italic: true, color: ink }, rowHeight: 28, wrapText: true };
sql.getRange('A4:B8').values = [
    ['SQL parameter', 'Value / example'],
    ['@report_year', 2026],
    ['@report_month', 10],
    ['@company_code', "NULL / '1000' / '5000'"],
    ['@fiscal_year', "'2026-27'"],
];
sql.getRange('A4:B4').format = { fill: cyan, font: { bold: true, color: white } };
sql.getRange('A4:B8').format.borders = {
    top: { color: '#B8D8E5', style: 'thin' },
    bottom: { color: '#B8D8E5', style: 'thin' },
    left: { color: '#B8D8E5', style: 'thin' },
    right: { color: '#B8D8E5', style: 'thin' },
    insideHorizontal: { color: '#B8D8E5', style: 'thin' },
    insideVertical: { color: '#B8D8E5', style: 'thin' },
};
sql.getRange('D4:E8').merge();
sql.getRange('D4').values = [
    ["Run first:\nSET @report_year = 2026;\nSET @report_month = 10;\nSET @company_code = NULL; -- or '1000' / '5000'\nSET @fiscal_year = '2026-27';"],
];
sql.getRange('D4:E8').format = { fill: '#F4F7FA', font: { name: 'Consolas', color: ink }, wrapText: true, verticalAlignment: 'top' };

sql.getRange('A10:E10').values = [['ID', 'Query name', 'Dashboard output', 'MySQL 8 query', 'Units / important notes']];
sql.getRange('A10:E10').format = { fill: navy, font: { bold: true, color: white }, rowHeight: 30, wrapText: true };

const q = [];
q.push([
    'Q01',
    'Net sales MTD and YTD',
    'Net Sales + MTD Value',
    `SELECT
  ROUND(SUM(CASE WHEN month = @report_month THEN net_sales ELSE 0 END) / 10000000, 2) AS mtd_sales_crore,
  ROUND(SUM(CASE WHEN month <= @report_month THEN net_sales ELSE 0 END) / 10000000, 2) AS ytd_sales_crore
FROM tbl_monthly_sales
WHERE year = @report_year
  AND company_code = COALESCE(@company_code, 'ALL');`,
    'Crore; all-company sales uses the pre-aggregated ALL row',
]);
q.push([
    'Q02',
    'Collections MTD and YTD',
    'Collections cards',
    `SELECT
  ROUND(SUM(CASE WHEN MONTH(c.posting_date) = @report_month THEN c.amount_received ELSE 0 END) / 10000000, 2) AS mtd_collection_crore,
  ROUND(SUM(CASE WHEN MONTH(c.posting_date) <= @report_month THEN c.amount_received ELSE 0 END) / 10000000, 2) AS ytd_collection_crore
FROM tbl_customer_collection_transactions c
WHERE c.status = 1
  AND YEAR(c.posting_date) = @report_year
  AND (@company_code IS NULL OR EXISTS (
    SELECT 1 FROM tbl_customer_company_map m
    WHERE m.customer_number = c.customer_number
      AND m.company_code = @company_code
  ));`,
    'Crore; collection import stores receipt transactions',
]);
q.push([
    'Q03',
    'Month-wise collections',
    'Orange collection line',
    `WITH RECURSIVE months AS (
  SELECT 1 AS month_number
  UNION ALL SELECT month_number + 1 FROM months WHERE month_number < 12
), totals AS (
  SELECT MONTH(c.posting_date) AS month_number, SUM(c.amount_received) AS amount
  FROM tbl_customer_collection_transactions c
  WHERE c.status = 1 AND YEAR(c.posting_date) = @report_year
    AND (@company_code IS NULL OR EXISTS (
      SELECT 1 FROM tbl_customer_company_map m
      WHERE m.customer_number = c.customer_number AND m.company_code = @company_code
    ))
  GROUP BY MONTH(c.posting_date)
)
SELECT m.month_number, ELT(m.month_number,'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec') AS month_name,
       ROUND(COALESCE(t.amount,0) / 10000000,2) AS collection_crore
FROM months m LEFT JOIN totals t USING (month_number)
ORDER BY m.month_number;`,
    'Crore; emits all 12 months including zero-collection months',
]);
q.push([
    'Q04',
    'Receivables overdue',
    'Receivables overdue card',
    `SELECT ROUND(COALESCE(SUM(s.current_month_overdue),0) / 10000000,2) AS overdue_crore
FROM tbl_customer_sales_summary s
JOIN tbl_users u ON u.customer_number = s.customer_number
WHERE s.status = 1 AND u.status = 1 AND UPPER(u.user_type) = 'USR4'
  AND (@company_code IS NULL OR EXISTS (
    SELECT 1 FROM tbl_customer_company_map m
    WHERE m.customer_number = s.customer_number AND m.company_code = @company_code
  ));`,
    'Crore; uses active USR4 customers',
]);
q.push([
    'Q05',
    'Inventory total, ZRAW, YRAW and cover',
    'Inventory card',
    `WITH material_type AS (
  SELECT matnr, MAX(UPPER(TRIM(mtart))) AS material_type
  FROM tbl_materials WHERE status = 1 GROUP BY matnr
)
SELECT
  ROUND(COALESCE(SUM(s.stock_value),0) / 10000000,2) AS inventory_crore,
  ROUND(COALESCE(SUM(CASE WHEN mt.material_type='ZRAW' THEN s.stock_value ELSE 0 END),0) / 10000000,2) AS zraw_crore,
  ROUND(COALESCE(SUM(CASE WHEN mt.material_type='YRAW' THEN s.stock_value ELSE 0 END),0) / 10000000,2) AS yraw_crore,
  ROUND(CASE WHEN SUM(s.auto_perday_consumption)>0 THEN SUM(s.current_stock)/SUM(s.auto_perday_consumption) ELSE 0 END,1) AS days_cover
FROM tbl_material_stocks s
LEFT JOIN material_type mt ON mt.matnr = s.material_code
WHERE s.status = 1
  AND (@company_code IS NULL OR s.plant_code IN (
    SELECT plant_code FROM tbl_company_plants WHERE company_code = @company_code AND status = 1
  ));`,
    "Crore and days; if your plant table has another name, replace tbl_company_plants with the app's plant-code source",
]);
q.push([
    'Q06',
    'Open priority items',
    'Priority card',
    `SELECT
  COUNT(*) AS open_priority_items,
  SUM(CASE WHEN due_at <= CONCAT(CURDATE(),' 23:59:59') THEN 1 ELSE 0 END) AS require_action_today
FROM tbl_support_tickets
WHERE status = 1
  AND LOWER(priority) IN ('high','critical')
  AND LOWER(ticket_status) NOT IN ('resolved','closed','cancelled');`,
    'Counts; only valid when tbl_support_tickets exists',
]);
q.push([
    'Q07',
    'Yearly and monthly targets by company',
    'Target cards + scorecard',
    `SELECT
  m.company_code,
  ROUND(SUM(t.total_sale),2) AS yearly_target_lakh,
  ROUND(SUM(t.total_sale)/100,2) AS yearly_target_crore,
  ROUND(SUM(t.total_sale)/12,2) AS monthly_target_lakh
FROM tbl_product_targets t
JOIN (SELECT DISTINCT customer_number, company_code FROM tbl_customer_company_map) m
  ON m.customer_number = t.customer_code
WHERE t.status = 1 AND t.fiscal_year = @fiscal_year
  AND (@company_code IS NULL OR m.company_code = @company_code)
GROUP BY m.company_code
ORDER BY m.company_code;`,
    'Targets are stored in lakh; 100 lakh = 1 crore',
]);
q.push([
    'Q08',
    'Month-wise sales, collections and target',
    'Three-line trend chart',
    `WITH RECURSIVE months AS (
 SELECT 1 month_number UNION ALL SELECT month_number+1 FROM months WHERE month_number<12
), sales AS (
 SELECT month month_number, net_sales/10000000 value_crore
 FROM tbl_monthly_sales
 WHERE year=@report_year AND company_code=COALESCE(@company_code,'ALL')
), collections AS (
 SELECT MONTH(c.posting_date) month_number, SUM(c.amount_received)/10000000 value_crore
 FROM tbl_customer_collection_transactions c
 WHERE c.status=1 AND YEAR(c.posting_date)=@report_year
   AND (@company_code IS NULL OR EXISTS (SELECT 1 FROM tbl_customer_company_map cm WHERE cm.customer_number=c.customer_number AND cm.company_code=@company_code))
 GROUP BY MONTH(c.posting_date)
), target AS (
 SELECT SUM(t.total_sale)/100 AS annual_crore
 FROM tbl_product_targets t
 JOIN (SELECT DISTINCT customer_number,company_code FROM tbl_customer_company_map) cm ON cm.customer_number=t.customer_code
 WHERE t.status=1 AND t.fiscal_year=@fiscal_year AND (@company_code IS NULL OR cm.company_code=@company_code)
)
SELECT m.month_number, ELT(m.month_number,'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec') month_name,
       ROUND(s.value_crore,2) sales_crore,
       ROUND(COALESCE(c.value_crore,0),2) collection_crore,
       ROUND(t.annual_crore/12,2) target_crore
FROM months m LEFT JOIN sales s USING(month_number) LEFT JOIN collections c USING(month_number) CROSS JOIN target t
ORDER BY m.month_number;`,
    'Crore; evenly allocated target fallback. Replace with month field aggregation when month-specific targets are populated',
]);
q.push([
    'Q09',
    'Company scorecard',
    'APL/KPL table',
    `WITH actual AS (
 SELECT company_code, SUM(net_sales)/100000 AS mtd_sales_lakh
 FROM tbl_monthly_sales
 WHERE year=@report_year AND month=@report_month AND company_code<>'ALL'
 GROUP BY company_code
), target AS (
 SELECT cm.company_code, SUM(t.total_sale)/12 AS monthly_target_lakh
 FROM tbl_product_targets t
 JOIN (SELECT DISTINCT customer_number,company_code FROM tbl_customer_company_map) cm ON cm.customer_number=t.customer_code
 WHERE t.status=1 AND t.fiscal_year=@fiscal_year GROUP BY cm.company_code
), customer AS (
 SELECT cm.company_code,
   SUM(s.current_month_payment_received)/100000 AS collections_lakh,
   SUM(s.current_month_overdue)/100000 AS overdue_lakh,
   COUNT(DISTINCT s.customer_number) AS active_customers
 FROM tbl_customer_sales_summary s
 JOIN (SELECT DISTINCT customer_number,company_code FROM tbl_customer_company_map) cm ON cm.customer_number=s.customer_number
 WHERE s.status=1 GROUP BY cm.company_code
)
SELECT c.company_code, c.company_name,
 ROUND(COALESCE(a.mtd_sales_lakh,0),2) mtd_sales_lakh,
 ROUND(COALESCE(t.monthly_target_lakh,0),2) monthly_target_lakh,
 ROUND(CASE WHEN COALESCE(t.monthly_target_lakh,0)>0 THEN a.mtd_sales_lakh/t.monthly_target_lakh*100 ELSE 0 END,1) target_achieved_pct,
 ROUND(COALESCE(x.collections_lakh,0),2) collections_lakh,
 ROUND(COALESCE(x.overdue_lakh,0),2) overdue_lakh,
 COALESCE(x.active_customers,0) active_customers
FROM tbl_company c
LEFT JOIN actual a ON a.company_code=c.company_code
LEFT JOIN target t ON t.company_code=c.company_code
LEFT JOIN customer x ON x.company_code=c.company_code
WHERE c.status=1 AND (@company_code IS NULL OR c.company_code=@company_code)
ORDER BY c.company_code;`,
    'Lakh, percent and count; target achieved safely returns 0 for zero target',
]);
q.push([
    'Q10',
    'Collection transaction detail',
    'Audit / reconciliation',
    `SELECT c.customer_number, c.company_code, c.document_number, c.posting_date,
       c.document_date, c.document_type, c.amount_received, c.currency,
       c.reference_number, c.description, c.synced_at
FROM tbl_customer_collection_transactions c
WHERE c.status=1 AND YEAR(c.posting_date)=@report_year
  AND MONTH(c.posting_date)<=@report_month
  AND (@company_code IS NULL OR c.company_code=@company_code OR EXISTS (
    SELECT 1 FROM tbl_customer_company_map m
    WHERE m.customer_number=c.customer_number AND m.company_code=@company_code
  ))
ORDER BY c.posting_date, c.customer_number, c.document_number;`,
    'Raw currency amount; useful to reconcile totals and duplicates',
]);
q.push([
    'Q11',
    'Data freshness',
    'Last sync / updated labels',
    `SELECT 'Collections' dataset, MAX(synced_at) last_sync FROM tbl_customer_collection_transactions
UNION ALL SELECT 'Sales summary', MAX(updated_at) FROM tbl_customer_sales_summary
UNION ALL SELECT 'Monthly sales', MAX(updated_at) FROM tbl_monthly_sales
UNION ALL SELECT 'Inventory', MAX(updated_at) FROM tbl_material_stocks
UNION ALL SELECT 'Product targets', MAX(updated_at) FROM tbl_product_targets;`,
    'Timestamps; drives dashboard freshness labels',
]);

sql.getRange(`A11:E${10 + q.length}`).values = q;
sql.getRange(`A11:E${10 + q.length}`).format = { wrapText: true, verticalAlignment: 'top', rowHeight: 155 };
sql.getRange(`A11:E${10 + q.length}`).format.borders = {
    top: { color: '#D6E3EA', style: 'thin' },
    bottom: { color: '#D6E3EA', style: 'thin' },
    left: { color: '#D6E3EA', style: 'thin' },
    right: { color: '#D6E3EA', style: 'thin' },
    insideHorizontal: { color: '#D6E3EA', style: 'thin' },
    insideVertical: { color: '#D6E3EA', style: 'thin' },
};
sql.getRange(`D11:D${10 + q.length}`).format.font = { name: 'Consolas', size: 9, color: ink };
for (let r = 11; r <= 10 + q.length; r++) if (r % 2 === 1) sql.getRange(`A${r}:E${r}`).format.fill = '#F8FBFD';
sql.getRange('A:A').format.columnWidth = 8;
sql.getRange('B:B').format.columnWidth = 30;
sql.getRange('C:C').format.columnWidth = 31;
sql.getRange('D:D').format.columnWidth = 112;
sql.getRange('E:E').format.columnWidth = 35;
sql.freezePanes.freezeRows(10);

workbook.recalculate();

const checks = {};
checks.formula = await workbook.inspect({
    kind: 'table',
    range: `Formula Catalog!A1:H${exStart + 5}`,
    include: 'values,formulas',
    tableMaxRows: 12,
    tableMaxCols: 8,
});
checks.sql = await workbook.inspect({
    kind: 'table',
    range: `MySQL Queries!A1:E${10 + q.length}`,
    include: 'values',
    tableMaxRows: 8,
    tableMaxCols: 5,
});
checks.errors = await workbook.inspect({
    kind: 'match',
    searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A',
    options: { useRegex: true, maxResults: 100 },
    summary: 'final formula error scan',
});

const outDir = path.resolve('outputs', '01a18db5-5166-7f71-8445-a77b246881ec');
await fs.mkdir(outDir, { recursive: true });
const preview1 = await workbook.render({ sheetName: 'Formula Catalog', range: `A1:H${exStart + 5}`, autoCrop: 'all', scale: 0.7, format: 'png' });
await fs.writeFile(path.join(outDir, 'formula_catalog_preview.png'), new Uint8Array(await preview1.arrayBuffer()));
const preview2 = await workbook.render({ sheetName: 'MySQL Queries', range: `A1:E${10 + q.length}`, autoCrop: 'all', scale: 0.55, format: 'png' });
await fs.writeFile(path.join(outDir, 'mysql_queries_preview.png'), new Uint8Array(await preview2.arrayBuffer()));
const xlsx = await SpreadsheetFile.exportXlsx(workbook);
const outFile = path.join(outDir, 'group_overview_formulas_and_mysql_queries.xlsx');
await xlsx.save(outFile);
console.log(JSON.stringify({ outFile, checks }, null, 2));
