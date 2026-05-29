import React, { useState, useEffect, useCallback, useRef, useImperativeHandle, forwardRef } from 'react';
import * as XLSX from 'xlsx-js-style';
import { unzipSync, zipSync, strFromU8, strToU8 } from 'fflate';
import api from '../api';
import { showToast } from './Toast';
import { FileAttach, parseAttachLimit } from './DataForm';

// 클립보드(엑셀/구글시트/그리드) TSV 파서 — 표준 CSV 인용 규칙 처리.
//   탭/개행/따옴표를 포함한 셀은 "..." 로 감싸지고 내부 " 는 "" 로 이스케이프됨.
//   naive split('\t')/split('\n') 은 셀 내 줄바꿈·탭에서 행/열이 폭증하므로 상태머신으로 파싱.
function parseClipboardTSV(text) {
  const s = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  const rows = [];
  let row = [], field = '', inQ = false;
  for (let i = 0; i < s.length; i++) {
    const ch = s[i];
    if (inQ) {
      if (ch === '"') { if (s[i + 1] === '"') { field += '"'; i++; } else inQ = false; }
      else field += ch;
    } else {
      if (ch === '"' && field === '') inQ = true;     // 필드 시작 위치의 " 만 인용 시작
      else if (ch === '\t') { row.push(field); field = ''; }
      else if (ch === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
      else field += ch;
    }
  }
  row.push(field); rows.push(row);
  // 엑셀의 꼬리 개행으로 생긴 마지막 빈 행 1개 제거
  if (rows.length > 1) {
    const last = rows[rows.length - 1];
    if (last.length === 1 && last[0] === '') rows.pop();
  }
  return rows;
}
import SearchableSelect, { SEARCHABLE_THRESHOLD } from './SearchableSelect';

/** SQL 가독성 포맷: 주요 절 앞에 줄바꿈 (서버 포맷 SQL은 그대로 반환) */
function formatSQL(sql) {
  if (!sql) return sql;
  // 서버에서 이미 포맷된 SQL (줄바꿈 있음)
  if (sql.includes('\n')) return sql.trim();
  // 서버 어노테이션 SQL (-- 주석 포함, 줄바꿈은 없는 경우): 키워드·주석 앞에 줄바꿈 복원
  if (sql.trimStart().startsWith('--')) {
    return sql
      .replace(/[ \t]+(-- )/g,           '\n$1')       // 공백 뒤의 --를 줄바꿈으로
      .replace(/[ \t]+\bSELECT\b/gi,     '\n\nSELECT')
      .replace(/[ \t]+\bFROM\b/gi,       '\nFROM')
      .replace(/[ \t]+\bLEFT\s+JOIN\b/gi,'\nLEFT JOIN')
      .replace(/[ \t]+\bINNER\s+JOIN\b/gi,'\nINNER JOIN')
      .replace(/[ \t]+\bWHERE\b/gi,      '\nWHERE')
      .replace(/[ \t]+\bAND\b/gi,        '\n  AND')
      .replace(/[ \t]+\bGROUP\s+BY\b/gi, '\nGROUP BY')
      .replace(/[ \t]+\bORDER\s+BY\b/gi, '\nORDER BY')
      .replace(/[ \t]+\bLIMIT\b/gi,      '\nLIMIT')
      .trim();
  }
  // 일반 단일행 SQL: 키워드 앞에 줄바꿈 삽입
  let s = sql.replace(/\s+/g, ' ').trim();
  s = s
    .replace(/\bFROM\b/gi,         '\nFROM')
    .replace(/\bLEFT\s+JOIN\b/gi,  '\nLEFT JOIN')
    .replace(/\bRIGHT\s+JOIN\b/gi, '\nRIGHT JOIN')
    .replace(/\bINNER\s+JOIN\b/gi, '\nINNER JOIN')
    .replace(/\bCROSS\s+JOIN\b/gi, '\nCROSS JOIN')
    .replace(/\bWHERE\b/gi,        '\nWHERE')
    .replace(/\bAND\b/gi,          '\n  AND')
    .replace(/\bOR\b/gi,           '\n  OR')
    .replace(/\bGROUP\s+BY\b/gi,   '\nGROUP BY')
    .replace(/\bORDER\s+BY\b/gi,   '\nORDER BY')
    .replace(/\bHAVING\b/gi,       '\nHAVING')
    .replace(/\bLIMIT\b/gi,        '\nLIMIT')
    .replace(/\bOFFSET\b/gi,       '\nOFFSET');
  return s.trim();
}

/** 바인딩 값을 SQL ? 에 대입해 완성된 쿼리 반환 */
function buildCompleteSQL(sql, bindings) {
  if (!bindings?.length) return sql;
  let i = 0;
  return sql.replace(/\?/g, () => {
    const v = bindings[i++];
    if (v === null || v === undefined) return 'NULL';
    if (typeof v === 'number') return String(v);
    return `'${String(v).replace(/'/g, "''")}'`;
  });
}

/** 복사용 텍스트: -- 1. SELECT / -- 2. COUNT 형식 (완성 쿼리 + 포맷) */
function buildCopyText(devSql) {
  const parts = ['-- 1. SELECT', formatSQL(buildCompleteSQL(devSql.sql, devSql.bindings)) + ';'];
  if (devSql.count_sql) {
    parts.push('\n-- 2. COUNT', formatSQL(buildCompleteSQL(devSql.count_sql, devSql.bindings)) + ';');
  }
  return parts.join('\n');
}

function copyText(text) {
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
  } else {
    legacyCopy(text);
  }
}

function legacyCopy(text) {
  const el = document.createElement('textarea');
  el.value = text;
  el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
  document.body.appendChild(el);
  el.select();
  try { document.execCommand('copy'); } catch {}
  document.body.removeChild(el);
}

const cfg = window.__APP_CONFIG__ ?? {};
const PAGE_SIZE = cfg.defaultPageSize ?? 25;

/** grid_align → Tailwind 텍스트 정렬 클래스 ('center' / 'right' / 그 외=기본 left) */
function alignClass(align) {
  if (align === 'center') return 'text-center';
  if (align === 'right')  return 'text-right';
  return '';
}

/** grid_align → Excel horizontal alignment (우선순위: grid_align > 숫자=right > default) */
function xlsxHorizontal(align, isNum, fallback = null) {
  if (align === 'center') return 'center';
  if (align === 'right')  return 'right';
  if (isNum)              return 'right';
  return fallback;
}

/** grid_align → 인쇄용 text-align 값 */
function printAlign(align) {
  if (align === 'center' || align === 'right') return align;
  return '';
}

/** Excel 시트명 금지문자 (: \ / ? * [ ]) 제거 + 31자 제한 */
function sanitizeSheetName(name) {
  return String(name ?? 'Sheet1').replace(/[:\\\/?*\[\]]/g, '_').slice(0, 31) || 'Sheet1';
}

/** 파일명 금지문자 (\ / : * ? " < > |) 제거 */
function sanitizeFileName(name) {
  return String(name ?? 'export').replace(/[\\\/:*?"<>|]/g, '_').trim() || 'export';
}

/** 현재 시각 → 파일명 suffix (YYYYMMDD_HHmmss) */
function fileTimestamp() {
  const d = new Date();
  const p = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth()+1)}${p(d.getDate())}_${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

/**
 * xlsx-js-style은 freeze pane을 기록하지 않는다.
 * XLSX.write → zip 해제 → sheet XML에 <pane> 주입 → 재압축 → 브라우저 다운로드.
 */
function writeXlsxWithFreeze(wb, filename, ySplit) {
  const buf = XLSX.write(wb, { type: 'array', bookType: 'xlsx' });
  if (ySplit > 0) {
    const zip = unzipSync(new Uint8Array(buf));
    const pane = `<pane ySplit="${ySplit}" topLeftCell="A${ySplit + 1}" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A${ySplit + 1}" sqref="A${ySplit + 1}"/>`;
    Object.keys(zip).forEach(p => {
      if (!/^xl\/worksheets\/sheet\d+\.xml$/.test(p)) return;
      let xml = strFromU8(zip[p]);
      if (/<sheetView\b[^>]*\/>/.test(xml)) {
        xml = xml.replace(/<sheetView\b([^>]*)\/>/, `<sheetView$1>${pane}</sheetView>`);
      } else if (/<sheetView\b[^>]*>/.test(xml)) {
        xml = xml.replace(/(<sheetView\b[^>]*>)/, `$1${pane}`);
      } else if (/<sheetViews\b[^>]*\/>/.test(xml)) {
        xml = xml.replace(/<sheetViews\b[^>]*\/>/, `<sheetViews><sheetView workbookViewId="0">${pane}</sheetView></sheetViews>`);
      }
      zip[p] = strToU8(xml);
    });
    const out = zipSync(zip);
    downloadBlob(new Blob([out], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename);
    return;
  }
  downloadBlob(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename);
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * col_title 파싱: "상위,상위계정코드" → { r1:'상위', r2:'상위계정코드' }
 *                 ",상위계정명"       → { r1:'',    r2:'상위계정명' }
 *                 "비고"              → { r1:null,  r2:'비고' }  (콤마 없음=standalone)
 */
function parseColTitle(colTitle) {
  const s = colTitle ?? '';
  const ci = s.indexOf(',');
  if (ci === -1) return { r1: null, r2: s };
  return { r1: s.slice(0, ci), r2: s.slice(ci + 1) };
}

/**
 * aggregate 팝업의 실데이터 엑셀 다운로드
 * - 숫자 필드는 숫자 속성으로 출력
 * - 파일명: {menu_name}_{소계|합계}.xlsx
 */
function exportAggPopupExcel(aggPopup, listFields, menu) {
  const rows = aggPopup?.rows ?? [];
  const headers = ['No', ...listFields.map(f => {
    const t = parseColTitle(f.col_title ?? f.alias_name ?? '');
    return t.r2 ?? t.r1 ?? f.alias_name ?? '';
  })];

  const thinBd = { style: 'thin', color: { rgb: 'FFCBD0DB' } };
  const borderAll = { top: thinBd, bottom: thinBd, left: thinBd, right: thinBd };
  const headerStyle = {
    font: { bold: true, color: { rgb: 'FFFFFFFF' }, sz: 11 },
    fill: { patternType: 'solid', fgColor: { rgb: 'FF4F6EF7' } },
    alignment: { horizontal: 'center', vertical: 'center' },
    border: {
      top: { style: 'thin', color: { rgb: 'FF2E3250' } },
      bottom: { style: 'medium', color: { rgb: 'FF2E3250' } },
      left: { style: 'thin', color: { rgb: 'FF2E3250' } },
      right: { style: 'thin', color: { rgb: 'FF2E3250' } },
    },
  };
  const dataBase = { border: borderAll, alignment: { vertical: 'center' } };
  const stripeFill = { fill: { patternType: 'solid', fgColor: { rgb: 'FFF7F8FC' } } };

  const ws = {};
  const range = { s: { r: 0, c: 0 }, e: { r: rows.length, c: headers.length - 1 } };

  headers.forEach((h, ci) => {
    const addr = XLSX.utils.encode_cell({ r: 0, c: ci });
    ws[addr] = { t: 's', v: h, s: headerStyle };
  });

  rows.forEach((row, ri) => {
    const stripe = ri % 2 === 1 ? stripeFill : null;
    const cellBase = { ...dataBase, ...(stripe ?? {}) };
    const noAddr = XLSX.utils.encode_cell({ r: ri + 1, c: 0 });
    ws[noAddr] = { t: 'n', v: ri + 1, z: '0', s: { ...cellBase, alignment: { horizontal: 'center', vertical: 'center' }, font: { color: { rgb: 'FF8C93B0' } } } };
    listFields.forEach((f, ci) => {
      const addr = XLSX.utils.encode_cell({ r: ri + 1, c: ci + 1 });
      const alias = f.alias_name ?? '';
      const raw = row[alias] ?? '';
      const isNum = typeof f.schema_type === 'string' && f.schema_type.startsWith('number');
      if (raw === '' || raw === null || raw === undefined) {
        ws[addr] = { t: 's', v: '', s: cellBase };
      } else if (isNum) {
        const n = typeof raw === 'number' ? raw : parseFloat(String(raw).replace(/,/g, ''));
        ws[addr] = Number.isFinite(n)
          ? { t: 'n', v: n, s: { ...cellBase, alignment: { horizontal: 'right', vertical: 'center' }, numFmt: '#,##0' } }
          : { t: 's', v: String(raw), s: cellBase };
      } else {
        ws[addr] = { t: 's', v: String(raw), s: cellBase };
      }
    });
  });

  const pxToWch = (px) => Math.max(4, Math.round((px - 5) / 7));
  ws['!cols'] = [{ wch: 6 }, ...listFields.map(f => {
    const px = Math.max(42, Math.abs(parseInt(f.col_width ?? '10', 10)) * 8);
    return { wch: pxToWch(px) };
  })];
  ws['!rows'] = [{ hpt: 22 }, ...rows.map(() => ({ hpt: 18 }))];
  ws['!ref'] = XLSX.utils.encode_range(range);
  ws['!views'] = [{ state: 'frozen', xSplit: 0, ySplit: 1, topLeftCell: 'A2', activePane: 'bottomLeft' }];

  const wb = XLSX.utils.book_new();
  const sheetName = sanitizeSheetName(menu?.menu_name ?? 'export');
  XLSX.utils.book_append_sheet(wb, ws, sheetName);
  const label = aggPopup?.title?.includes('합계') ? '합계' : '소계';
  const baseName = sanitizeFileName(menu?.menu_name ?? 'export');
  writeXlsxWithFreeze(wb, `${baseName}_${label}_${fileTimestamp()}.xlsx`, 1);
}

/**
 * URL orderby 문자열 정규화: "field desc,field2 asc" → "-field,field2"
 */
function normalizeOrderby(ob) {
  if (!ob) return '';
  return ob.split(',').map(t => {
    t = t.trim();
    const lc = t.toLowerCase();
    if (lc.endsWith(' desc')) return '-' + t.slice(0, -5).trim();
    if (lc.endsWith(' asc'))  return t.slice(0, -4).trim();
    return t;
  }).filter(Boolean).join(',');
}

/**
 * URL allFilter JSON → filterValues 초기값 추출
 * toolbar_ 접두어 제거, between → {from,to}
 */
function parseUrlFilter(afStr) {
  try {
    const filters = JSON.parse(afStr);
    const vals = {};
    (filters ?? []).forEach(f => {
      let field = f.field ?? '';
      if (field.startsWith('toolbar_')) field = field.slice(8);
      if (!field || f.value === undefined) return;
      if (f.operator === 'between' && Array.isArray(f.value)) {
        vals[field] = { from: f.value[0] ?? '', to: f.value[1] ?? '' };
      } else {
        vals[field] = f.value;
      }
    });
    return vals;
  } catch {
    return {};
  }
}

/**
 * listFields → 2행 헤더 그룹 계산
 */
function computeHeaderGroups(listFields) {
  const parsed = listFields.map(f => ({
    ...parseColTitle(f.col_title ?? f.alias_name ?? ''),
    field: f,
  }));

  const groups = [];
  parsed.forEach((p, i) => {
    if (p.r1 === null) {
      groups.push({ r1: null, colspan: 1, startIdx: i });
    } else if (p.r1 !== '') {
      groups.push({ r1: p.r1, colspan: 1, startIdx: i });
    } else {
      if (groups.length > 0 && groups[groups.length - 1].r1 !== null) {
        groups[groups.length - 1].colspan++;
      } else {
        groups.push({ r1: '', colspan: 1, startIdx: i });
      }
    }
  });

  return { parsed, groups };
}

/** items 문자열 → [{value, text}] */
function parseItems(items) {
  try {
    const parsed = JSON.parse(items ?? '[]');
    if (Array.isArray(parsed)) {
      return parsed.map(o => (typeof o === 'object' ? o : { value: o, text: o }));
    }
  } catch {}
  return (items ?? '').split(',').filter(Boolean).map(v => ({ value: v.trim(), text: v.trim() }));
}

/** mis_menu_fields grid_orderby ('1a','2d'...) → orderby 문자열 */
function buildDefaultOrderby(fields) {
  return fields
    .filter(f => f.grid_orderby)
    .map(f => {
      const raw  = String(f.grid_orderby);
      const rank = parseInt(raw, 10);
      const desc = raw.endsWith('d');
      return { alias: f.alias_name ?? '', rank, desc };
    })
    .filter(item => item.rank > 0 && item.alias)
    .sort((a, b) => a.rank - b.rank)
    .map(item => item.desc ? `-${item.alias}` : item.alias)
    .join(',');
}

/** filterValues → allFilter JSON */
function buildAllFilter(filterValues, filterFields) {
  const filters = [];
  filterFields.forEach(f => {
    const alias  = f.alias_name ?? '';
    const handle = f.grid_is_handle ?? '';
    const val    = filterValues[alias];
    if (handle === 't' && val) {
      filters.push({ field: alias, operator: 'contains', value: val });
    } else if (handle === 's' && val) {
      filters.push({ field: alias, operator: 'eq', value: val });
    } else if (handle === 'w') {
      const from = val?.from ?? '';
      const to   = val?.to   ?? '';
      if (from || to) filters.push({ field: alias, operator: 'between', value: [from, to] });
    }
  });
  return JSON.stringify(filters);
}

/** 필터 객체 → 입력 문자열 (URL 복원용) */
function filterToInputStr(f) {
  const v = Array.isArray(f.value) ? f.value.join(',,') : String(f.value ?? '');
  switch (f.operator) {
    case 'eq':         return `=${v}`;
    case 'neq':        return `<>${v}`;
    case 'lt':         return `<${v}`;
    case 'lte':        return `<=${v}`;
    case 'gt':         return `>${v}`;
    case 'gte':        return `>=${v}`;
    case 'startsWith': return `${v}%`;
    case 'endsWith':   return `%${v}`;
    case 'isNull':     return ',,';
    default:           return v; // contains / in
  }
}

/** URL allFilter 에서 toolbar_ 가 아닌 항목만 추출 → 입력값 맵 */
function parseUrlColFilters(afStr) {
  try {
    const vals = {};
    (JSON.parse(afStr) ?? []).forEach(f => {
      const field = f.field ?? '';
      if (!field || field.startsWith('toolbar_')) return;
      vals[field] = filterToInputStr(f);
    });
    return vals;
  } catch { return {}; }
}

/** URL allFilter 에서 toolbar_ 항목만 남긴 JSON (초기 load용) */
function toolbarOnlyAf(afStr) {
  try {
    return JSON.stringify((JSON.parse(afStr) ?? []).filter(f => (f.field ?? '').startsWith('toolbar_')));
  } catch { return '[]'; }
}

/**
 * 새로고침/리로드 시 URL 의 외부 주입 필터(뷰 디자이너 등) + UI 토글 필터 병합
 * - URL allFilter 중 UI filterFields 에 없는 alias 는 외부 컨텍스트로 보존
 * - UI filterFields 에 있는 alias 는 현재 filterValues 값을 우선 (사용자 변경 반영)
 */
function mergeExternalAndUiFilters(urlAfStr, filterValues, allFields) {
  const uiAliases = new Set(
    allFields
      .filter(f => ['s','t','w'].includes(f.grid_is_handle ?? ''))
      .map(f => f.alias_name)
  );
  let urlFilters = [];
  try { urlFilters = JSON.parse(urlAfStr ?? '[]') ?? []; } catch {}
  const external = urlFilters.filter(f => {
    const raw = f.field ?? '';
    const alias = raw.startsWith('toolbar_') ? raw.slice(8) : raw;
    return !uiAliases.has(alias);
  });
  const uiAfJson = buildAllFilter(filterValues, allFields.filter(f => ['s','t','w'].includes(f.grid_is_handle ?? '')));
  let uiFilters = [];
  try { uiFilters = JSON.parse(uiAfJson || '[]'); } catch {}
  return JSON.stringify([...external, ...uiFilters]);
}

/**
 * 컬럼 헤더 필터 문법 파싱
 * "관리"      → contains   "=관리" → eq      "관리%" → startsWith  "%관리" → endsWith
 * "<관리"     → lt         "<=관리"→ lte     ">관리" → gt          ">=관리"→ gte
 * "<>관리"    → neq        ",,"    → isNull  "a,,b,,c" → in
 */
function parseColFilter(alias, raw) {
  const v = (raw ?? '').trim();
  if (!v) return null;
  if (v === ',,') return { field: alias, operator: 'isNull', value: '' };
  if (v.includes(',,')) {
    return { field: alias, operator: 'in', value: v.split(',,').map(s => s.trim()) };
  }
  if (v.startsWith('<>')) return { field: alias, operator: 'neq',        value: v.slice(2) };
  if (v.startsWith('<=')) return { field: alias, operator: 'lte',        value: v.slice(2) };
  if (v.startsWith('>=')) return { field: alias, operator: 'gte',        value: v.slice(2) };
  if (v.startsWith('<'))  return { field: alias, operator: 'lt',         value: v.slice(1) };
  if (v.startsWith('>'))  return { field: alias, operator: 'gt',         value: v.slice(1) };
  if (v.startsWith('='))  return { field: alias, operator: 'eq',         value: v.slice(1) };
  if (v.startsWith('%'))  return { field: alias, operator: 'endsWith',   value: v.slice(1) };
  if (v.endsWith('%'))    return { field: alias, operator: 'startsWith', value: v.slice(0, -1) };
  // suffix 비교 연산자: "3>" → gt 3, "3>=" → gte 3, "3<" → lt 3, "3<=" → lte 3
  if (v.endsWith('>='))   return { field: alias, operator: 'gte', value: v.slice(0, -2) };
  if (v.endsWith('<='))   return { field: alias, operator: 'lte', value: v.slice(0, -2) };
  if (v.endsWith('<>'))   return { field: alias, operator: 'neq', value: v.slice(0, -2) };
  if (v.endsWith('>'))    return { field: alias, operator: 'gt',  value: v.slice(0, -1) };
  if (v.endsWith('<'))    return { field: alias, operator: 'lt',  value: v.slice(0, -1) };
  return { field: alias, operator: 'contains', value: v };
}

/**
 * 범용 데이터 그리드
 */
const DataGrid = forwardRef(function DataGrid({ gubun, user, menu, onToggleView, onModify,
                                   panelOpen, panelSize, onPanelSizeClick, onPanelClose, currentIdx, onOpenTab,
                                   parentIdx: parentIdxProp, parentGubun, onSqlBtn,
                                   devMode: devModeProp, noAutoOpen = false, noPanelBtn = false, onOnlyList, onClientMeta, onFieldsLoad, onListData, onAlwaysModify,
                                   deletedMode = false, onExitDeletedMode, isWidget = false }, ref) {
  // URL params (한 번만 파싱) + menu.add_url 파라미터 병합 (URL이 우선)
  const urlParams = useRef(null);
  if (!urlParams.current) {
    // isWidget(대시보드 위젯) 모드: 브라우저 URL 무시 + menu.add_url 만 사용
    const real = isWidget ? new URLSearchParams() : new URLSearchParams(window.location.search);
    const addUrl = (menu?.add_url ?? '').trim();
    if (addUrl) {
      const add = new URLSearchParams(addUrl.startsWith('&') ? addUrl.slice(1) : addUrl);
      for (const [k, v] of add) {
        if (!real.has(k)) real.set(k, v);
      }
    }
    urlParams.current = real;
  }
  const [colWidths, setColWidths] = useState({});
  const resizeDrag = useRef(null); // { alias, startX, startWidth }

  const [checkedRows, setCheckedRows] = useState(new Set());

  // 셀 선택
  const [selAnchor, setSelAnchor] = useState(null); // {ri, ci}
  const [selFocus,  setSelFocus]  = useState(null);
  // 셀 범위 붙여넣기 미리보기 — { [rowIdx]: { [alias]: value } }
  const [pastePreview, setPastePreview] = useState({});
  // 붙여넣기 확인 다이얼로그 상태
  const [pasteConfirm, setPasteConfirm] = useState(null); // { message, nRows, nCols }
  // 붙여넣기 오류 안내 (배너) — 선택 영역과 복사 크기 불일치 등
  const [pasteError, setPasteError] = useState('');
  const [copyDone,  setCopyDone]  = useState(false);
  const isDragging = useRef(false);

  // 컬럼 헤더 인라인 필터 — URL allFilter 의 non-toolbar_ 항목에서 복원
  const [colFilters, setColFilters] = useState(() =>
    parseUrlColFilters(urlParams.current.get('allFilter') ?? '[]')
  );
  const colFiltersRef = useRef({});
  colFiltersRef.current = colFilters;

  // 필터행 표시 여부 (localStorage 영속)
  const FILTER_ROW_KEY = 'mis_filter_row';
  const [showFilterRow, setShowFilterRow] = useState(() => localStorage.getItem(FILTER_ROW_KEY) !== 'N');
  const toggleFilterRow = () => { setShowFilterRow(v => { const next = !v; localStorage.setItem(FILTER_ROW_KEY, next ? 'Y' : 'N'); return next; }); };

  // 조회/수정 클릭 모드 (localStorage 영속)
  const CLICK_MODE_KEY = 'mis_click_mode';
  const [clickMode, setClickModeRaw] = useState(() => localStorage.getItem(CLICK_MODE_KEY) || 'view');
  const clickModeRef = useRef(clickMode);
  clickModeRef.current = clickMode;
  const setClickMode = (m) => { setClickModeRaw(m); localStorage.setItem(CLICK_MODE_KEY, m); };

  const [rows, setRows]       = useState([]);
  const [onlyListMode, setOnlyListMode] = useState(false);
  const [access, setAccess] = useState({ read: true, write: true, admin: false });
  // write 권한 없으면 수정 모드 비활성 — 조회 모드로 강제
  useEffect(() => { if (!access.write && clickMode === 'modify') setClickMode('view'); }, [access.write]);
  // simple_list 명시 OR (쓰기권한 없고 g01 공란 → 강제 simple_list) OR isPopup=Y (팝업은 체크박스 컬럼 불필요)
  const _urlIsPopup = urlParams.current?.get('isPopup') === 'Y';
  const isSimpleList = _urlIsPopup || menu?.g01 === 'simple_list' || (!access.write && !menu?.g01);
  const [fields, setFields]   = useState([]);
  const [total, setTotal]     = useState(0);
  const [page, setPage]       = useState(1);
  const [pageSize, setPageSize] = useState(() => {
    const ps = parseInt(urlParams.current.get('psize') ?? urlParams.current.get('pageSize') ?? '0', 10);
    if (ps > 0) return ps;
    // aggregate 모드에서는 psize 미지정 시 최대값 (모든 그룹/소계 정확히 계산 위해 — limit 으로 잘리면 일부 그룹만 표시됨)
    if (urlParams.current.get('aggregate')) return 99999;
    return PAGE_SIZE;
  });
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');

  // ── 인라인 편집 (list_edit=Y) ──
  const [editCell, setEditCell] = useState(null); // { ri, alias, saveAlias, displayAlias, idx, fkField }
  const [savedRowIdx, setSavedRowIdx] = useState(null);
  const [savedCell, setSavedCell]     = useState(null); // { idx, alias }
  const [editVal, setEditVal]   = useState('');
  const [editSaving, setEditSaving] = useState(false);



  // display 필드 → FK 필드 매핑 빌드 (fields 변경 시 한 번만)
  const fkMapRef = useRef({});
  useEffect(() => {
    const map = {}; // displayAlias → fkField
    for (let i = 0; i < fields.length; i++) {
      const f = fields[i];
      if (f.grid_list_edit === 'Y' && f.prime_key) {
        // 이 FK 필드의 다음(sort_order+1) display 필드 찾기
        // display 필드: 같은 db_table prefix가 아닌, sort_order 직전 필드
        // 실제로는 직전 필드가 display (sort_order 기준 i-1)
        if (i > 0) {
          const prev = fields[i - 1];
          const pw = parseInt(prev.col_width ?? '0', 10);
          if (pw > 0 && prev.db_table !== 'table_m') {
            map[prev.alias_name] = f;
          }
        }
      }
    }
    fkMapRef.current = map;
  }, [fields]);

  const startEdit = useCallback((ri, alias, val, rowIdx, row) => {
    const fkField = fkMapRef.current[alias];
    if (fkField) {
      // display 필드 클릭 → FK 필드의 값으로 편집
      setEditCell({ ri, alias, saveAlias: fkField.alias_name, displayAlias: alias, idx: rowIdx, fkField });
      setEditVal(row[fkField.alias_name] ?? '');
    } else {
      setEditCell({ ri, alias, saveAlias: alias, displayAlias: alias, idx: rowIdx, fkField: null });
      setEditVal(val ?? '');
    }
  }, []);

  const cancelEdit = useCallback(() => { setEditCell(null); setEditVal(''); }, []);

  // 편집 셀 이동 (Enter → 아래, Shift+Enter → 위)
  const moveEdit = useCallback((direction) => {
    if (!editCell) return;
    const nextRi = direction === 'down' ? editCell.ri + 1 : editCell.ri - 1;
    if (nextRi < 0 || nextRi >= rows.length) { setEditCell(null); setEditVal(''); return; }
    const nextRow = rows[nextRi];
    startEdit(nextRi, editCell.alias, nextRow?.[editCell.alias] ?? '', nextRow?.idx, nextRow);
  }, [editCell, rows, startEdit]);

  // 체크박스: 첫 클릭 → 활성화(선택 상태), 재클릭 → 토글 저장
  const [checkActive, setCheckActive] = useState(null); // { ri, alias }
  const checkActiveTimer = useRef(null);

  const handleCheckClick = useCallback(async (ri, alias, currentVal, rowIdx, isOverrideRO = false, field = null) => {
    // 체크 ON/OFF 값: schema_type='boolean'(tinyint 0/1) 컬럼은 1/0, 그 외 char(1)은 'Y'/''.
    // (it_soldout 같은 정수 체크박스에 'Y' 보내면 MySQL 이 0 으로 캐스팅돼 저장 안 되던 버그 대응)
    const _isBool = field?.schema_type === 'boolean';
    const ON  = _isBool ? '1' : 'Y';
    const OFF = _isBool ? '0' : '';
    const _isChecked = (v) => v === 'Y' || v === '1' || v === 1 || v === true;
    // override 모드 (read_only_cond 행 + max_length='X!') — confirm 후 즉시 저장
    if (isOverrideRO) {
      const checked = _isChecked(currentVal);
      const msg = checked ? '체크를 해제하시겠습니까?' : '체크를 하시겠습니까?';
      if (!window.confirm(msg)) return;
      const newVal = checked ? OFF : ON;
      try {
        const res = await api.save(gubun, { [alias]: newVal, _listEdit: true }, rowIdx);
        if (res._client_toast) showToast(res._client_toast);
        else showToast('저장되었습니다.', 'success');
        setRows(prev => prev.map((r, i) => i === ri ? { ...r, [alias]: newVal } : r));
        const ck = { idx: rowIdx, alias };
        setSavedCell(ck);
        setTimeout(() => setSavedCell(prev => prev?.idx === ck.idx && prev?.alias === ck.alias ? null : prev), 1400);
      } catch (e) {
        showToast(e.message || '저장 실패', 'error');
      }
      return;
    }

    // 이미 활성화된 셀 → 토글 저장
    if (checkActive && checkActive.ri === ri && checkActive.alias === alias) {
      const newVal = _isChecked(currentVal) ? OFF : ON;
      setCheckActive(null);
      if (checkActiveTimer.current) clearTimeout(checkActiveTimer.current);
      try {
        const res = await api.save(gubun, { [alias]: newVal, _listEdit: true }, rowIdx);
        if (res._client_toast) showToast(res._client_toast);
        setRows(prev => prev.map((r, i) => i === ri ? { ...r, [alias]: newVal } : r));
        // 셀 체크마크
        const ck = { idx: rowIdx, alias };
        setSavedCell(ck);
        setTimeout(() => setSavedCell(prev => prev?.idx === ck.idx && prev?.alias === ck.alias ? null : prev), 1400);
      } catch (e) {
        showToast(e.message || '저장 실패');
      }
      return;
    }
    // 첫 클릭 → 활성화 (3초 후 자동 해제)
    setCheckActive({ ri, alias });
    if (checkActiveTimer.current) clearTimeout(checkActiveTimer.current);
    checkActiveTimer.current = setTimeout(() => setCheckActive(null), 3000);
  }, [gubun, checkActive]);

  const saveEdit = useCallback(async (direction, overrideValue) => {
    if (!editCell) return;
    const effectiveVal = overrideValue !== undefined && overrideValue !== null ? overrideValue : editVal;
    const prevVal = editCell.fkField
      ? rows[editCell.ri]?.[editCell.saveAlias]
      : rows[editCell.ri]?.[editCell.alias];
    if (String(effectiveVal) === String(prevVal ?? '')) {
      // 값 변경 없어도 방향키로 이동
      if (direction === 'down' || direction === 'up') {
        moveEdit(direction);
      } else {
        setEditCell(null);
        setEditVal('');
      }
      return;
    }
    setEditSaving(true);
    try {
      const saveBody = { [editCell.saveAlias]: effectiveVal, _listEdit: true };
      let res = await api.save(gubun, saveBody, editCell.idx, devModeRef.current);

      if (res._confirm) {
        setEditSaving(false);
        if (!window.confirm(res._confirm)) return;
        setEditSaving(true);
        res = await api.save(gubun, { ...saveBody, _confirmed: true }, editCell.idx, devModeRef.current);
      }

      if (res._client_toast) showToast(res._client_toast);

      // 개발자모드: 저장쿼리 표시
      if (res._sql || res._execSql) {
        setDevSql({ sql: res._sql, count_sql: null, bindings: res._bindings ?? [], error: res._sql_error ?? null, execSql: res._execSql ?? null });
        setShowSqlBtn(true);
        sqlBtnDuration.current = 8000;
      }

      // 저장 완료 행 깜빡임
      const savedIdx = editCell.idx;
      setSavedRowIdx(savedIdx);
      setTimeout(() => setSavedRowIdx(prev => prev === savedIdx ? null : prev), 1200);

      // 셀 체크마크 표시
      const cellKey = { idx: savedIdx, alias: editCell.alias };
      setSavedCell(cellKey);
      setTimeout(() => setSavedCell(prev => prev?.idx === cellKey.idx && prev?.alias === cellKey.alias ? null : prev), 1400);

      // 서버 훅이 edited row 이외의 값도 변경했음을 알리면 해당 행 재조회 (ex: 267 의 alias_name 자동 재생성)
      if (res._listEditReload) {
        try {
          const rowData = await api.view(gubun, editCell.idx);
          if (rowData?.data) {
            const savedRi = editCell.ri;
            setRows(prev => prev.map((r, i) => i === savedRi ? { ...r, ...rowData.data } : r));
          }
        } catch {}
      }

      // 방향 이동 (Enter/Shift+Enter)
      if (direction === 'down' || direction === 'up') {
        const nextRi = direction === 'down' ? editCell.ri + 1 : editCell.ri - 1;
        // 서버 훅이 값을 재계산한 경우(_listEditReload) 위의 refetch 가 이미 반영했으므로
        // 사용자가 타이핑한 값으로 덮어쓰지 않음 (alias_name 을 ''로 비웠을 때 regen 된 값이 유지되도록)
        if (!res._listEditReload) {
          setRows(prev => prev.map((r, i) => i === editCell.ri ? { ...r, [editCell.saveAlias]: effectiveVal } : r));
        }
        if (nextRi >= 0 && nextRi < rows.length) {
          const nextRow = rows[nextRi];
          startEdit(nextRi, editCell.alias, nextRow?.[editCell.alias] ?? '', nextRow?.idx, nextRow);
        } else {
          setEditCell(null);
          setEditVal('');
        }
        setEditSaving(false);
        return;
      }

      // select/FK: 해당 행 1건만 서버에서 재조회. 모든 케이스에서 input 닫기 (blur/Enter 모두).
      if (editCell.fkField) {
        setEditCell(null);
        setEditVal('');
        try {
          const rowData = await api.view(gubun, editCell.idx);
          if (rowData.data) {
            setRows(prev => prev.map((r, i) => i === editCell.ri ? { ...r, ...rowData.data } : r));
          }
        } catch {}
      } else {
        setRows(prev => prev.map((r, i) => i === editCell.ri ? { ...r, [editCell.saveAlias]: effectiveVal } : r));
        setEditCell(null);
        setEditVal('');
      }
    } catch (e) {
      showToast(e.message || '저장 실패');
    } finally {
      setEditSaving(false);
    }
  }, [editCell, editVal, gubun]);

  // URL orderby 정규화해서 초기값으로
  const [orderby, setOrderby] = useState(() =>
    normalizeOrderby(urlParams.current.get('orderby') ?? ''));

  // URL allFilter → filterValues 초기값
  const [filterValues, setFilterValues] = useState(() =>
    parseUrlFilter(urlParams.current.get('allFilter') ?? '[]'));
  // 항상 최신 filterValues 참조 (blur 핸들러에서 stale 방지)
  const filterValuesRef = useRef({});
  filterValuesRef.current = filterValues;

  const [dynamicOptions, setDynamicOptions] = useState({});

  // recently: URL > g03 기본값. 버튼은 항상 활성
  const [recently, setRecently] = useState(() => {
    const urlR = urlParams.current.get('recently');
    if (urlR !== null) return urlR === 'Y';
    return menu?.g03 !== 'Y'; // g03=Y → 기본 OFF, 아니면 기본 ON
  });

  // 개발자 모드 (prop 우선, 없으면 localStorage)
  const devMode = devModeProp ?? (localStorage.getItem('mis_dev_mode') === '1');
  const devModeRef = useRef(devMode);
  devModeRef.current = devMode;

  const [devSql,       setDevSql]       = useState(null); // { sql, count_sql, bindings }
  const [showSqlBtn,   setShowSqlBtn]   = useState(false);
  const [sqlModalOpen, setSqlModalOpen] = useState(false);
  // aggregate 클릭 시 표시할 팝업: { rows, title } — 레거시(현재 미사용)
  const [aggPopup, setAggPopup] = useState(null);
  // (이전 embedPopup 은 mis:openTab 으로 대체됨 — 더 이상 state 불필요)
  const isFirstLoad       = useRef(true);
  const sqlBtnDuration    = useRef(8000);
  const onToggleViewRef      = useRef(onToggleView);
  onToggleViewRef.current    = onToggleView;
  const onPanelSizeClickRef  = useRef(onPanelSizeClick);
  onPanelSizeClickRef.current = onPanelSizeClick;
  const panelOpenRef         = useRef(panelOpen);
  panelOpenRef.current       = panelOpen;
  // 페이지 이동으로 인한 로드 여부 (load 완료 후 첫 행 자동선택 트리거)
  const pageChangePending    = useRef(false);

  // 컬럼 필터 blur 검색용 — native focusout으로 처리
  const filterRowRef         = useRef(null);
  const colFilterSearchRef   = useRef(null); // 매 렌더마다 최신 함수로 갱신

  // 컨테이너 폭 추적 (좁은 화면 대응)
  const gridContainerRef = useRef(null);
  const tableScrollRef = useRef(null);
  const serverViewPrefRef = useRef(null); // 서버 _client_viewPref
  const disableSortRef    = useRef(false); // 서버 _client_disableSort (pageLoad 에서 set)
  const [gridW, setGridW] = useState(800);
  const [tableW, setTableW] = useState(0); // 테이블 가용 폭 (스크롤바 제외)
  useEffect(() => {
    const el = gridContainerRef.current;
    if (!el) return;
    setGridW(el.offsetWidth);
    const obs = new ResizeObserver(entries => {
      for (const e of entries) setGridW(e.contentRect.width);
    });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);
  useEffect(() => {
    const el = tableScrollRef.current;
    if (!el) return;
    const measure = () => setTableW(el.clientWidth);
    measure();
    const obs = new ResizeObserver(measure);
    obs.observe(el);
    return () => obs.disconnect();
  }, []);
  const autoScaleRef = useRef(1); // resize 핸들러용

  // 모바일 여부 (767px 이하)
  const [isMobile, setIsMobile] = useState(() => window.innerWidth <= 767);
  useEffect(() => {
    const handler = () => setIsMobile(window.innerWidth <= 767);
    window.addEventListener('resize', handler);
    return () => window.removeEventListener('resize', handler);
  }, []);

  // parent_idx: prop 우선, 없으면 URL 파라미터
  const urlParentIdx = urlParams.current.get('parent_idx') ?? '';
  const effectiveParentIdx = parentIdxProp !== undefined ? String(parentIdxProp) : urlParentIdx;
  // ref로 항상 최신값 유지 → load 클로저 내에서 stale 값 없음
  const parentIdxRef = useRef(effectiveParentIdx);
  parentIdxRef.current = effectiveParentIdx;

  const load = useCallback(async (pg = 1, ob = '', af = '[]', rec, ps) => {
    setLoading(true);
    setError('');
    const effectivePs = ps ?? pageSize;
    // 컬럼 헤더 필터 병합
    const colParts = Object.entries(colFiltersRef.current)
      .map(([alias, raw]) => parseColFilter(alias, raw)).filter(Boolean);
    const finalAf = colParts.length > 0
      ? JSON.stringify([...(JSON.parse(af || '[]')), ...colParts])
      : af;
    try {
      const listParams = {
        page: pg, pageSize: effectivePs, orderby: ob, allFilter: finalAf,
        recently: rec ? 'Y' : 'N',
      };
      if (deletedMode) listParams.deleted = '1';
      // 팝업 플래그: iframe 내에서 URL의 isPopup=Y를 그대로 전달
      const _urlSearch = new URLSearchParams(window.location.search);
      const _popupFlag = _urlSearch.get('isPopup');
      if (_popupFlag === 'Y') listParams.isPopup = 'Y';
      // aggregate=auto: 부분합/총합 표시 (URL에서 전달)
      const _agg = _urlSearch.get('aggregate');
      if (_agg) listParams.aggregate = _agg;
      // 사용자 정의 버튼 action 전달
      const capturedAction = window.__mis_custom_action || '';
      if (capturedAction) {
        listParams.customAction = capturedAction;
        window.__mis_custom_action = ''; // 전달 후 즉시 리셋
      }
      // 사용자 정의 버튼 payload (예: 체크된 행 idx 목록)
      const capturedPayload = window.__mis_custom_action_payload;
      if (capturedPayload !== undefined && capturedPayload !== null) {
        listParams.customActionPayload = JSON.stringify(capturedPayload);
        window.__mis_custom_action_payload = null;
      }
      if (parentIdxRef.current !== '') listParams.parent_idx = parentIdxRef.current;
      if (parentGubun) listParams.parent_gubun = String(parentGubun);
      if (devModeRef.current) listParams.dev_mode = '1';
      if (isFirstLoad.current) listParams.first_load = '1';
      // 알려지지 않은 URL 파라미터(pid, sid 등 사용자정의)도 API 로 전달 — 사용자로직 requestVB() 가 읽음
      const _knownUrlKeys = new Set(['gubun','idx','actionFlag','isMenuIn','isPopup','isPrint','isAddURL','recently','recently_view','orderby','page','pageSize','psize','allFilter','parent_idx','tabid','aggregate','_chart','_chartFull','dev_mode','first_load','deleted']);
      for (const [k, v] of _urlSearch) {
        if (!_knownUrlKeys.has(k) && !(k in listParams)) listParams[k] = v;
      }
      const data = await api.list(gubun, listParams);
      if (data._sql) {
        sqlBtnDuration.current = isFirstLoad.current ? 8000 : 5000;
        isFirstLoad.current = false;
        setDevSql({ sql: data._sql, count_sql: data._count_sql, bindings: data._bindings, error: data._sql_error ?? null, execSql: data._execSql ?? null });
        setShowSqlBtn(true);
      }
      // 서버 훅 _client_confirm — 커스텀 액션 1차 호출 시 확인 → _confirmed 붙여 재호출
      if (data._client_confirm && capturedAction) {
        setLoading(false);
        if (window.confirm(data._client_confirm)) {
          window.__mis_custom_action = capturedAction;
          window.__mis_custom_action_payload = { ...(capturedPayload || {}), _confirmed: true };
          return load(pg, ob, af, rec, ps);
        }
        return; // 취소
      }
      // 서버 훅에서 전달한 클라이언트 메시지 처리
      if (data._client_alert) alert(data._client_alert);
      if (data._client_toast) showToast(data._client_toast);
      if (data._client_openTab) {
        const t = data._client_openTab;
        window.dispatchEvent(new CustomEvent('mis:openTab', { detail: { gubun: t.gubun, label: t.label ?? '', idx: t.idx ?? 0, linkVal: t.linkVal ?? t.idx ?? 0, openFull: !!t.openFull } }));
      }
      if (data._client_redirect) {
        const t = data._client_redirect;
        // allFilter 지원: 객체/문자열 모두 허용 (PHP 에서 array 또는 JSON string 전달)
        let addUrl = t.addUrl ?? null;
        if (t.allFilter != null) {
          const af = typeof t.allFilter === 'string' ? t.allFilter : JSON.stringify(t.allFilter);
          addUrl = (addUrl ? addUrl + '&' : '') + 'allFilter=' + encodeURIComponent(af);
        }
        window.dispatchEvent(new CustomEvent('mis:redirectTab', { detail: { gubun: t.gubun, label: t.label ?? '', idx: t.idx ?? null, linkVal: t.linkVal ?? null, addUrl } }));
        return;
      }
      const newRows   = data.data   ?? [];
      const newFields = data.fields ?? [];
      { const v = !!data._onlyList; setOnlyListMode(v); onOnlyList?.(v); }
      if (data._client_viewPref) serverViewPrefRef.current = data._client_viewPref;
      disableSortRef.current = !!data._client_disableSort;
      // 서버 훅 _client_alwaysModify — list 셀 클릭 시 view 가 아닌 modify 로 진입
      onAlwaysModify?.(!!data._client_alwaysModify);
      if (data._access) setAccess(data._access);
      // 매 응답마다 메타 통지 — 백업↔정상 전환 시 css/badge 가 잔존하지 않도록 항상 호출 (없으면 null 로 클리어)
      onClientMeta?.({
        css:             data._client_css ?? null,
        js:              data._client_js,
        buttonText:      data._client_buttonText,
        buttons:         data._client_buttons,
        access:          data._access,
        isBackupView:    !!data._isBackupView,
        backupBadgeText: data._backupBadgeText ?? '(백업)',
      });
      setRows(newRows);
      setFields(newFields);
      onFieldsLoad?.(newFields);
      onListData?.({ rows: newRows, fields: newFields, orderby: ob, aggregate: urlParams.current?.get('aggregate') ?? '' });
      setTotal(data.total ?? 0);
      setCheckedRows(new Set());
      setPage(data.page   ?? pg);

      // 페이지 이동 + 패널 열려있음 → 첫 행 자동선택
      if (pageChangePending.current && newRows.length > 0 && newFields.length > 0) {
        pageChangePending.current = false;
        const _pk0cw        = parseInt(newFields[0]?.col_width ?? '0', 10);
        const _pkAlias      = newFields[0]?.alias_name ?? 'idx';
        const _usePkForLink = _pk0cw !== -1 && _pk0cw !== -2;
        // URL idx lookup 후보 — PK 숨김 마커(-1, -2) 만 skip (col_width=0 은 폼 표시용 = lookup 가능)
        const _lookupField  = newFields.find(f => { const w = parseInt(f.col_width ?? '0', 10); return w !== -1 && w !== -2; });
        const _firstAlias   = _lookupField?.alias_name ?? '';
        const firstRow      = newRows[0];
        const rowPk      = _usePkForLink ? (firstRow[_pkAlias] ?? firstRow.idx) : (firstRow[_firstAlias] ?? firstRow[_pkAlias] ?? firstRow.idx);
        const rowLinkVal = _usePkForLink ? (firstRow[_pkAlias] ?? firstRow.idx) : (firstRow[_firstAlias] ?? firstRow[_pkAlias] ?? firstRow.idx);
        onToggleViewRef.current?.(rowPk, rowLinkVal, true);
      } else {
        pageChangePending.current = false;
      }
      // recently=OFF이고 orderby 없으면 fields의 기본 정렬을 클라이언트 state에 반영
      // recently=ON이면 서버가 wdate DESC 사용 → 기본 정렬 표시 안 함
      if (ob === '' && !rec) {
        const defaultOb = buildDefaultOrderby(newFields);
        if (defaultOb) setOrderby(defaultOb);
      }
      // URL 디코딩: 인코딩된 주소를 사람이 읽을 수 있게 교체 (위젯 모드는 URL 안 만짐)
      if (!isWidget) {
        const decoded = decodeURIComponent(window.location.search);
        if (decoded !== window.location.search) {
          history.replaceState(null, '', window.location.pathname + decoded);
        }
      }
    } catch (e) {
      if (e._sqlData) {
        setDevSql(e._sqlData);
        setShowSqlBtn(true);
      }
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [gubun, pageSize]);

  // 인라인 편집 저장 후 재조회용 ref
  const loadRef = useRef(null);
  loadRef.current = () => {
    const af = buildAllFilter(filterValues, fields.filter(f => ['s','t','w'].includes(f.grid_is_handle ?? '')));
    load(page, orderby, af, recently);
  };

  // SQL 버튼 상태 변경 시 부모에게 알림 + 자동 숨김 (에러 시 유지)
  useEffect(() => {
    onSqlBtn?.(showSqlBtn, () => setSqlModalOpen(true), !!(devSql?.error));
    if (!showSqlBtn) return;
    if (devSql?.error) return; // 에러 시 자동 숨김 안 함
    const t = setTimeout(() => setShowSqlBtn(false), sqlBtnDuration.current);
    return () => clearTimeout(t);
  }, [showSqlBtn, onSqlBtn]);

  // 언마운트 시 버튼 숨김 알림
  useEffect(() => () => { onSqlBtn?.(false, null); }, [onSqlBtn]);

  // 부모에 메서드 노출
  // 나의백업에 추가 — 현재 필터/정렬 그대로 server 에 보내 JSON 백업 + mis_backup_list 기록
  // parent: { parent_gubun, parent_idx } — 자식 프로그램에서 호출 시
  async function handleBackupToMyList(parent = {}) {
    try {
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      const opts = {
        allFilter: af,
        orderby:   orderby ?? '',
        recently:  recently ? 'Y' : 'N',
      };
      if (parent.parent_gubun)   opts.parent_gubun = parent.parent_gubun;
      if (parent.parent_idx)     opts.parent_idx   = parent.parent_idx;
      const res = await api.backupList(gubun, opts);
      if (res?.success) {
        showToast(res.message ?? '백업 추가됨');
      } else {
        alert('백업 실패: ' + (res?.message ?? '알 수 없는 오류'));
      }
    } catch (e) {
      alert('백업 실패: ' + (e?.message ?? e));
    }
  }

  // ─────────────────────────────────────────────────────────────────────
  // 전역 리로드 이벤트 (mis:reloadGrid) — data-mis-action treat 응답 등에서 발사.
  //   key remount 대신 데이터만 refetch → 스크롤/포커스 보존 (트리 [↑][↓] 이동 시 화면 정지).
  //   reload 전후로 tableScrollRef.scrollTop 캡쳐/복원 → 데이터 길이 변화에도 위치 유지
  // ─────────────────────────────────────────────────────────────────────
  const reloadStateRef = useRef({});
  reloadStateRef.current = { page, orderby, recently, filterValues, fields, load };
  useEffect(() => {
    const h = async () => {
      const s = reloadStateRef.current;
      if (!s.load) return;
      const savedTop = tableScrollRef.current?.scrollTop ?? 0;
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', s.filterValues, s.fields);
      await s.load(s.page, s.orderby, af, s.recently);
      // 새 rows 가 DOM 에 반영된 다음 프레임에 scrollTop 복원
      requestAnimationFrame(() => {
        if (tableScrollRef.current) tableScrollRef.current.scrollTop = savedTop;
      });
    };
    window.addEventListener('mis:reloadGrid', h);
    return () => window.removeEventListener('mis:reloadGrid', h);
  }, []);

  useImperativeHandle(ref, () => ({
    downloadExcel:   handleExcel,
    downloadXlsFast: handleXlsFast,
    print:           handlePrint,
    backupToMyList:  handleBackupToMyList,
    getCurrentUrl: buildCurrentUrl,
    clearDevSql:   () => setDevSql(null),
    reload: () => {
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(page, orderby, af, recently);
    },
    reset: () => {
      // '초기화' = 완전 비움이 아니라 프로그램 기본 상태(menu.add_url)로 복귀
      // → add_url 에 기본 allFilter/orderby/recently 가 있으면 그대로 다시 적용 (최초 진입과 동일)
      const addUrl = (menu?.add_url ?? '').trim();
      const dft = new URLSearchParams(addUrl.startsWith('&') ? addUrl.slice(1) : addUrl);
      const dftAf   = dft.get('allFilter') ?? '[]';
      const dftOb   = normalizeOrderby(dft.get('orderby') ?? '');
      const dftRec  = dft.has('recently') ? dft.get('recently') === 'Y' : (menu?.g03 !== 'Y');
      const dftPs   = Number(dft.get('psize') ?? dft.get('pageSize')) || PAGE_SIZE;
      const dftColF = parseUrlColFilters(dftAf);
      setFilterValues({});
      setColFilters(dftColF);
      colFiltersRef.current = dftColF;
      setOrderby(dftOb);
      setRecently(dftRec);
      setPageSize(dftPs);
      const p = new URLSearchParams(window.location.search);
      p.delete('allFilter');
      p.delete('orderby');
      p.delete('recently');
      p.delete('psize');
      p.delete('pageSize');
      p.delete('colF');
      // 부분합/차트 모드도 함께 해제 → 일반 리스트로 복귀
      p.delete('aggregate');
      p.delete('_chart');
      p.delete('_chartGroup');
      p.delete('_chartValue');
      p.delete('_chartSort');
      p.delete('_chartFull');
      // menu.add_url 기본 파라미터를 URL 에 복원 (최초 진입 상태와 동일하게)
      for (const [k, v] of dft) {
        if (!p.has(k)) p.set(k, v);
      }
      if (!isWidget) history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
      load(1, dftOb, toolbarOnlyAf(dftAf), dftRec, dftPs);
    },
    bulkDelete: handleBulkDelete,
    getCheckedCount: () => checkedRows.size,
    getCheckedIdxs: () => [...checkedRows],
    applySort: (ob, rec) => {
      setOrderby(ob);
      setRecently(rec);
      if (!isWidget) {
        const p = new URLSearchParams(window.location.search);
        if (ob) p.set('orderby', ob); else p.delete('orderby');
        p.set('recently', rec ? 'Y' : 'N');
        history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
      }
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(1, ob, af, rec);
    },
  })); // deps 없음 — 최신 클로저는 매 렌더마다 갱신

  // 최초 로드: toolbar_ 필터만 af로 전달 (컬럼 필터는 colFiltersRef에서 load 내부 병합)
  useEffect(() => {
    const urlAF = urlParams.current.get('allFilter') ?? '[]';
    const urlOb = normalizeOrderby(urlParams.current.get('orderby') ?? '');
    load(1, urlOb, toolbarOnlyAf(urlAF), recently);
  }, [gubun]);

  // parentIdx prop 변경 시 재조회 (초기 마운트 제외)
  const prevParentIdxProp = useRef(parentIdxProp);
  useEffect(() => {
    if (prevParentIdxProp.current === parentIdxProp) return;
    prevParentIdxProp.current = parentIdxProp;
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    load(1, orderby, af, recently);
  }, [parentIdxProp]); // eslint-disable-line react-hooks/exhaustive-deps

  // s 타입 필터는 hover/focus 시점에 lazy-load (실제 데이터 distinct 값)
  // - items 는 입력/수정 폼의 '선택목록' 이며 상단 필터와 무관
  // - 초기 로드 시 일괄 조회하지 않고 사용자가 펼치려 할 때 한 번만 조회
  const loadedFilterAliases = useRef(new Set());
  useEffect(() => {
    loadedFilterAliases.current = new Set(); // fields/gubun 바뀌면 다시 로드 가능
  }, [fields, gubun]);
  const loadFilterItems = useCallback((alias) => {
    if (!alias || loadedFilterAliases.current.has(alias)) return;
    loadedFilterAliases.current.add(alias);
    api.filterItems(gubun, alias)
      .then(data => setDynamicOptions(prev => ({ ...prev, [alias]: data.data ?? [] })))
      .catch(() => { loadedFilterAliases.current.delete(alias); /* 실패 시 재시도 허용 */ });
  }, [gubun]);

  // 드래그 선택 중 mouseup 처리 (document 레벨)
  useEffect(() => {
    const stop = () => { isDragging.current = false; };
    document.addEventListener('mouseup', stop);
    return () => document.removeEventListener('mouseup', stop);
  }, []);

  // 최초 로드 1회만 자동열기 허용
  const autoOpenDone = useRef(false);

  // 로드 완료 시 (loading: true→false) 첫 번째 행 자동 내용보기 — 최초 1회만
  useEffect(() => {
    if (noAutoOpen || urlParams.current?.get('noAutoOpen') === '1') return; // child 또는 URL 지정
    if (urlParams.current?.get('aggregate')) return; // aggregate 모드: 자동열기 비활성
    // URL 에 allFilter 가 있으면 자동열기 비활성 (사용자가 필터로 진입한 컨텍스트 → 목록 먼저 보여주기)
    if ((urlParams.current?.get('allFilter') ?? '').trim() !== '' && (urlParams.current?.get('allFilter') ?? '') !== '[]') return;
    // 조회설정: 서버(B) > 프로그램별(A) > 전역 순서로 판단
    // 프로그램별 키는 EFFECTIVE real_pid 기준 — MisJoin 메뉴(6123 등)는 부모(6083) 설정을 공유
    const serverPref = serverViewPrefRef.current;
    const globalPref = localStorage.getItem('mis_view_pref') || 'auto';
    const effectivePid = menu?.mis_join_pid || menu?.real_pid || `g${gubun}`;
    const perProgPref = localStorage.getItem(`mis_view_pref_${effectivePid}`);
    // '개별' 모드에서 프로그램별 값이 없으면 기본 '목록만 먼저'(list)
    const effectivePref = serverPref || (globalPref === 'custom' ? (perProgPref || 'list') : globalPref);
    if (effectivePref === 'list') return;
    if (window.innerWidth <= 767) return;               // 모바일에서는 자동열기 비활성
    if (autoOpenDone.current) return;                   // 필터/페이지 이동 후 재조회 시 비활성
    if (panelOpen) { autoOpenDone.current = true; return; } // 패널이 이미 열려있으면 건너뜀 (저장 후 리로드 등)
    if (loading) return;                                // 아직 로딩 중이면 무시
    if (rows.length === 0 || fields.length === 0) return;
    if (!onToggleViewRef.current) return;
    autoOpenDone.current = true;
    const _pk0cw        = parseInt(fields[0]?.col_width ?? '0', 10);
    const _pkAlias      = fields[0]?.alias_name ?? 'idx';
    const _usePkForLink = _pk0cw !== -1 && _pk0cw !== -2;
    const _listFields   = fields.filter(f => { const w = parseInt(f.col_width ?? '0', 10); return w !== 0 && w !== -1 && w !== -2; });
    const _firstAlias   = _listFields[0]?.alias_name ?? '';
    const firstRow      = rows[0];
    const rowPk      = _usePkForLink ? (firstRow[_pkAlias] ?? firstRow.idx) : (firstRow[_firstAlias] ?? firstRow[_pkAlias] ?? firstRow.idx);
    const rowLinkVal = _usePkForLink ? (firstRow[_pkAlias] ?? firstRow.idx) : (firstRow[_firstAlias] ?? firstRow[_pkAlias] ?? firstRow.idx);
    if (clickModeRef.current === 'modify') {
      // PK 가 idx 가 아닌 테이블(예: g5_shop_item.it_id) 에서는 firstRow.idx 가 undefined → rowPk 사용
      onModify?.(rowPk, getLinkVal(firstRow));
    } else {
      // panelSize=4(전체화면)이면 3으로 축소
      if (panelSize === 4 && onPanelSizeClickRef.current) {
        onPanelSizeClickRef.current(3, rowPk, rowLinkVal);
      }
      onToggleViewRef.current(rowPk, rowLinkVal, true); // forceOpen=true: 항상 열기 (토글 방지)
    }
  }, [loading]); // eslint-disable-line react-hooks/exhaustive-deps


  async function handleDelete(idx) {
    if (!window.confirm('삭제하시겠습니까?')) return;
    try {
      await api.delete(gubun, idx);
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(page, orderby, af, recently);
    } catch (e) {
      alert(e.message);
    }
  }

  async function handleBulkDelete() {
    if (checkedRows.size === 0) return;
    if (!window.confirm(`${checkedRows.size}건을 삭제하시겠습니까?`)) return;
    try {
      const res = await api.bulkDelete(gubun, [...checkedRows]);
      setCheckedRows(new Set());
      const msg = res.message || `${res.deleted ?? checkedRows.size}건 삭제 완료`;
      showToast(res._client_toast || msg);
      if (res._client_alert) alert(res._client_alert);
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(page, orderby, af, recently);
    } catch (e) {
      showToast(e.message || '삭제 실패');
    }
  }

  async function handleBulkRestore() {
    if (checkedRows.size === 0) return;
    if (!window.confirm(`${checkedRows.size}건을 복원하시겠습니까?`)) return;
    try {
      const res = await api.bulkRestore(gubun, [...checkedRows]);
      setCheckedRows(new Set());
      showToast(res.message || `${res.affected ?? 0}건 복원 완료`);
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(page, orderby, af, recently);
    } catch (e) {
      showToast(e.message || '복원 실패', 'error');
    }
  }

  async function handleBulkPermanentDelete() {
    if (checkedRows.size === 0) return;
    if (!window.confirm(`${checkedRows.size}건을 완전삭제합니다. 복구할 수 없습니다. 계속하시겠습니까?`)) return;
    try {
      const res = await api.bulkPermanentDelete(gubun, [...checkedRows]);
      setCheckedRows(new Set());
      showToast(res.message || `${res.affected ?? 0}건 완전삭제 완료`);
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      load(page, orderby, af, recently);
    } catch (e) {
      showToast(e.message || '완전삭제 실패', 'error');
    }
  }

  function handleCheckRow(idx, e) {
    setCheckedRows(prev => {
      const next = new Set(prev);
      if (e?.shiftKey && lastCheckedRef.current != null) {
        // Shift+클릭: 범위 선택
        const idxList = rows.map(r => r.idx ?? r[pkAlias]);
        const from = idxList.indexOf(lastCheckedRef.current);
        const to = idxList.indexOf(idx);
        const [start, end] = from < to ? [from, to] : [to, from];
        for (let i = start; i <= end; i++) {
          next.add(idxList[i]);
        }
      } else {
        if (next.has(idx)) next.delete(idx); else next.add(idx);
      }
      lastCheckedRef.current = idx;
      return next;
    });
  }

  function handleCheckAll() {
    if (checkedRows.size === rows.length) {
      setCheckedRows(new Set());
    } else {
      setCheckedRows(new Set(rows.map(r => r.idx ?? r[pkAlias])));
    }
  }
  const lastCheckedRef = useRef(null);

  function handleSort(alias, e) {
    // 팝업 모드 (뷰 디자이너 등) 에서는 헤더 클릭 정렬 비활성 — 드래그 리사이즈 UX 우선
    if (_urlIsPopup) return;
    // 사용자로직 (programs/*.php pageLoad 에서 $GLOBALS['_client_disableSort']=true) 으로 정렬 비활성 가능
    if (disableSortRef.current) return;
    const af  = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    const newR = false; // 컬럼 클릭 시 최근순 자동 OFF
    setRecently(newR);
    if (e.ctrlKey || e.metaKey) {
      // Ctrl+클릭: 다중 정렬 추가/토글 (현재 orderby 기준)
      const parts = orderby ? orderby.split(',').filter(Boolean) : [];
      const hasAsc  = parts.includes(alias);
      const hasDesc = parts.includes(`-${alias}`);
      let newParts;
      if (hasAsc)       newParts = parts.map(p => p === alias ? `-${alias}` : p);
      else if (hasDesc) newParts = parts.filter(p => p !== `-${alias}`);
      else              newParts = [...parts, alias];
      const newOb = newParts.join(',');
      setOrderby(newOb);
      load(1, newOb, af, newR);
    } else {
      // 일반 클릭: 단일 정렬 (ASC→DESC→해제)
      const newOb = orderby === alias ? `-${alias}` : (orderby === `-${alias}` ? '' : alias);
      setOrderby(newOb);
      load(1, newOb, af, newR);
    }
  }

  function handlePage(pg) {
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    if (panelOpenRef.current) pageChangePending.current = true;
    load(pg, orderby, af, recently);
  }

  // ── 셀 선택 ────────────────────────────────────────────────────────────────

  function getSelRange() {
    if (!selAnchor || !selFocus) return null;
    return {
      r1: Math.min(selAnchor.ri, selFocus.ri), r2: Math.max(selAnchor.ri, selFocus.ri),
      c1: Math.min(selAnchor.ci, selFocus.ci), c2: Math.max(selAnchor.ci, selFocus.ci),
    };
  }

  function isCellSelected(ri, ci) {
    const r = getSelRange();
    return r ? ri >= r.r1 && ri <= r.r2 && ci >= r.c1 && ci <= r.c2 : false;
  }

  function handleCellMouseDown(e, ri, ci) {
    if (e.button !== 0) return;
    // cell-html 안의 data-mis-action / data-mis-iframe / data-opentab 버튼은 셀 선택을 트리거하지 않음
    // (mousedown 시 셀 선택 setState → 재렌더로 dangerouslySetInnerHTML 의 버튼 DOM 이 detach 되어
    //  뒤이은 click 이 document capture 핸들러에 도달 못하는 문제 방지)
    if (e.target.closest && (e.target.closest('[data-mis-action]') || e.target.closest('[data-mis-iframe]') || e.target.closest('[data-opentab]'))) {
      return;
    }
    // data-mis-nolink — 셀이 PK 처럼 동작 (뷰 진입) 하는 것을 차단. 셀 선택은 허용.
    if (e.target.closest && e.target.closest('[data-mis-nolink]')) {
      setSelAnchor({ ri, ci });
      setSelFocus({ ri, ci });
      e.stopPropagation();
      return;
    }
    if (e.shiftKey && selAnchor) {
      setSelFocus({ ri, ci });
    } else {
      setSelAnchor({ ri, ci });
      setSelFocus({ ri, ci });
    }
    isDragging.current = true;
    if (!editCell) requestAnimationFrame(() => tableScrollRef.current?.focus());
  }

  function handleCellMouseEnter(ri, ci) {
    if (isDragging.current) setSelFocus({ ri, ci });
  }

  function handleCopy() {
    const r = getSelRange();
    if (!r) return;
    const textLines = [];
    const htmlRows  = [];

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    // 전체 선택(Ctrl+A)인 경우 헤더 행 포함
    const isAll = r.r1 === 0 && r.r2 === rows.length - 1
               && r.c1 === 0 && r.c2 === listFields.length - 1;
    if (isAll) {
      const headers = listFields.slice(r.c1, r.c2 + 1).map(f => {
        const t = parseColTitle(f.col_title ?? f.alias_name ?? '');
        return t.r2 ?? t.r1 ?? f.alias_name ?? '';
      });
      textLines.push(headers.join('\t'));
      htmlRows.push('<tr>' + headers.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>');
    }

    for (let ri = r.r1; ri <= r.r2; ri++) {
      const row = rows[ri];
      if (!row) continue;
      const cells = [];
      for (let ci = r.c1; ci <= r.c2; ci++) {
        const f = listFields[ci];
        if (!f) continue;
        cells.push(String(row[f.alias_name ?? ''] ?? ''));
      }
      textLines.push(cells.join('\t'));
      htmlRows.push('<tr>' + cells.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>');
    }
    const text = textLines.join('\n');
    const html = `<table>${htmlRows.join('')}</table>`;

    const done = () => {
      setCopyDone(true);
      setTimeout(() => setCopyDone(false), 1500);
    };

    // text/html + text/plain 동시 기록 — Excel 이 HTML 의 <td> 수를 그대로 인식해 trailing empty 보존
    const canWrite = navigator.clipboard && typeof navigator.clipboard.write === 'function' && typeof window.ClipboardItem !== 'undefined';
    if (canWrite) {
      const item = new window.ClipboardItem({
        'text/html':  new Blob([html], { type: 'text/html'  }),
        'text/plain': new Blob([text], { type: 'text/plain' }),
      });
      navigator.clipboard.write([item]).then(done).catch(() => {
        // 폴백 — plain text 만
        navigator.clipboard.writeText(text).then(done).catch(() => { legacyCopy(text); done(); });
      });
    } else if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => { legacyCopy(text); done(); });
    } else {
      legacyCopy(text);
      done();
    }
  }

  // ── 셀 범위 붙여넣기 (admin 권한 + grid_list_edit=Y + text 타입 컬럼만) ──
  async function handleGridPaste(e) {
    // input/textarea/contentEditable 내부의 paste 는 그대로 통과
    // (예: 컬럼헤더 인라인 필터 input — onPaste 컨테이너 안쪽에 있어서
    //  셀 선택이 남아 있으면 preventDefault 가 input 의 paste 를 막아버리는 버그 방지)
    const tgt = e.target;
    if (tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable)) return;
    if (editCell) return; // 인라인 편집 중에는 기본 paste
    const r = getSelRange();
    if (!r) return; // 선택 없으면 무시
    e.preventDefault();

    // HTML / plain text 모두 파싱해서 더 큰 그리드(=trailing empty 보존된 쪽)를 채택
    const html = e.clipboardData?.getData('text/html') ?? '';
    const txt  = e.clipboardData?.getData('text') ?? '';

    let gridHtml = null;
    const tableMatch = html.match(/<table[\s\S]*?<\/table>/i);
    if (tableMatch) {
      const tmp = document.createElement('div');
      tmp.innerHTML = tableMatch[0];
      const trs = Array.from(tmp.querySelectorAll('tr'));
      gridHtml = trs.map(tr => Array.from(tr.querySelectorAll('td,th')).map(td => {
        // 셀 내 <br> 은 줄바꿈으로 보존, nbsp 는 공백으로 정규화
        const c = td.cloneNode(true);
        c.querySelectorAll('br').forEach(b => b.replaceWith('\n'));
        return (c.textContent ?? '').replace(/ /g, ' ').trim();
      }));
    }

    // plain text → 표준 인용 규칙을 지키는 TSV 파서 (셀 내 줄바꿈/탭에서도 행·열이 폭증하지 않음)
    let gridTxt = txt ? parseClipboardTSV(txt) : null;

    // 디버그: window.__misPasteDebug=1 설정 시 원본 클립보드 콘솔 출력
    if (typeof window !== 'undefined' && window.__misPasteDebug) {
      console.log('[mis-paste] text =', JSON.stringify(txt));
      console.log('[mis-paste] html =', JSON.stringify(html));
      console.log('[mis-paste] gridTxt =', JSON.stringify(gridTxt), '| gridHtml =', JSON.stringify(gridHtml));
    }

    // plain text(표준 TSV 파서) 우선 — 엑셀/시트/그리드 모두 정확히 행·열을 인식.
    // text 가 비어있을 때만 HTML 테이블 파싱으로 폴백.
    const txtHasContent = gridTxt && gridTxt.some(r => r.some(c => (c ?? '') !== ''));
    let grid = txtHasContent ? gridTxt : ((gridHtml && gridHtml.length) ? gridHtml : gridTxt);
    if (!grid || !grid.length) return;

    // 클립보드 크기
    const rawRows = grid.length;
    const rawCols = grid.reduce((m, a) => Math.max(m, a.length), 0);
    if (rawRows === 0 || rawCols === 0) return;
    setPasteError('');

    // Excel 식 붙여넣기: 선택 영역의 좌상단(r.r1,r.c1)을 시작점으로 클립보드 크기만큼 채운다.
    //  - 선택 영역 크기와 달라도 OK (외부 엑셀 복사 포함)
    //  - 그리드 범위(행/열)를 넘으면 자동으로 잘라 붙임 (오류 대신)
    const startR = r.r1;
    const startC = r.c1;
    const pRows = Math.min(rawRows, rows.length - startR);
    const pCols = Math.min(rawCols, listFields.length - startC);
    if (pRows <= 0 || pCols <= 0) return;
    const clipped = (pRows < rawRows) || (pCols < rawCols);

    // 컬럼별 붙여넣기 가능 여부 — 불가 컬럼(편집불가/날짜/체크/조인 등)은 위치 정렬을 유지한 채 건너뜀
    const canPasteField = (f) => {
      if (!f) return false;
      const st  = (f.schema_type ?? '').trim();
      const ctl = (f.grid_ctl_name ?? '').trim();
      return f.grid_list_edit === 'Y'
        && (f.db_table ?? '') === 'table_m'
        && (st === '' || st === 'text' || st.startsWith('number'))
        && ctl !== 'check' && ctl !== 'checkbox';
    };

    // 미리보기 빌드 — 좌상단 기준 위치 1:1 매핑, 붙여넣기 불가 컬럼은 skip
    const preview = {};
    let pasteCells = 0;
    for (let ri = 0; ri < pRows; ri++) {
      const row = rows[startR + ri];
      if (!row) continue;
      // 저장 PK — fields[0](pkAlias) 값. PK가 idx가 아닌 테이블(it_id 등) 대응
      const savePk = row[pkAlias] ?? row.idx;
      for (let ci = 0; ci < pCols; ci++) {
        const f = listFields[startC + ci];
        if (!canPasteField(f)) continue;
        const val = (grid[ri] && grid[ri][ci] !== undefined) ? grid[ri][ci] : '';
        preview[savePk] = { ...(preview[savePk] ?? {}), [f.alias_name]: val };
        pasteCells++;
      }
    }
    if (pasteCells === 0) {
      showToast('붙여넣기 시작 위치에 편집 가능한(text/number) 컬럼이 없습니다.', 'error');
      return;
    }

    // 누적 — 기존 미리보기에 병합 (Yes/No 선택 전에도 계속 복/붙 가능)
    setPastePreview(prev => {
      const merged = { ...prev };
      for (const rid in preview) {
        merged[rid] = { ...(merged[rid] ?? {}), ...preview[rid] };
      }
      const totalRows = Object.keys(merged).length;
      const colSet = new Set();
      for (const rid in merged) for (const alias in merged[rid]) colSet.add(alias);
      setPasteConfirm({
        message: `${colSet.size}개 항목 ${totalRows}건 을 일괄저장하시겠습니까?`
          + ` (이번 붙여넣기: ${pRows}×${pCols}${clipped ? ` — 클립보드 ${rawRows}×${rawCols} 중 그리드 범위로 잘림` : ''})`,
        nRows: totalRows,
        nCols: colSet.size,
      });
      return merged;
    });
  }

  async function onPasteConfirmYes() {
    const preview = pastePreview;
    const nRows = Object.keys(preview).length;
    setPasteConfirm(null);
    try {
      // PK 가 숫자가 아닐 수 있음(it_id/real_pid 등) → 순수 숫자만 Number, 그 외 문자열 유지
      const edits = Object.entries(preview).map(([idx, cols]) => ({ idx: /^\d+$/.test(idx) ? Number(idx) : idx, ...cols }));
      const res = await api.bulkListSave(gubun, edits);
      showToast(res.message || `${nRows}건 저장 완료`);
    } catch (ex) {
      showToast(ex.message || '저장 실패', 'error');
    }
    setPastePreview({});
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    load(page, orderby, af, recently);
  }
  function onPasteConfirmNo() {
    setPasteConfirm(null);
    setPastePreview({});
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    load(page, orderby, af, recently);
  }

  function handleGridKeyDown(e) {
    // input/textarea 내부 키이벤트는 통과 (Ctrl+A=텍스트 전체선택, Ctrl+C=텍스트복사 등 브라우저 기본 보존)
    const tgt = e.target;
    if (tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable)) return;
    // Esc → 블록 선택 해제 (스크롤바 조작으로는 절대 해제되지 않으므로 명시적 해제 수단 제공)
    if (e.key === 'Escape') {
      setSelAnchor(null); setSelFocus(null);
      return;
    }
    // Ctrl+C 복사
    if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
      e.preventDefault();
      handleCopy();
      return;
    }
    // Ctrl+A 전체선택
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
      e.preventDefault();
      if (rows.length > 0 && listFields.length > 0) {
        setSelAnchor({ ri: 0, ci: 0 });
        setSelFocus({ ri: rows.length - 1, ci: listFields.length - 1 });
      }
      return;
    }
    // 방향키 이동
    if (!selFocus) return;
    const maxRi = rows.length - 1;
    const maxCi = listFields.length - 1;
    let { ri, ci } = selFocus;
    let moved = true;
    // Ctrl(또는 Meta) + 방향키 → 끝까지 점프 (Excel 호환). Shift 와 조합하면 블록 선택 유지.
    const jump = e.ctrlKey || e.metaKey;
    switch (e.key) {
      case 'ArrowUp':    ri = jump ? 0     : Math.max(0, ri - 1);    break;
      case 'ArrowDown':  ri = jump ? maxRi : Math.min(maxRi, ri + 1); break;
      case 'ArrowLeft':  ci = jump ? 0     : Math.max(0, ci - 1);    break;
      case 'ArrowRight': ci = jump ? maxCi : Math.min(maxCi, ci + 1); break;
      default: moved = false;
    }
    if (!moved) return;
    e.preventDefault();
    const newPos = { ri, ci };
    setSelFocus(newPos);
    if (!e.shiftKey) setSelAnchor(newPos);
  }

  // ── 컬럼 너비 ────────────────────────────────────────────────────────────────

  // 컬럼 기본 raw 너비 — col_width=N → N글자 표시 가능한 폭
  // (사용자가 드래그 조정한 값은 sw() 에서 override 로 별도 처리 — 축소 스케일을 우회하여 직접 display px 로 사용)
  // 공식: width = N × 5.51 + 39
  function getColWidth(f) {
    const chars = Math.abs(parseInt(f.col_width ?? '10', 10));
    return Math.max(55, Math.ceil(chars * 5.51 + 39));
  }

  function handleResizeStart(e, alias, currentDisplayWidth) {
    e.preventDefault();
    e.stopPropagation();
    resizeDrag.current = { alias, startX: e.clientX, startWidth: currentDisplayWidth };

    function onMouseMove(ev) {
      const d   = resizeDrag.current;
      const dx  = ev.clientX - d.startX;
      const newW = Math.max(30, d.startWidth + dx);
      setColWidths(prev => ({ ...prev, [d.alias]: newW }));
    }
    function onMouseUp() {
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      resizeDrag.current = null;
    }
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  }

  function handleSearch() {
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    lastAppliedSigRef.current = JSON.stringify({ t: filterValuesRef.current ?? {}, c: colFiltersRef.current ?? {} });
    load(1, orderby, af, recently);
  }

  async function handleExcel() {
    try {
      const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
      const urlSearch = new URLSearchParams(window.location.search);
      const agg = urlSearch.get('aggregate');
      const excelParams = {
        page: 1, pageSize: 10000, orderby, allFilter: af,
        recently: recently ? 'Y' : 'N',
      };
      if (agg) excelParams.aggregate = agg;
      // 백업 보기 모드 — _backup 파라미터 함께 전달해야 백엔드가 JSON 파일에서 데이터 export
      const _bk = urlSearch.get('_backup');
      if (_bk) excelParams._backup = _bk;
      const data = await api.list(gubun, excelParams);
      const allRows = data.data ?? [];
      const excelHasGroupHeader = listFields.some(f => (f.col_title ?? '').includes(','));
      const excelGH = excelHasGroupHeader
        ? computeHeaderGroups(listFields)
        : { parsed: null, groups: null };
      const headerRows = excelHasGroupHeader ? 2 : 1;
      const headers = ['No', ...listFields.map(f => {
        const t = parseColTitle(f.col_title ?? f.alias_name ?? '');
        return t.r2 ?? t.r1 ?? f.alias_name ?? '';
      })];
      const excelTotal = data.total ?? allRows.length;

      // 스타일 공통 — xlsx-js-style 포맷
      const thinBd = { style: 'thin', color: { rgb: 'FFCBD0DB' } };
      const borderAll = { top: thinBd, bottom: thinBd, left: thinBd, right: thinBd };
      const headerStyle = {
        font: { bold: true, color: { rgb: 'FFFFFFFF' }, sz: 11 },
        fill: { patternType: 'solid', fgColor: { rgb: 'FF4F6EF7' } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: {
          top:    { style: 'thin', color: { rgb: 'FF2E3250' } },
          bottom: { style: 'medium', color: { rgb: 'FF2E3250' } },
          left:   { style: 'thin', color: { rgb: 'FF2E3250' } },
          right:  { style: 'thin', color: { rgb: 'FF2E3250' } },
        },
      };
      const dataBase = { border: borderAll, alignment: { vertical: 'center' } };
      const stripeFill = { fill: { patternType: 'solid', fgColor: { rgb: 'FFF7F8FC' } } };
      const aggFill    = { fill: { patternType: 'solid', fgColor: { rgb: 'FFE8EDFD' } } };
      const totalFill  = { fill: { patternType: 'solid', fgColor: { rgb: 'FFD6DEFA' } } };

      // 필드별 헤더 스타일 (grid_align 반영, 없으면 기본 center)
      const hStyleFor = (f) => ({
        ...headerStyle,
        alignment: { ...headerStyle.alignment, horizontal: xlsxHorizontal(f?.grid_align, false, 'center') },
      });

      const ws = {};
      const range = { s: { r: 0, c: 0 }, e: { r: allRows.length + headerRows - 1, c: headers.length - 1 } };
      const merges = [];

      // 헤더 행 (1행 또는 2행)
      if (excelHasGroupHeader) {
        // No 컬럼: 2행 병합
        ws[XLSX.utils.encode_cell({ r: 0, c: 0 })] = { t: 's', v: 'No', s: headerStyle };
        ws[XLSX.utils.encode_cell({ r: 1, c: 0 })] = { t: 's', v: '', s: headerStyle };
        merges.push({ s: { r: 0, c: 0 }, e: { r: 1, c: 0 } });

        excelGH.groups.forEach(g => {
          const colStart = g.startIdx + 1;
          if (g.r1 === null) {
            // standalone: rowspan=2, 두 행 모두 r2 표시 후 병합
            const p = excelGH.parsed[g.startIdx];
            const label = p.r2 ?? '';
            const hs = hStyleFor(p.field);
            ws[XLSX.utils.encode_cell({ r: 0, c: colStart })] = { t: 's', v: label, s: hs };
            ws[XLSX.utils.encode_cell({ r: 1, c: colStart })] = { t: 's', v: '', s: hs };
            merges.push({ s: { r: 0, c: colStart }, e: { r: 1, c: colStart } });
          } else {
            // 그룹: r1을 colspan(center 고정), r2를 각 셀에 grid_align 반영
            ws[XLSX.utils.encode_cell({ r: 0, c: colStart })] = { t: 's', v: g.r1, s: headerStyle };
            for (let k = 1; k < g.colspan; k++) {
              ws[XLSX.utils.encode_cell({ r: 0, c: colStart + k })] = { t: 's', v: '', s: headerStyle };
            }
            if (g.colspan > 1) {
              merges.push({ s: { r: 0, c: colStart }, e: { r: 0, c: colStart + g.colspan - 1 } });
            }
            for (let k = 0; k < g.colspan; k++) {
              const p = excelGH.parsed[g.startIdx + k];
              ws[XLSX.utils.encode_cell({ r: 1, c: colStart + k })] = { t: 's', v: p.r2 ?? '', s: hStyleFor(p.field) };
            }
          }
        });
      } else {
        // 1행 헤더: No는 center, 필드는 grid_align
        ws[XLSX.utils.encode_cell({ r: 0, c: 0 })] = { t: 's', v: headers[0], s: headerStyle };
        listFields.forEach((f, ci) => {
          const addr = XLSX.utils.encode_cell({ r: 0, c: ci + 1 });
          ws[addr] = { t: 's', v: headers[ci + 1], s: hStyleFor(f) };
        });
      }

      // 데이터 행 — No + 필드값
      let dataRowIndex = 0;
      allRows.forEach((row, ri) => {
        const aggType = row.__agg_type;
        const isAgg   = !!aggType;
        const isTotal = aggType === 'total';
        const stripe  = !isAgg && (ri % 2 === 1);
        const rowFillBase = isTotal ? totalFill : isAgg ? aggFill : (stripe ? stripeFill : null);

        // No 컬럼
        const noAddr = XLSX.utils.encode_cell({ r: ri + headerRows, c: 0 });
        if (isAgg) {
          ws[noAddr] = {
            t: 's',
            v: isTotal ? '합계' : '소계',
            s: {
              ...dataBase,
              ...rowFillBase,
              alignment: { horizontal: 'center', vertical: 'center' },
              font: { bold: true, color: { rgb: 'FF1A1D27' } },
            },
          };
        } else {
          dataRowIndex++;
          ws[noAddr] = {
            t: 'n', v: dataRowIndex, z: '0',
            s: {
              ...dataBase,
              ...(rowFillBase ?? {}),
              alignment: { horizontal: 'center', vertical: 'center' },
              font: { color: { rgb: 'FF8C93B0' } },
            },
          };
        }

        // 필드 컬럼
        listFields.forEach((f, ci) => {
          const addr = XLSX.utils.encode_cell({ r: ri + headerRows, c: ci + 1 });
          const alias = f.alias_name ?? '';
          const raw  = row[alias] ?? '';
          const isNumField = typeof f.schema_type === 'string' && f.schema_type.startsWith('number');
          const hAlign = xlsxHorizontal(f.grid_align, isNumField, null);
          const fieldAlign = hAlign
            ? { horizontal: hAlign, vertical: 'center' }
            : { vertical: 'center' };
          const cellBase = { ...dataBase, ...(rowFillBase ?? {}), alignment: fieldAlign };

          if (isAgg) {
            if (raw === '' || raw === null || raw === undefined) {
              ws[addr] = { t: 's', v: '', s: cellBase };
            } else {
              const font = isNumField
                ? { bold: true,  color: { rgb: 'FF1A1D27' } }
                : { italic: true, color: { rgb: 'FF4A5068' } };
              if (isNumField) {
                const n = typeof raw === 'number' ? raw : parseFloat(String(raw).replace(/,/g, ''));
                ws[addr] = Number.isFinite(n)
                  ? { t: 'n', v: n, s: { ...cellBase, font, numFmt: '#,##0' } }
                  : { t: 's', v: String(raw), s: { ...cellBase, font } };
              } else {
                ws[addr] = { t: 's', v: String(raw), s: { ...cellBase, font } };
              }
            }
          } else if (isNumField) {
            if (raw === '' || raw === null || raw === undefined) {
              ws[addr] = { t: 's', v: '', s: cellBase };
            } else {
              const num = typeof raw === 'number' ? raw : parseFloat(String(raw).replace(/,/g, ''));
              if (Number.isFinite(num)) {
                ws[addr] = { t: 'n', v: num, s: { ...cellBase, numFmt: '#,##0' } };
              } else {
                ws[addr] = { t: 's', v: String(raw), s: cellBase };
              }
            }
          } else {
            ws[addr] = { t: 's', v: String(raw), s: cellBase };
          }
        });
      });

      // 컬럼 너비 — 웹 그리드의 실제 픽셀 폭을 wch(문자 단위)로 변환
      const pxToWch = (px) => Math.max(4, Math.round((px - 5) / 7));
      ws['!cols'] = [{ wch: 6 }, ...listFields.map(f => ({ wch: pxToWch(getColWidth(f)) }))];
      // 행 높이 (헤더 약간 높게)
      const headerRowHeights = Array.from({ length: headerRows }, () => ({ hpt: 22 }));
      ws['!rows'] = [...headerRowHeights, ...allRows.map(() => ({ hpt: 18 }))];
      ws['!ref'] = XLSX.utils.encode_range(range);
      if (merges.length > 0) ws['!merges'] = merges;
      // 헤더 행 freeze (xlsx-js-style)
      ws['!views'] = [{
        state: 'frozen',
        xSplit: 0,
        ySplit: headerRows,
        topLeftCell: `A${headerRows + 1}`,
        activePane: 'bottomLeft',
      }];

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, sanitizeSheetName(menu?.menu_name));
      const baseName = sanitizeFileName(menu?.menu_name ?? 'export');
      // 백업 모드 — 현재시간 대신 JSON 파일명 stem 을 suffix 로 (어느 백업의 export 인지 분명히)
      const _bkFile = urlParams.current?.get('_backup') ?? '';
      const suffix  = _bkFile ? sanitizeFileName(_bkFile.replace(/\.json$/i, '')) : fileTimestamp();
      writeXlsxWithFreeze(wb, `${baseName}_${suffix}.xlsx`, headerRows);
    } catch (e) {
      alert(e.message);
    }
  }

  /**
   * 빠른 xls 다운로드 — 서버가 SELECT 결과를 HTML 테이블로 직접 stream 해서 .xls 로 다운로드.
   *
   * v6 의 빠른 export 방식:
   *   - 서버: PDO unbuffered 로 한 행씩 fetch → echo (메모리 누적 없음)
   *   - 클라이언트: 그냥 URL 클릭 → 브라우저가 다운로드 처리 (JSON parse 안 함)
   *
   * 결과: xlsx-js-style 워크북 빌드 + zip 압축 + 클라이언트 HTML 빌드 모두 건너뜀 → 매우 빠름.
   */
  function handleXlsFast() {
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    const qs = new URLSearchParams({
      act:        'list',
      gubun:      String(gubun),
      page:       '1',
      pageSize:   '999999',
      orderby:    orderby ?? '',
      allFilter:  af,
      recently:   recently ? 'Y' : 'N',
      _xlsStream: '1',
    });
    // 숨겨진 anchor 로 다운로드 트리거 — SPA 페이지 이탈 없이 파일만 받음
    const a = document.createElement('a');
    a.href = `api.php?${qs.toString()}`;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => a.remove(), 0);
  }

  function handlePrint() {
    const printHasGroupHeader = listFields.some(f => (f.col_title ?? '').includes(','));
    // grid_align → th/td inline style (center/right, 없으면 '')
    const alignStyle = (f) => {
      const a = printAlign(f?.grid_align);
      return a ? ` style="text-align:${a}"` : '';
    };
    let theadHtml;
    if (printHasGroupHeader) {
      const { parsed, groups } = computeHeaderGroups(listFields);
      const row1Cells = ['<th class="no-col" rowspan="2">NO</th>'];
      const row2Cells = [];
      groups.forEach(g => {
        if (g.r1 === null) {
          const p = parsed[g.startIdx];
          row1Cells.push(`<th rowspan="2"${alignStyle(p.field)}>${p.r2 ?? ''}</th>`);
        } else {
          row1Cells.push(`<th colspan="${g.colspan}" style="text-align:center">${g.r1}</th>`);
          for (let k = 0; k < g.colspan; k++) {
            const p = parsed[g.startIdx + k];
            row2Cells.push(`<th${alignStyle(p.field)}>${p.r2 ?? ''}</th>`);
          }
        }
      });
      theadHtml = `<tr>${row1Cells.join('')}</tr><tr>${row2Cells.join('')}</tr>`;
    } else {
      const noCellH = '<th class="no-col">NO</th>';
      const fieldHs = listFields.map(f => {
        const t = parseColTitle(f.col_title ?? f.alias_name ?? '');
        const title = t.r2 ?? t.r1 ?? f.alias_name ?? '';
        return `<th${alignStyle(f)}>${title}</th>`;
      }).join('');
      theadHtml = `<tr>${noCellH}${fieldHs}</tr>`;
    }
    // 데이터 행 번호 — 그리드와 동일 규칙 (total - (page-1)*pageSize - ri). aggregate 행은 '소계'/'합계'
    let dataIdx = 0;
    const tbodyHtml = rows.map((row, ri) => {
      const aggType = row.__agg_type;
      let noCell;
      if (aggType === 'total')          noCell = '<td class="no-col agg">합계</td>';
      else if (aggType === 'subtotal')  noCell = '<td class="no-col agg">소계</td>';
      else {
        dataIdx++;
        const n = total - (page - 1) * pageSize - (ri - (ri - dataIdx + 1));
        noCell = `<td class="no-col">${total > 0 ? (total - (page - 1) * pageSize - ri) : dataIdx}</td>`;
      }
      const trCls = aggType ? ' class="agg-row"' : '';
      const tds = listFields.map(f => `<td${alignStyle(f)}>${String(row[f.alias_name ?? ''] ?? '')}</td>`).join('');
      return `<tr${trCls}>${noCell}${tds}</tr>`;
    }).join('');
    const title = menu?.menu_name ?? '목록';
    const win = window.open('', '_blank', 'width=1000,height=700');
    win.document.write(`<!DOCTYPE html>
<html lang="ko"><head><meta charset="utf-8"><title>${title}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Malgun Gothic',Arial,sans-serif;font-size:11px;padding:14px;padding-top:56px}
h2{font-size:13px;font-weight:bold;margin-bottom:8px}
table{width:100%;border-collapse:collapse;table-layout:auto}
th,td{border:1px solid #bbb;padding:3px 5px;text-align:left;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
thead th{background:#f0f0f0;font-weight:bold}
.no-col{width:42px;text-align:center;color:#666;font-variant-numeric:tabular-nums}
.no-col.agg{color:#111;font-weight:bold}
tr.agg-row td{background:#f6f7fb;font-weight:bold}
.toolbar{position:fixed;top:0;left:0;right:0;padding:10px 14px;background:#fff;border-bottom:1px solid #ddd;display:flex;gap:8px;align-items:center;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.toolbar button{padding:6px 14px;font-size:12px;border:1px solid #4F6EF7;background:#4F6EF7;color:#fff;border-radius:4px;cursor:pointer;font-family:inherit;font-weight:600}
.toolbar button:hover{background:#3e5bd9}
.toolbar button.secondary{background:#fff;color:#555;border-color:#ccc}
.toolbar button.secondary:hover{background:#f4f5f7}
.toolbar .sp{flex:1}
.toolbar .hint{color:#888;font-size:11px}
@media print{
  .toolbar{display:none}
  body{padding-top:14px}
  thead{display:table-header-group}
}
</style></head><body>
<div class="toolbar">
  <button onclick="window.print()">🖨 인쇄</button>
  <button class="secondary" onclick="window.close()">닫기</button>
  <span class="sp"></span>
  <span class="hint">Ctrl+P 로도 인쇄할 수 있습니다</span>
</div>
<h2>${title}</h2>
<table><thead>${theadHtml}</thead><tbody>${tbodyHtml}</tbody></table>
</body></html>`);
    win.document.close();
    win.focus();
  }

  function handleToggleRecently() {
    const newR = !recently;
    setRecently(newR);
    const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
    load(1, orderby, af, newR);
  }

  function handleFilterChange(alias, val) {
    filterValuesRef.current = { ...filterValuesRef.current, [alias]: val };
    setFilterValues(filterValuesRef.current);
  }

  function handleFilterRangeChange(alias, key, val) {
    const prev = filterValuesRef.current;
    filterValuesRef.current = {
      ...prev,
      [alias]: { ...(prev[alias] ?? { from: '', to: '' }), [key]: val },
    };
    setFilterValues(filterValuesRef.current);
  }

  // 목록 표시 필드: col_width ∉ {0, -1, -2}
  //   - col_width=0 은 폼에만 표시, 리스트에서 숨김 (db_table 무관 — virtual_field 도 동일)
  //   - virtual_field 를 리스트에 노출하려면 col_width 를 양수로 설정 후 list_json_load 훅에서 값 주입
  const listFields = fields.length > 0
    ? fields.filter(f => {
        const w = parseInt(f.col_width ?? '0', 10);
        return w !== 0 && w !== -1 && w !== -2;
      })
    : (rows[0]
        ? Object.keys(rows[0])
            .filter(k => !['idx','wdate','wdater','lastupdate','lastupdater','useflag'].includes(k))
            .slice(0, 8)
            .map(k => ({ alias_name: k, col_title: k, col_width: 10 }))
        : []);

  // 필터 필드: grid_is_handle ∈ {s, t, w}
  // popup 모드 (isPopup=Y) — embed iframe 안에선 상단 사용자 필터 영역 자체를 숨김
  const _isPopupMode = (urlParams.current?.get('isPopup') ?? '') === 'Y';
  const filterFields = _isPopupMode
    ? []
    : fields.filter(f => ['s','t','w'].includes(f.grid_is_handle ?? ''));

  // ※ 동일 필터로 blur 재검색 시 list 재렌더가 발생해 첫 클릭이 사라지는 문제
  //   (필터 후 idx 2번 클릭 필요) 방지 — toolbar 필터 + 컬럼 헤더 필터 통합 시그니처로 비교
  const lastAppliedSigRef = useRef(JSON.stringify({ t: filterValuesRef.current ?? {}, c: {} }));
  const buildSig = () => JSON.stringify({
    t: filterValuesRef.current ?? {},
    c: colFiltersRef.current ?? {},
  });

  // 컬럼 필터 blur 검색 함수
  colFilterSearchRef.current = () => {
    const cur = buildSig();
    if (cur === lastAppliedSigRef.current) return;
    lastAppliedSigRef.current = cur;
    const af = buildAllFilter(filterValuesRef.current, filterFields);
    load(1, orderby, af, recently);
  };

  // 툴바 필터 blur 검색 함수
  const toolbarBlurSearch = () => {
    const cur = buildSig();
    if (cur === lastAppliedSigRef.current) return;
    lastAppliedSigRef.current = cur;
    const af = buildAllFilter(filterValuesRef.current, filterFields);
    load(1, orderby, af, recently);
  };

  // PK 필드: fields 전체 중 sort_order 1번째 (col_width 무관, 숨겨져도 됨)
  // 두 개의 "first" 분리:
  //   linkValAlias  = URL idx 로 보낼 값을 가진 필드 (PK 숨김 마커 -1/-2 만 skip — col_width=0 OK)
  //   clickCellAlias = 그리드에서 "클릭 가능한 링크 셀" 의 alias (그리드 표시 첫 셀)
  // 둘이 다를 수 있음 (예: 6118 — it_id col_width=0 으로 그리드 숨김이지만 URL idx 매칭 필드)
  const pkAlias       = fields[0]?.alias_name ?? 'idx';
  const lookupField   = fields.find(f => { const w = parseInt(f.col_width ?? '0', 10); return w !== -1 && w !== -2; });
  const firstAlias    = lookupField?.alias_name ?? '';
  const clickCellAlias = listFields[0]?.alias_name ?? firstAlias;
  // URL idx 값 결정 규칙:
  //   fields[0].col_width가 -1/-2(완전 숨김) → lookup 후보 필드값 사용 (예: it_id, real_pid)
  //   그 외(0 또는 양수)                      → pk 필드값 사용 (예: integer idx)
  const pk0cw        = parseInt(fields[0]?.col_width ?? '0', 10);
  const usePkForLink = pk0cw !== -1 && pk0cw !== -2;
  const getLinkVal   = (r) => usePkForLink
    ? (r[pkAlias] ?? r.idx)
    : (r[firstAlias] ?? r[pkAlias] ?? r.idx);
  const totalPages = Math.ceil(total / pageSize);
  const colSpan    = listFields.length + 2 + (isSimpleList ? 0 : 1);
  const hasAnyColFilter = Object.values(colFilters).some(v => v?.trim());

  // 컬럼 폭 자동 fit:
  //   1) 자연 합 < 컨테이너 폭 → 우측 여백 흡수
  //      - flex 컬럼(col_width ≥ 30자) 이 있으면 그 컬럼만 확장 (이미지URL/숫자/코드 등 좁은 컬럼은 자연폭 유지)
  //      - flex 가 없으면 모든 컬럼 균등 확장
  //   2) 자연 합 > 컨테이너 폭 → 모든 컬럼 균등 축소 (0.85 까지). 그 이하는 가로 스크롤 (가독성 보호)
  // table-fixed/border-collapse 측정오차 + scrollbar gutter 흡수용 4px 안전마진.
  const FLEX_CHAR_THRESHOLD = 30;
  const _fixedColsSum = (isSimpleList ? 0 : 45) + 60;
  const _narrowFieldsSum = listFields.reduce((s, f) => {
    const chars = Math.abs(parseInt(f.col_width ?? '10', 10));
    return s + (chars < FLEX_CHAR_THRESHOLD ? getColWidth(f) : 0);
  }, 0);
  const _flexFieldsSum = listFields.reduce((s, f) => {
    const chars = Math.abs(parseInt(f.col_width ?? '10', 10));
    return s + (chars >= FLEX_CHAR_THRESHOLD ? getColWidth(f) : 0);
  }, 0);
  const _reservedSum    = _fixedColsSum + _narrowFieldsSum;
  const _allNativeSum   = _reservedSum + _flexFieldsSum;
  const _safeTableW     = Math.max(0, tableW - 4);
  const _availableForFlex = Math.max(0, _safeTableW - _reservedSum);

  let flexScale = 1, narrowScale = 1;
  if (_safeTableW > 0 && _allNativeSum > 0) {
    if (_safeTableW > _allNativeSum) {
      // 케이스 1: 여백 있음
      if (_flexFieldsSum > 0) {
        // flex 만 확장
        flexScale = _availableForFlex / _flexFieldsSum;
      } else {
        // flex 없음 → 모든 컬럼 균등 확장
        narrowScale = _safeTableW / _allNativeSum;
      }
    } else if (_safeTableW < _allNativeSum) {
      // 케이스 2: 자연 합이 컨테이너 초과
      const ratio = _safeTableW / _allNativeSum;
      if (ratio >= 0.85) {
        // 살짝 부족 → 모든 컬럼 균등 축소
        flexScale = ratio;
        narrowScale = ratio;
      }
      // ratio < 0.85: 가로스크롤 허용 (자연폭 유지)
    }
  }
  // 전역 autoScaleRef 는 resize 핸들러가 쓰므로 "사용자가 조정할 컬럼 기준" 으로 설정 (1 = 스케일 없이 px 그대로)
  const autoScale = 1;
  const sw = (w, f) => {
    // 사용자 드래그로 조정된 컬럼: override 값을 display px 로 그대로 사용
    const alias = f?.alias_name;
    if (alias && colWidths[alias] != null) {
      return Math.round(colWidths[alias]);
    }
    if (f) {
      const chars = Math.abs(parseInt(f.col_width ?? '10', 10));
      return chars >= FLEX_CHAR_THRESHOLD ? Math.floor(w * flexScale) : Math.floor(w * narrowScale);
    }
    // 체크박스/No 등 fixed 컬럼 — narrow 와 동일 스케일
    return Math.floor(w * narrowScale);
  };
  autoScaleRef.current = 1;
  // 실제 렌더될 컬럼 폭의 합 (사용자 드래그 override 반영). 드래그 시 이 합이 변하면 테이블도 함께 확장/축소돼야
  // 브라우저가 "table-fixed + 고정폭" 때문에 cols 를 비례 축소하지 않음.
  const _actualFixedSum = sw(isSimpleList ? 0 : 45) + sw(60);
  const _actualFieldsSum = listFields.reduce((s, f) => s + sw(getColWidth(f), f), 0);
  const _actualColsSum = _actualFixedSum + _actualFieldsSum;
  // 테이블 명시 너비:
  //   - 실제 합이 컨테이너보다 크면 → 실제 합 (가로 스크롤)
  //   - 작거나 같으면 → undefined (100% 채움)
  const tableExplicitW = _actualColsSum > tableW && tableW > 0 ? _actualColsSum : undefined;

  // URL 생성용: filterValues → allFilter JSON (toolbar_ 접두어 포함)
  function buildUrlAllFilter() {
    const filters = [];
    filterFields.forEach(f => {
      const alias  = f.alias_name ?? '';
      const handle = f.grid_is_handle ?? '';
      const val    = filterValues[alias];
      if (handle === 't' && val) {
        filters.push({ field: `toolbar_${alias}`, operator: 'contains', value: val });
      } else if (handle === 's' && val) {
        filters.push({ field: `toolbar_${alias}`, operator: 'eq', value: val });
      } else if (handle === 'w') {
        const from = val?.from ?? '', to = val?.to ?? '';
        if (from || to) filters.push({ field: `toolbar_${alias}`, operator: 'between', value: [from, to] });
      }
    });
    return JSON.stringify(filters);
  }

  function buildCurrentUrl() {
    const p = new URLSearchParams(window.location.search);
    const toolbarParts = JSON.parse(buildUrlAllFilter() || '[]');
    const colParts = Object.entries(colFiltersRef.current)
      .map(([alias, raw]) => parseColFilter(alias, raw)).filter(Boolean);
    const allParts = [...toolbarParts, ...colParts];
    const af = allParts.length > 0 ? JSON.stringify(allParts) : '[]';
    if (af !== '[]') p.set('allFilter', af); else p.delete('allFilter');
    if (orderby)     p.set('orderby', orderby); else p.delete('orderby');
    p.set('recently', recently ? 'Y' : 'N');
    p.delete('colF');
    return window.location.pathname + '?' + decodeURIComponent(p.toString());
  }

  // 정렬 정보 맵: alias → { dir:'asc'|'desc', rank:number }
  const sortInfo = {};
  if (orderby) {
    orderby.split(',').filter(Boolean).forEach((token, i) => {
      const desc  = token.startsWith('-');
      const alias = desc ? token.slice(1) : token;
      sortInfo[alias] = { dir: desc ? 'desc' : 'asc', rank: i + 1 };
    });
  }
  const multiSort = Object.keys(sortInfo).length > 1;
  const selRange = getSelRange(); // 행 강조용 미리 계산

  function sortLabel(alias) {
    const s = sortInfo[alias];
    if (!s) return null;
    return (s.dir === 'asc' ? '▲' : '▼') + (multiSort ? s.rank : '');
  }

  // 2행 헤더 여부 판단
  const hasGroupHeader = listFields.some(f => (f.col_title ?? '').includes(','));
  const { parsed, groups } = hasGroupHeader
    ? computeHeaderGroups(listFields)
    : { parsed: listFields.map(f => ({ r1: null, r2: f.col_title ?? f.alias_name ?? '', field: f })), groups: [] };

  if (error) return (
    <div className="flex-1 px-5 py-5 text-danger text-base">{error}</div>
  );

  const isNarrow = gridW < 450 || isMobile;

  return (
    <div ref={gridContainerRef} className="relative flex flex-col flex-1 overflow-hidden">

      {/* ── 붙여넣기 안내/오류 메시지 배너 ── */}
      {pasteError && (
        <div
          className="flex items-center justify-between gap-3 px-4 py-2.5 flex-shrink-0 border-b-2"
          style={{ background: '#FEE2E2', color: '#991B1B', borderColor: '#EF4444' }}
        >
          <span className="text-sm font-bold">⚠ {pasteError}</span>
          <button
            type="button"
            className="h-btn-sm px-3 text-xs rounded font-bold cursor-pointer"
            style={{ background: '#fff', color: '#991B1B', border: '1px solid #EF4444' }}
            onClick={() => setPasteError('')}
          >✕ 닫기</button>
        </div>
      )}

      {/* ── 붙여넣기 일괄저장 확인 바 (그리드 상단 고정, 배경 dim 없음) ── */}
      {pasteConfirm && (
        <div
          className="flex items-center justify-between gap-3 px-4 py-2.5 flex-shrink-0 border-b-2 animate-pulse-subtle"
          style={{
            background: '#FDE68A',
            color:      '#78350F',
            borderColor:'#F59E0B',
          }}
        >
          <span className="text-sm font-bold">📋 {pasteConfirm.message}</span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="h-btn-sm px-4 text-xs rounded font-bold cursor-pointer"
              style={{ background: '#F59E0B', color: '#fff' }}
              onClick={onPasteConfirmYes}
            >✓ Yes (저장)</button>
            <button
              type="button"
              className="h-btn-sm px-4 text-xs rounded font-bold cursor-pointer"
              style={{ background: '#fff', color: '#78350F', border: '1px solid #F59E0B' }}
              onClick={onPasteConfirmNo}
            >✕ No</button>
          </div>
        </div>
      )}

      {/* ── 삭제내역 모드 배너 ── */}
      {deletedMode && (
        <div className="flex items-center justify-between gap-2 px-3 py-2 bg-danger-dim border-b border-danger flex-shrink-0">
          <span className="text-sm font-bold text-danger">⚠ 삭제내역 모드 — 체크 후 복원/완전삭제 ({checkedRows.size}건 선택됨)</span>
          <div className="flex items-center gap-2">
            <button
              disabled={checkedRows.size === 0}
              className="h-btn-sm px-3 text-xs rounded border border-accent bg-accent text-white font-semibold cursor-pointer hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
              onClick={handleBulkRestore}
            >복원</button>
            <button
              disabled={checkedRows.size === 0}
              className="h-btn-sm px-3 text-xs rounded border border-danger bg-danger text-white font-semibold cursor-pointer hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
              onClick={handleBulkPermanentDelete}
            >완전삭제</button>
            <button
              className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer"
              onClick={() => onExitDeletedMode?.()}
            >정상목록 조회</button>
          </div>
        </div>
      )}

      {/* ── 검색 필터 툴바 ── panelOpen 이고 panelSize<4 일 때도 '목록전용' 시점의 폭을 유지, hover 시 플로팅 */}
      <div className="max-md:overflow-x-auto max-md:scrollbar-hide flex-shrink-0">
      <div
        className="grid-toolbar flex items-start border-b border-border-base flex-shrink-0 bg-surface-2 min-h-[38px] max-md:min-w-[768px]"
        style={(() => {
          if (!panelOpen || panelSize >= 4) return undefined;
          const gridPct = { 1: 75, 2: 50, 3: 25 }[panelSize];
          if (!gridPct) return undefined;
          return { width: `${(100 / gridPct) * 100}%` };
        })()}
      >
        {/* 맨 좌측: 새로고침 + 최근순 + 조회/수정 모드 */}
        <div className="flex-shrink-0 flex items-center gap-1 pl-2 pr-1 py-2 border-r border-border-base">
          <button
            title="새로고침"
            className={clickModeCls}
            onClick={() => {
              const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
              load(page, orderby, af, recently);
            }}
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
            </svg>
          </button>
          <button
            id="mis-btn-recently"
            className={recently ? recentlyOnCls : recentlyOffCls}
            onClick={handleToggleRecently}
            title={recently ? '최근순 OFF' : '최근순 ON'}
          >최근순</button>
          <button
            title="조회 모드"
            className={clickMode === 'view' ? clickModeActiveCls : clickModeCls}
            onClick={() => {
              setClickMode('view');
              const tr  = rows[selFocus?.ri ?? 0] ?? rows[0];
              if (!tr) return;
              const rPk = usePkForLink ? (tr[pkAlias] ?? tr.idx ?? 0) : getLinkVal(tr);
              if (rPk) onToggleView?.(rPk, getLinkVal(tr));
            }}
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
          {access.write && (
          <button
            title="수정 모드"
            className={clickMode === 'modify' ? clickModeActiveCls : clickModeCls}
            onClick={() => {
              setClickMode('modify');
              const tr = rows[selFocus?.ri ?? 0] ?? rows[0];
              if (!tr) return;
              // hidden PK(col_width=-1/-2) 메뉴에서도 동작하도록 조회 모드와 동일 로직 사용 —
              // 정수 idx 가 없을 때는 visible-key (getLinkVal) 로 fallback
              const rPk = usePkForLink ? (tr[pkAlias] ?? tr.idx ?? 0) : getLinkVal(tr);
              if (rPk === undefined || rPk === null || rPk === '' || rPk === 0) return;
              onModify?.(rPk, getLinkVal(tr));
            }}
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
          )}
        </div>

        {/* 좌측: 필터 영역 (공간 부족 시 여러 줄로 자동 줄바꿈 — 엑셀 리본 스타일) */}
        <div className="flex-1 flex flex-wrap items-center gap-x-2 gap-y-1 px-4 py-1 min-w-0">
          {filterFields.map(f => {
            const alias  = f.alias_name ?? '';
            const handle = f.grid_is_handle ?? '';
            const label  = (() => {
              const s = f.col_title ?? alias;
              const ci = s.indexOf(',');
              return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || alias;
            })();

            if (handle === 's') {
              // 상단 필터는 실제 데이터 distinct 값 우선 (items 는 입력/수정용 선택목록이라 필터와 무관)
              // 서버 로드 실패/지연 시에는 items 로 폴백 — 단 SQL 쿼리 형태('select ...')는 폴백 제외
              const dynVals    = dynamicOptions[alias] ?? null;
              const rawItems   = f.items ?? '';
              const isSqlItems = /^\s*select\s+/i.test(rawItems);
              const staticOpts = (rawItems && !isSqlItems) ? parseItems(rawItems) : null;
              const baseOptions = dynVals ? dynVals.map(v => ({ value: v, text: v })) : (staticOpts ?? []);
              // URL 로 미리 선택된 값이 있는데 아직 옵션이 로드되지 않았으면 그 값을 임시 옵션으로 끼워넣어 표시 유지
              const currentVal = filterValues[alias] ?? '';
              const hasCurrent = currentVal === '' || baseOptions.some(o => String(o.value) === String(currentVal));
              const options = hasCurrent ? baseOptions : [{ value: currentVal, text: currentVal }, ...baseOptions];
              const doChange = (val) => {
                handleFilterChange(alias, val);
                const newVals = { ...filterValues, [alias]: val };
                load(1, orderby, buildAllFilter(newVals, filterFields), recently);
              };
              // lazy-load 트리거: hover/focus 시 distinct 값 한 번만 가져옴
              const preload = () => loadFilterItems(alias);
              // preset 값이 있으면 즉시 preload (hover 전이라도)
              if (currentVal !== '' && !loadedFilterAliases.current.has(alias)) {
                preload();
              }
              // s 필터는 폭 제한 (옵션 길이에 따라 과도하게 늘어나는 것 방지)
              const selectWidthCls = ' w-[140px] max-w-[180px]';
              return (
                <div
                  key={alias}
                  className="flex items-center gap-1 flex-shrink-0"
                  onMouseEnter={preload}
                  onFocusCapture={preload}
                >
                  <span className="text-xs text-secondary whitespace-nowrap">{label}</span>
                  {options.length > SEARCHABLE_THRESHOLD ? (
                    <SearchableSelect
                      options={[...options]}
                      value={filterValues[alias] ?? ''}
                      className={filterInputCls + ' cursor-pointer' + selectWidthCls}
                      onChange={(val) => doChange(val)}
                    />
                  ) : (
                    <select
                      className={filterInputCls + selectWidthCls}
                      value={filterValues[alias] ?? ''}
                      onMouseDown={preload}
                      onChange={e => doChange(e.target.value)}
                    >
                      <option value="">전체</option>
                      {options.map(o => (
                        <option key={o.value} value={o.value}>{o.text ?? o.value}</option>
                      ))}
                    </select>
                  )}
                </div>
              );
            }

            if (handle === 'w') {
              const rv = filterValues[alias] ?? { from: '', to: '' };
              // schema_type 으로 date / number 자동 분기 (모바일과 동일)
              const _st = (f.schema_type ?? '').toLowerCase();
              const isNumberRange = _st === 'number' || _st.startsWith('number');
              const inputType  = isNumberRange ? 'text' : 'date';
              const inputModeAttr = isNumberRange ? 'numeric' : undefined;
              // 날짜는 native picker 아이콘 공간 필요 → 폭 더 줌
              const rangeInputCls = filterInputCls + (isNumberRange ? ' w-[100px]' : ' w-[140px]');
              return (
                <div key={alias} className="flex items-center gap-1 flex-shrink-0">
                  <span className="text-xs text-secondary whitespace-nowrap">{label}</span>
                  <input
                    className={rangeInputCls}
                    type={inputType}
                    inputMode={inputModeAttr}
                    placeholder={isNumberRange ? '시작' : ''}
                    value={rv.from ?? ''}
                    onChange={e => handleFilterRangeChange(alias, 'from', e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && handleSearch()}
                    onBlur={toolbarBlurSearch}
                  />
                  <span className="text-xs text-muted">~</span>
                  <input
                    className={rangeInputCls}
                    type={inputType}
                    inputMode={inputModeAttr}
                    placeholder={isNumberRange ? '끝' : ''}
                    value={rv.to ?? ''}
                    onChange={e => handleFilterRangeChange(alias, 'to', e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && handleSearch()}
                    onBlur={toolbarBlurSearch}
                  />
                </div>
              );
            }

            // handle === 't'
            return (
              <div key={alias} className="flex items-center gap-1 flex-shrink-0">
                <span className="text-xs text-secondary whitespace-nowrap">{label}</span>
                <input
                  className={filterInputCls}
                  type="text"
                  placeholder={`${label} 검색`}
                  value={filterValues[alias] ?? ''}
                  onChange={e => handleFilterChange(alias, e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && handleSearch()}
                  onBlur={toolbarBlurSearch}
                />
              </div>
            );
          })}
        </div>

        {/* 우측: 내용보기 */}
        {!isMobile && (
          <div className="relative flex-shrink-0 flex items-center gap-1 px-3 py-2">
            {!panelOpen && !noPanelBtn && !onlyListMode && <>
              <span className="text-border-base mx-0.5 select-none">|</span>
              {(!onPanelSizeClick || isNarrow) ? (
                <button
                  className={viewSizeCls}
                  onClick={() => {
                    const tr  = rows[selFocus?.ri ?? 0] ?? rows[0] ?? {};
                    const rPk = usePkForLink ? (tr[pkAlias] ?? tr.idx ?? 0) : getLinkVal(tr);
                    if (onPanelSizeClick) onPanelSizeClick(4, rPk, getLinkVal(tr));
                    else onToggleView?.(rPk, getLinkVal(tr));
                  }}
                >내용보기</button>
              ) : (<>
                <span className="text-xs text-secondary whitespace-nowrap select-none">내용보기</span>
                {[4, 3, 2, 1].map(size => (
                  <button
                    key={size}
                    className={panelSize === size ? viewSizeDimActiveCls : viewSizeCls}
                    onClick={() => {
                      const tr  = rows[selFocus?.ri ?? 0] ?? rows[0] ?? {};
                      const rPk = usePkForLink ? (tr[pkAlias] ?? tr.idx ?? 0) : getLinkVal(tr);
                      onPanelSizeClick(size, rPk, getLinkVal(tr));
                    }}
                  >{size}</button>
                ))}
              </>)}
            </>}
          </div>
        )}
      </div>
      </div>


      {/* ── SQL 상세 모달 ── */}
      {sqlModalOpen && devSql && (
        <div
          className="fixed inset-0 z-[200] flex items-center justify-center"
          className="modal-overlay"
          onClick={() => setSqlModalOpen(false)}
        >
          <div
            className="bg-surface rounded-lg border border-border-base shadow-pop flex flex-col overflow-hidden modal-box"
            style={{ width: 'min(860px, 92vw)', maxHeight: '80vh' }}
            onClick={e => e.stopPropagation()}
          >
            {/* 모달 헤더 */}
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-border-base bg-surface-2 flex-shrink-0">
              <span className="text-sm font-bold text-primary">실행 쿼리 (개발자모드)</span>
              <div className="flex items-center gap-2">
                <button
                  className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors"
                  onClick={() => { copyText(buildCopyText(devSql)); showToast('복사되었습니다'); }}
                >복사</button>
                <button
                  className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors"
                  onClick={() => setSqlModalOpen(false)}
                >✕ 닫기</button>
              </div>
            </div>
            <div className="flex-1 overflow-auto p-4 flex flex-col gap-4">
              {devSql.error && (
                <div className="rounded border border-solid border-danger bg-danger-dim px-3 py-2 flex items-start gap-2">
                  <span className="text-danger font-bold text-sm flex-shrink-0">SQL 오류</span>
                  <span className="text-danger text-xs font-mono break-all leading-5">{devSql.error}</span>
                </div>
              )}
              <div>
                <div className={`text-xs font-bold mb-1 uppercase tracking-wide ${devSql.error ? 'text-danger' : 'text-secondary'}`}>SELECT</div>
                <pre className={`text-xs bg-surface-2 rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6 ${devSql.error ? 'text-danger' : 'text-primary'}`}>{formatSQL(devSql.sql)}</pre>
              </div>
              {devSql.count_sql && (
                <div>
                  <div className="text-xs font-bold text-secondary mb-1 uppercase tracking-wide">COUNT</div>
                  <pre className="text-xs text-primary bg-surface-2 rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6">{formatSQL(devSql.count_sql)}</pre>
                </div>
              )}
              {devSql.bindings?.length > 0 && (
                <div>
                  <div className="text-xs font-bold text-secondary mb-1 uppercase tracking-wide">바인딩 값</div>
                  <pre className="text-xs text-primary bg-surface-2 rounded p-3 font-mono leading-6">{devSql.bindings.map((v, i) => `[${i + 1}] ${JSON.stringify(v)}`).join('\n')}</pre>
                </div>
              )}
              {devSql.execSql?.length > 0 && (
                <div>
                  <div className="text-xs font-bold text-link mb-1 uppercase tracking-wide">실행쿼리 (execSql)</div>
                  {devSql.execSql.map((log, i) => (
                    <div key={i} className="mb-2">
                      <pre className={`text-xs rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6 ${log.result === 'fail' ? 'bg-danger-dim text-danger' : 'bg-surface-2 text-primary'}`}>
                        {formatSQL(log.sql)}{log.bindings?.length > 0 ? '\n-- bindings: ' + JSON.stringify(log.bindings) : ''}{'\n'}-- {log.result === 'success' ? `OK (${log.rowCount ?? 0} rows)` : `FAIL: ${log.error}`}
                      </pre>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── 테이블 ── */}
      <div
        ref={tableScrollRef}
        className="flex-1 overflow-auto outline-none"
        tabIndex={0}
        onKeyDown={handleGridKeyDown}
        onPaste={handleGridPaste}
      >
        {copyDone && (
          <div className="absolute top-2 left-1/2 -translate-x-1/2 z-50 px-3 py-1 rounded bg-accent text-white text-xs shadow pointer-events-none">
            복사됨
          </div>
        )}
        <table
          className={(tableExplicitW ? '' : 'w-full ') + 'table-fixed border-collapse text-base bg-surface select-none'}
          style={tableExplicitW ? { width: tableExplicitW + 'px' } : undefined}
          data-autoscale={autoScale.toFixed(3)} data-tablew={tableW} data-colsum={_allNativeSum}>
          <colgroup>
            {!isSimpleList && <col className="mis-check-col" style={{width: sw(45) + 'px'}} />}
            <col style={{width: sw(60) + 'px'}} />
            {listFields.map(f => (
              <col key={f.alias_name ?? ''} style={{width: sw(getColWidth(f), f) + 'px'}} />
            ))}
          </colgroup>
          <thead className="bg-surface-2">
            {hasGroupHeader ? (
              <>
                {/* ── 상단 그룹 띠 ── 그룹별로 colSpan, standalone 컬럼은 rowSpan=2 로 흡수 ── */}
                <tr>
                  {!isSimpleList && <th rowSpan={2} className={thCls + ' text-center mis-check-col'} style={{width:sw(45),maxWidth:sw(45)}}>
                    <input type="checkbox" checked={rows.length > 0 && checkedRows.size === rows.length} onChange={handleCheckAll} className="cursor-pointer" tabIndex={-1} />
                  </th>}
                  <th rowSpan={2} className={thCls + ' text-center mis-no-col'} style={{width:sw(60),maxWidth:sw(60)}}
                      onClick={toggleFilterRow} title={showFilterRow ? '필터 숨기기' : '필터 보기'}>
                    <span className="inline-flex items-center gap-1 justify-center">
                      <span className={showFilterRow ? 'text-link' : hasAnyColFilter ? 'text-danger' : 'text-muted'}>No</span>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                        className={'mis-no-filter-icon ' + (!showFilterRow && hasAnyColFilter ? 'text-danger' : showFilterRow ? 'text-link' : 'text-muted')}>
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                      </svg>
                    </span>
                  </th>
                  {groups.map((g, gi) => {
                    if (g.r1 === null) {
                      // standalone 컬럼 — rowSpan=2 로 두 행에 걸쳐 컬럼 라벨 표시
                      const fi = g.startIdx;
                      const f  = listFields[fi];
                      const p  = parsed[fi];
                      const alias = f.alias_name ?? '';
                      const cw = getColWidth(f);
                      const sl = sortLabel(alias);
                      const hasColFilter = !!(colFilters[alias]?.trim());
                      const al = alignClass(f.grid_align);
                      return (
                        <th key={alias} rowSpan={2} className={thCls + (al ? ' ' + al : '')}
                            style={{ width: sw(cw, f) + 'px' }}
                            title={f.col_title ?? alias}
                            onClick={e => handleSort(alias, e)}>
                          <div className="flex items-center justify-start leading-tight">
                            {hasColFilter && <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent mr-0.5 align-middle flex-shrink-0" />}
                            <span className="truncate">{p.r2 || (f.col_title ?? alias)}</span>
                            {sl && <span className="text-link text-[10px] ml-0.5">{sl}</span>}
                          </div>
                          <ResizeHandle onMouseDown={e => handleResizeStart(e, alias, sw(cw, f))} />
                        </th>
                      );
                    }
                    // 그룹 라벨 띠 — colSpan, 컬럼 헤더와 다른 톤 (밝은 surface, 가운데정렬, 작은 글자)
                    return (
                      <th key={'g' + gi} colSpan={g.colspan} className={groupBandThCls}>
                        {g.r1 || ''}
                      </th>
                    );
                  })}
                </tr>
                {/* ── 하단 개별 컬럼명 행 ── 그룹 소속 컬럼만 (standalone 은 위에서 흡수됨) ── */}
                <tr>
                  {groups.flatMap((g, gi) => {
                    if (g.r1 === null) return [];
                    return Array.from({ length: g.colspan }, (_, k) => {
                      const fi = g.startIdx + k;
                      const f  = listFields[fi];
                      const p  = parsed[fi];
                      const alias = f.alias_name ?? '';
                      const cw = getColWidth(f);
                      const sl = sortLabel(alias);
                      const hasColFilter = !!(colFilters[alias]?.trim());
                      const al = alignClass(f.grid_align);
                      return (
                        <th key={alias} className={subThCls + (al ? ' ' + al : '')}
                            style={{ width: sw(cw, f) + 'px' }}
                            title={f.col_title ?? alias}
                            onClick={e => handleSort(alias, e)}>
                          <div className="flex items-center justify-start leading-tight">
                            {hasColFilter && <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent mr-0.5 align-middle flex-shrink-0" />}
                            <span className="truncate">{p.r2 || (f.col_title ?? alias)}</span>
                            {sl && <span className="text-link text-[10px] ml-0.5">{sl}</span>}
                          </div>
                          <ResizeHandle onMouseDown={e => handleResizeStart(e, alias, sw(cw, f))} />
                        </th>
                      );
                    });
                  })}
                </tr>
              </>
            ) : (
              <tr>
                {!isSimpleList && <th className={thCls + ' text-center mis-check-col'} style={{width:sw(45),maxWidth:sw(45)}}>
                  <input type="checkbox" checked={rows.length > 0 && checkedRows.size === rows.length} onChange={handleCheckAll} className="cursor-pointer" tabIndex={-1} />
                </th>}
                <th className={thCls + ' text-center mis-no-col'} style={{width:sw(60),maxWidth:sw(60)}}
                    onClick={toggleFilterRow} title={showFilterRow ? '필터 숨기기' : '필터 보기'}>
                  <span className="inline-flex items-center gap-1 justify-center">
                    <span className={showFilterRow ? 'text-link' : hasAnyColFilter ? 'text-danger' : 'text-muted'}>No</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                      className={'mis-no-filter-icon ' + (!showFilterRow && hasAnyColFilter ? 'text-danger' : showFilterRow ? 'text-link' : 'text-muted')}>
                      <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                  </span>
                </th>
                {listFields.map(f => {
                  const alias = f.alias_name ?? '';
                  const cw = getColWidth(f);
                  const sl = sortLabel(alias);
                  const hasColFilter = !!(colFilters[alias]?.trim());
                  const al = alignClass(f.grid_align);
                  return (
                    <th key={alias} className={thCls + (al ? ' ' + al : '')} style={{ width: sw(cw, f) + 'px' }}
                        title={f.col_title ?? alias}
                        onClick={e => handleSort(alias, e)}>
                      {hasColFilter && <span className="inline-block w-1.5 h-1.5 rounded-full bg-accent mr-0.5 align-middle -mt-0.5 flex-shrink-0" />}
                      {f.col_title ?? alias}
                      {sl && <span className="text-link text-[10px] ml-0.5">{sl}</span>}
                      <ResizeHandle onMouseDown={e => handleResizeStart(e, alias, sw(cw, f))} />
                    </th>
                  );
                })}
              </tr>
            )}
            {/* 컬럼 헤더 인라인 필터 행 — 2-row 헤더면 그룹 띠(28px) 만큼 더 밀림 */}
            <tr ref={filterRowRef} className={'border-b border-border-base' + (showFilterRow ? '' : ' hidden')} style={{position:'sticky',top: hasGroupHeader ? 64 : 36, zIndex:9}}>
              {!isSimpleList && <td className="px-1 py-0.5 bg-surface-2 border-r border-border-base mis-check-col" style={{width:sw(45),maxWidth:sw(45)}} />}
              <td className="px-1 py-0.5 bg-surface-2 border-r border-border-base" style={{width:sw(60),maxWidth:sw(60)}} />
              {listFields.map(f => {
                const alias = f.alias_name ?? '';
                const cw = getColWidth(f);
                return (
                  <td key={alias} className="px-1 py-0.5 bg-surface border-r border-border-base" style={{ maxWidth: sw(cw, f) + 'px' }}>
                    <input
                      type="text"
                      className="w-full h-5 px-1.5 text-xs bg-surface-2 border border-border-base rounded text-primary outline-none focus:border-accent transition-colors"
                      value={colFilters[alias] ?? ''}
                      placeholder=""
                      onChange={e => {
                        const newFilters = { ...colFiltersRef.current, [alias]: e.target.value };
                        colFiltersRef.current = newFilters;
                        setColFilters(newFilters);
                      }}
                      onKeyDown={e => {
                        e.stopPropagation();
                        if (e.key === 'Enter') {
                          colFilterSearchRef.current?.();
                        }
                        if (e.key === 'Escape') {
                          const n = { ...colFiltersRef.current };
                          delete n[alias];
                          colFiltersRef.current = n;
                          setColFilters(n);
                          colFilterSearchRef.current?.();
                        }
                      }}
                      onBlur={e => {
                        if (filterRowRef.current?.contains(e.relatedTarget)) return;
                        colFilterSearchRef.current?.();
                      }}
                      onClick={e => e.stopPropagation()}
                      onMouseDown={e => e.stopPropagation()}
                    />
                  </td>
                );
              })}
            </tr>
          </thead>

          <tbody>
            {loading ? (
              <SkeletonRows colSpan={colSpan} />
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={colSpan} className="py-12 text-center text-muted text-sm">
                  <svg className="w-8 h-8 mx-auto mb-2 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                  데이터가 없습니다.
                </td>
              </tr>
            ) : rows.map((row, ri) => {
              // PK가 숨겨진 경우(usePkForLink=false) → 첫 visible 필드값을 식별자로 사용
              const rowPk      = usePkForLink ? (row[pkAlias] ?? row.idx) : getLinkVal(row);
              const rowLinkVal = getLinkVal(row);
              // 저장/인라인편집용 PK — 서버 save 는 fields[0](=pkAlias)를 PK 컬럼으로 사용.
              // g5_shop_item.it_id 처럼 PK가 idx가 아닌 테이블에서 row.idx 는 0/undefined 이므로
              // 반드시 pkAlias 값을 보내야 INSERT 가 아닌 UPDATE 가 된다.
              const rowSavePk  = row[pkAlias] ?? row.idx;
              // string/number 혼합 비교: pk 또는 linkVal 중 하나라도 일치하면 강조
              const isActiveRow = panelOpen && !!currentIdx
                && (rowPk == currentIdx || String(rowLinkVal) === String(currentIdx)); // eslint-disable-line eqeqeq
              const isInSel     = selRange ? ri >= selRange.r1 && ri <= selRange.r2 : false;
              const isSavedRow  = savedRowIdx != null && (rowSavePk == savedRowIdx); // eslint-disable-line eqeqeq
              const rowBgCls    = isSavedRow  ? 'saved-row-flash'
                                : isActiveRow ? 'bg-accent-dim'
                                : isInSel     ? 'bg-surface-2'
                                :               'hover:bg-surface-2';
              const aggType = row.__agg_type; // 'subtotal' | 'total' | undefined
              const isAgg = !!aggType;
              const aggRows = row.__agg_rows; // simple 모드일 때만 첨부됨
              const aggClickable = isAgg && Array.isArray(aggRows) && aggRows.length > 0;
              // popup 모드(embed iframe): 합계(total) 행 숨김 — 부모창의 합계와 중복되므로
              if (_isPopupMode && aggType === 'total') return null;
              const aggRowCls = aggType === 'total'   ? 'bg-surface-2 font-bold'
                              : aggType === 'subtotal' ? 'bg-surface-2/60 font-semibold'
                              : '';
              return (
              <tr key={row.idx ?? `__agg_${ri}`}
                  className={`transition-colors ${isAgg ? aggRowCls : rowBgCls}${aggClickable ? ' cursor-pointer hover:bg-accent-dim' : ''}`}
                  onClick={aggClickable ? () => {
                    // aggregate row 클릭 → 같은 프로그램을 aggregate=auto + allFilter 로 새 탭으로 오픈
                    // (mis:openTab dispatch — Layout.jsx 가 forceNew=true 로 받아 항상 새 탭)
                    // 주의: urlParams.current 는 mount 시점 스냅샷이라 헤더 클릭으로 정렬이 바뀌어도 stale.
                    //       정렬 기준은 live state(orderby) 사용, gubun 은 prop 사용.
                    const orderbyParam = orderby || new URLSearchParams(window.location.search).get('orderby') || '';
                    const curGubun = Number(gubun || new URLSearchParams(window.location.search).get('gubun') || 0);
                    const groupFields = orderbyParam.split(',').map(s => s.replace(/^-/, '').trim()).filter(Boolean);
                    const firstRow = aggRows[0] || {};
                    // 빈/NULL 값은 operator='isNull' 로 — eq 로는 NULL 매칭 안 됨
                    const filters = groupFields.map(f => {
                      const v = firstRow[f];
                      if (v === null || v === undefined || v === '') {
                        return { field: f, operator: 'isNull', value: '' };
                      }
                      return { field: f, operator: 'eq', value: String(v) };
                    });

                    // 탭 라벨: 그룹값 / 그룹값 ... (N건)
                    const labelParts = groupFields.map(f => {
                      const v = firstRow[f];
                      return (v === null || v === undefined || v === '') ? '(공백)' : String(v).trim();
                    });
                    const label = aggType === 'total'
                      ? `합계 상세 (${aggRows.length}건)`
                      : `${labelParts.join(' / ') || '소계'} (${aggRows.length}건)`;

                    // addUrl 구성 (orderby/recently/aggregate/allFilter)
                    const extra = new URLSearchParams();
                    if (orderbyParam) extra.set('orderby', orderbyParam);
                    extra.set('recently', 'N');
                    extra.set('aggregate', 'auto');
                    if (aggType === 'subtotal' && filters.length) {
                      extra.set('allFilter', JSON.stringify(filters));
                    }

                    window.dispatchEvent(new CustomEvent('mis:openTab', {
                      detail: {
                        gubun: curGubun,
                        label,
                        addUrl: '&' + extra.toString(),
                      },
                    }));
                  } : undefined}>
                {!isSimpleList && (isAgg
                  ? <td className={tdCls} style={{width:sw(45),maxWidth:sw(45)}}></td>
                  : (row.__readonly === 1 || row.__readonly === '1' || row.__readonly === true)
                    ? <td className={tdCls + ' text-center mis-check-col'} style={{width:sw(45),maxWidth:sw(45)}} title="읽기전용">
                        <span className="text-xs text-muted">🔒</span>
                      </td>
                    : <td className={tdCls + ' text-center cursor-pointer mis-check-col'} style={{width:sw(45),maxWidth:sw(45)}} onClick={e => { e.stopPropagation(); handleCheckRow(row.idx ?? row[pkAlias], e); }}>
                        <input type="checkbox" checked={checkedRows.has(row.idx ?? row[pkAlias])} readOnly className="pointer-events-none" />
                      </td>)}
                <td className={tdCls + (isAgg ? ' text-center text-primary text-xs font-bold' : ' text-center text-muted text-xs tabular-nums')} style={{width:sw(60),maxWidth:sw(60)}}>
                  {isAgg
                    ? (aggType === 'total' ? '합계' : '소계')
                    : (total - (page - 1) * pageSize - ri)}
                </td>
                {listFields.map((f, ci) => {
                  const alias    = f.alias_name ?? '';
                  const previewVal = pastePreview[rowSavePk]?.[alias];
                  const hasPreview = previewVal !== undefined;
                  const val      = hasPreview ? previewVal : (row[alias] ?? '');
                  const html     = hasPreview ? null : row.__html?.[alias]; // 미리보기 중에는 __html 무시
                  const noLink   = !!(html && /data-mis-nolink/.test(html)); // __html 안에 data-mis-nolink 가 있으면 view 진입 비활성
                  // 클릭 가능 링크 셀: 그리드에 노출된 첫 번째 셀 (URL idx 매칭 필드가 col_width=0 등으로 숨김인 경우 대비)
                  const isLink   = !isAgg && !noLink && alias === clickCellAlias;
                  const cw       = getColWidth(f);
                  const selected = !isAgg && isCellSelected(ri, ci);
                  const rowReadOnly = (row.__readonly === 1 || row.__readonly === '1' || row.__readonly === true);
                  // max_length 규칙 — 인라인편집은 modify 동작이므로 음수/0 은 편집 불가 (DataForm 과 동일 기준)
                  const _mlGridRaw = parseInt(f.max_length ?? '', 10);
                  const _mlGridReadOnly = !isNaN(_mlGridRaw) && _mlGridRaw <= 0;
                  // 객체명 check + max_length 끝 '!' = readonly 행이라도 confirm 후 저장 허용 (override)
                  const _isOverrideCheck = (
                    (f.grid_ctl_name === 'check' || f.grid_ctl_name === 'checkbox') &&
                    String(f.max_length ?? '').endsWith('!')
                  );
                  const isListEdit = access.write && !isAgg && !_mlGridReadOnly &&
                    (!rowReadOnly || _isOverrideCheck) &&
                    (f.grid_list_edit === 'Y' || !!fkMapRef.current[alias]);
                  const isCheckEdit = isListEdit && (f.grid_ctl_name === 'check' || f.grid_ctl_name === 'checkbox');
                  // attach/image + 목록편집=Y → 셀에서 수정폼과 동일한 첨부 위젯(FileAttach) 직접 렌더
                  const isAttachEdit = isListEdit && (f.grid_ctl_name === 'attach' || f.grid_ctl_name === 'image');
                  const isEditing  = !isAgg && editCell && editCell.ri === ri && editCell.alias === alias;
                  const al = alignClass(f.grid_align);
                  // textarea 컨트롤: 여러 줄 보존 + 고정 높이 범위에서 스크롤
                  const isTextarea = f.grid_ctl_name === 'textarea';
                  return (
                    <td key={alias}
                        className={tdCls + (al ? ' ' + al : '') + (selected ? ' !bg-accent-dim' : '') + (isListEdit && !isEditing && !isAttachEdit ? ' cursor-pointer' : '') + (isEditing ? ' !px-0' : '') + ((isTextarea || isAttachEdit) ? ' !h-auto align-top' : '') + ' relative'}
                        style={{
                          maxWidth: sw(cw, f) + 'px',
                          ...(hasPreview ? {
                            background: '#FDE68A',       // 밝은 앰버 — 눈에 확 띔
                            color:      '#78350F',        // 진한 갈색 텍스트 — 대비
                            fontWeight: 700,
                            boxShadow:  'inset 0 0 0 2px #F59E0B', // 주황 보더 링
                          } : {}),
                        }}
                        onMouseDown={isAgg ? undefined : e => { if (!isEditing) handleCellMouseDown(e, ri, ci); }}
                        onMouseEnter={isAgg ? undefined : () => { if (!isEditing) handleCellMouseEnter(ri, ci); }}
                        onClick={isAgg ? undefined
                          : isCheckEdit && !isEditing ? () => handleCheckClick(ri, alias, val, rowSavePk, rowReadOnly && _isOverrideCheck, f)
                          : isListEdit && !isCheckEdit && !isAttachEdit && !isEditing && selected ? () => startEdit(ri, alias, val, rowSavePk, row)
                          : undefined}
                        onDoubleClick={!isAgg && isListEdit && !isCheckEdit && !isAttachEdit && !isEditing ? () => startEdit(ri, alias, val, rowSavePk, row) : undefined}>
                      {savedCell && savedCell.idx == rowSavePk && savedCell.alias === alias && (
                        <span className="saved-check-flash">
                          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                        </span>
                      )}
                      <div
                        className={(isEditing || isAttachEdit) ? '' : (isTextarea ? '' : 'truncate')}
                        style={!isEditing && isTextarea ? {
                          whiteSpace: 'pre-line',
                          maxHeight: '130px',
                          minHeight: '26px',
                          overflow: 'auto',
                          verticalAlign: 'top',
                        } : undefined}
                      >
                        {isAttachEdit
                          ? (() => {
                              const ai = parseAttachLimit(f.max_length);
                              return (
                                <FileAttach
                                  gubun={gubun}
                                  idx={rowSavePk}
                                  realPid={f.field_real_pid ?? ''}
                                  alias={alias}
                                  readOnly={false}
                                  multi={ai.multi}
                                  maxMB={ai.maxMB}
                                  maxCount={ai.maxCount}
                                  allowExts={f.schema_validation || ''}
                                  mode="modify"
                                  midx={parseInt(row[alias + '_midx'] ?? 0, 10) || 0}
                                  immediate
                                />
                              );
                            })()
                          : isEditing
                          ? <InlineEdit
                              field={f}
                              fkField={editCell.fkField}
                              value={editVal}
                              onChange={setEditVal}
                              onSave={saveEdit}
                              onCancel={cancelEdit}
                              saving={editSaving}
                              gubun={gubun}
                            />
                          : html
                          ? (isLink && !onlyListMode
                              ? <span className="text-link cell-html cursor-pointer underline underline-offset-2 hover:text-accent-hover"
                                      dangerouslySetInnerHTML={{ __html: html }}
                                      onClick={e => {
                                        // data-opentab 버튼 클릭은 App.jsx capture 핸들러가 stopImmediatePropagation 처리하므로 여기까지 안 옴
                                        if (e.shiftKey) {
                                          e.preventDefault();
                                          onOpenTab?.(rowPk, rowLinkVal);
                                        } else if (e.ctrlKey || e.metaKey) {
                                          const p = new URLSearchParams(window.location.search);
                                          p.set('idx', String(rowLinkVal));
                                          window.open(window.location.pathname + '?' + p.toString(), '_blank');
                                        } else if (clickMode === 'modify') {
                                          onModify?.(rowPk, rowLinkVal);
                                        } else {
                                          onToggleView(rowPk, rowLinkVal);
                                        }
                                      }} />
                              : <span className="text-primary cell-html" dangerouslySetInnerHTML={{ __html: html }} />)
                          : isLink && !onlyListMode
                          ? (() => {
                              // link 셀에서도 schema_type (date^^MM-dd 등) 포맷 적용
                              const display = (val !== null && val !== undefined && val !== '')
                                ? (typeof f.schema_type === 'string' && f.schema_type.includes('^^')
                                    ? formatBySchema(val, f.schema_type)
                                    : (f.schema_type === 'date' && String(val).length >= 10 ? String(val).slice(0,10)
                                        : (f.schema_type === 'datetime' && String(val).length >= 10 ? String(val).slice(0,16) : val)))
                                : '-';
                              return (
                                <span className="text-link cursor-pointer underline underline-offset-2 hover:text-accent-hover"
                                      onClick={e => {
                                        if (e.shiftKey) {
                                          e.preventDefault();
                                          onOpenTab?.(rowPk, rowLinkVal);
                                        } else if (e.ctrlKey || e.metaKey) {
                                          const p = new URLSearchParams(window.location.search);
                                          p.set('idx', String(rowLinkVal));
                                          window.open(window.location.pathname + '?' + p.toString(), '_blank');
                                        } else if (clickMode === 'modify') {
                                          onModify?.(rowPk, rowLinkVal);
                                        } else {
                                          onToggleView(rowPk, rowLinkVal);
                                        }
                                      }}>{display}</span>
                              );
                            })()
                          : (isCheckEdit || f.grid_ctl_name === 'check' || f.grid_ctl_name === 'checkbox')
                          ? (() => {
                              const checked = val === 'Y' || val === '1' || val === 'true' || val === 1 || val === true;
                              const active = checkActive && checkActive.ri === ri && checkActive.alias === alias;
                              return (
                                <span className={`flex items-center justify-center transition-all ${active ? 'scale-125' : ''}`}>
                                  {checked
                                    ? <svg className={`w-4 h-4 ${active ? 'text-danger' : 'text-accent'}`} viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                                    : <span className={`text-base ${active ? 'text-accent' : 'text-secondary'}`}>☐</span>}
                                </span>
                              );
                            })()
                          : isListEdit
                          ? <span className="text-link cursor-pointer">{val || '-'}</span>
                          : <CellValue val={val} schemaType={f.schema_type} />
                        }
                      </div>
                    </td>
                  );
                })}
              </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* ── 페이지네이션 (항상 하단 고정) ── */}
      <div className="flex-shrink-0 flex items-center justify-between px-3 py-1.5 bg-surface border-t border-border-base">
        <div className="flex items-center gap-2">
          <span className="text-muted text-sm">전체 {total.toLocaleString()}건</span>
          <select
            className="h-btn-sm px-1.5 text-sm rounded border border-solid border-border-base bg-surface text-secondary outline-none cursor-pointer"
            value={pageSize}
            onChange={e => {
              const ps = Number(e.target.value);
              setPageSize(ps);
              const af = mergeExternalAndUiFilters(urlParams.current.get('allFilter') ?? '[]', filterValues, fields);
              load(1, orderby, af, recently, ps);
            }}
          >
            {[25, 1000].concat(![25, 1000].includes(pageSize) ? [pageSize] : [])
              .sort((a, b) => a - b)
              .map(n => <option key={n} value={n}>{n}개</option>)}
          </select>
        </div>
        <div className="flex gap-1 items-center">
          <PagerBtn label="«" disabled={page <= 1}          onClick={() => handlePage(1)} />
          <PagerBtn label="‹" disabled={page <= 1}          onClick={() => handlePage(page - 1)} />
          {isNarrow ? (
            <span className="text-sm text-secondary tabular-nums px-1 whitespace-nowrap">{page}/{totalPages}</span>
          ) : (() => {
            const WINDOW = 5;
            let start = Math.max(1, page - Math.floor(WINDOW / 2));
            let end   = Math.min(totalPages, start + WINDOW - 1);
            if (end - start + 1 < WINDOW) start = Math.max(1, end - WINDOW + 1);
            const pgs = [];
            for (let p = start; p <= end; p++) pgs.push(p);
            return (
              <>
                {pgs.map(pg => (
                  <PagerBtn key={pg} label={pg} active={pg === page} onClick={() => handlePage(pg)} />
                ))}
                {totalPages > WINDOW && (
                  <span className="text-xs text-muted tabular-nums px-1 whitespace-nowrap">/ {totalPages}</span>
                )}
              </>
            );
          })()}
          <PagerBtn label="›" disabled={page >= totalPages} onClick={() => handlePage(page + 1)} />
          <PagerBtn label="»" disabled={page >= totalPages} onClick={() => handlePage(totalPages)} />
        </div>
      </div>

      {/* aggregate 상세 팝업 (레거시 — 현재 미사용, mis:openTab 으로 대체) */}
      {aggPopup && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center modal-overlay"
             onClick={() => setAggPopup(null)}>
          <div className="bg-surface rounded-lg border border-border-base shadow-pop flex flex-col overflow-hidden modal-box"
               style={{ width: 'min(1200px, 95vw)', maxHeight: '85vh' }}
               onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-border-base bg-surface-2 flex-shrink-0">
              <span className="text-sm font-bold text-primary">{aggPopup.title} — {aggPopup.rows.length}건</span>
              <div className="flex items-center gap-2">
                <button className="h-btn-sm px-3 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2"
                        onClick={() => exportAggPopupExcel(aggPopup, listFields, menu)}>엑셀다운로드</button>
                <button className="h-btn-sm px-4 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2"
                        onClick={() => setAggPopup(null)}>닫기</button>
              </div>
            </div>
            <div className="flex-1 overflow-auto">
              <table className="w-full text-sm border-collapse">
                <thead className="bg-surface-2 sticky top-0 z-10">
                  <tr>
                    <th className="px-2 py-1.5 text-center text-xs font-bold text-muted border-b border-border-base" style={{width:60}}>No</th>
                    {listFields.map(f => {
                      const t = parseColTitle(f.col_title ?? f.alias_name ?? '');
                      return (
                        <th key={f.alias_name}
                            className="px-2 py-1.5 text-left text-xs font-bold text-primary border-b border-border-base whitespace-nowrap">
                          {t.r2 ?? t.r1 ?? f.alias_name}
                        </th>
                      );
                    })}
                  </tr>
                </thead>
                <tbody>
                  {aggPopup.rows.map((r, ri) => (
                    <tr key={r.idx ?? ri} className="hover:bg-surface-2">
                      <td className="px-2 py-1 text-center text-xs text-muted tabular-nums border-b border-border-base">{ri + 1}</td>
                      {listFields.map(f => {
                        const a = f.alias_name ?? '';
                        const html = r.__html?.[a];
                        return (
                          <td key={a} className="px-2 py-1 text-primary text-sm border-b border-border-base whitespace-nowrap">
                            {html
                              ? <span dangerouslySetInnerHTML={{ __html: html }} />
                              : <CellValue val={r[a] ?? ''} schemaType={f.schema_type} />}
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
});

export default DataGrid;

function ResizeHandle({ onMouseDown }) {
  return (
    <div
      className="absolute right-0 top-0 h-full w-2 cursor-col-resize group z-20"
      onMouseDown={onMouseDown}
      onClick={e => e.stopPropagation()}
    >
      <div className="absolute right-0.5 top-1 bottom-1 w-px bg-border-base opacity-0 group-hover:opacity-100 transition-opacity" />
    </div>
  );
}

const SKEL_WIDTHS = ['w-full','w-11/12','w-10/12','w-9/12','w-full','w-10/12','w-11/12','w-9/12'];
function SkeletonRows({ colSpan }) {
  return Array.from({ length: 8 }, (_, i) => (
    <tr key={i} className="border-b border-border-base">
      <td colSpan={colSpan} className="h-row px-3">
        <div className={`skeleton h-3 ${SKEL_WIDTHS[i]} rounded`} />
      </td>
    </tr>
  ));
}

function InlineEdit({ field, fkField, value, onChange, onSave, onCancel, saving, gubun }) {
  const activeField = fkField ?? field;
  const ctl = activeField.grid_ctl_name ?? '';
  const rawItems = activeField.items ?? '';
  const isSqlItems = /^\s*select\s+/i.test(rawItems);
  const staticItems = (!isSqlItems && rawItems) ? parseItems(rawItems) : [];
  const hasPrimeKey = !!(fkField?.prime_key);
  const isDate = field.schema_type === 'date' || field.schema_type === 'datetime';
  // select 에서 값 선택(onChange) 발생 여부 — blur 시 취소/저장 충돌 방지용
  const pickedRef = useRef(false);

  // prime_key 드롭다운: 서버에서 아이템 로드
  const [pkItems, setPkItems] = useState(null);
  useEffect(() => {
    if (!hasPrimeKey || !gubun) return;
    api.primeKeyItems(gubun, fkField.alias_name).then(res => {
      setPkItems(res.data ?? []);
    }).catch(() => setPkItems([]));
  }, [hasPrimeKey, gubun, fkField?.alias_name]);

  // SQL 기반 items: 서버에서 쿼리 결과 로드
  const [sqlItems, setSqlItems] = useState(null);
  useEffect(() => {
    if (!isSqlItems || !gubun) return;
    api.dropdownItems(gubun, activeField.alias_name).then(res => {
      setSqlItems(Array.isArray(res.data) ? res.data : []);
    }).catch(() => setSqlItems([]));
  }, [isSqlItems, gubun, activeField.alias_name]);

  const items = hasPrimeKey ? (pkItems ?? []) : isSqlItems ? (sqlItems ?? []) : staticItems;
  const isCheck = ctl === 'check' || ctl === 'checkbox';
  const isSelect = !isCheck && (hasPrimeKey || isSqlItems || ctl === 'dropdownlist' || ctl === 'dropdownitem' || ctl === 'select' || staticItems.length > 0);

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') { e.preventDefault(); onSave(e.shiftKey ? 'up' : 'down'); }
    if (e.key === 'Escape') onCancel();
  };

  // 체크박스: 클릭 즉시 토글 저장
  if (isCheck) {
    const checked = value === 'Y' || value === '1' || value === 1 || value === 'true' || value === true;
    // boolean(tinyint 0/1) 컬럼은 1/0, 그 외 char(1)은 'Y'/''
    const _isBool = activeField?.schema_type === 'boolean';
    const _on = _isBool ? '1' : 'Y';
    const _off = _isBool ? '0' : '';
    return (
      <label className="flex items-center justify-center h-row cursor-pointer">
        <input
          type="checkbox"
          className="w-4 h-4 accent-accent cursor-pointer"
          checked={checked}
          onChange={() => { onChange(checked ? _off : _on); setTimeout(onSave, 0); }}
          onKeyDown={handleKeyDown}
          autoFocus
          disabled={saving}
        />
      </label>
    );
  }

  if (isSelect) {
    if ((hasPrimeKey && pkItems === null) || (isSqlItems && sqlItems === null)) {
      return <span className="text-xs text-muted px-1">로딩...</span>;
    }
    return (
      <select
        className="w-full h-row text-xs bg-surface border border-accent rounded px-0.5 text-primary focus:outline-none"
        value={value}
        onChange={e => { const v = e.target.value; pickedRef.current = true; onChange(v); setTimeout(() => onSave(null, v), 50); }}
        // 선택 없이 셀을 벗어나면 편집 종료 (값 선택은 onChange 가 처리하므로 그때는 취소하지 않음)
        onBlur={() => { if (!pickedRef.current) onCancel(); }}
        onKeyDown={handleKeyDown}
        autoFocus
        disabled={saving}
      >
        <option value="">-</option>
        {items.map(o => <option key={o.value} value={o.value}>{o.text}</option>)}
      </select>
    );
  }

  // textarea: 다중줄 편집 — 셀 표시 크기와 동일한 범위(min 26px, max 130px) 유지
  // Enter 는 줄바꿈, Ctrl/Shift+Enter 또는 Esc/Blur 로 저장/취소
  if (ctl === 'textarea') {
    const taKeyDown = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); onCancel(); return; }
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey || e.shiftKey)) {
        e.preventDefault();
        onSave(e.shiftKey ? 'up' : 'down');
      }
      // 그 외 Enter 는 textarea 기본 동작(줄바꿈) 유지
    };
    return (
      <textarea
        rows={6}
        className="w-full text-xs bg-surface border border-accent rounded px-1 py-0.5 text-primary focus:outline-none resize-none"
        style={{
          height: '130px',
          minHeight: '130px',
          maxHeight: '130px',
          whiteSpace: 'pre-line',
          verticalAlign: 'top',
          overflow: 'auto',
          boxSizing: 'border-box',
          display: 'block',
        }}
        value={value}
        onChange={e => onChange(e.target.value)}
        onBlur={() => onSave()}
        onKeyDown={taKeyDown}
        autoFocus
        disabled={saving}
        maxLength={field.max_length ? (Math.abs(parseInt(field.max_length, 10)) || undefined) : undefined}
      />
    );
  }

  return (
    <input
      type={isDate ? 'date' : 'text'}
      className="w-full h-row text-xs bg-surface border border-accent rounded px-0.5 text-primary focus:outline-none"
      value={value}
      onChange={e => onChange(e.target.value)}
      onBlur={() => onSave()}
      onKeyDown={handleKeyDown}
      autoFocus
      disabled={saving}
      maxLength={field.max_length ? parseInt(field.max_length, 10) : undefined}
    />
  );
}

/**
 * 웹소스상세 의 schema_type 포맷 (v6 호환): "<type>^^<format>"
 *   number^^#,##0       → 888,888,888
 *   number^^#,##0.00 $  → 999,999,999.00 $
 *   number^^#,##0 원    → 8,888원
 *   number^^0000년      → 1985년 (4자리 zero-pad + 접미사)
 *   date^^MM-dd         → 12-30
 *   date^^yyyy-MM-dd    → 2026-12-30
 *   datetime^^yyyy-MM-dd HH:mm
 */
export function formatBySchema(val, schemaType) {
  if (val === null || val === undefined || val === '') return '';
  if (typeof schemaType !== 'string') return String(val);
  const sepIdx = schemaType.indexOf('^^');
  if (sepIdx < 0) return String(val);
  const type = schemaType.slice(0, sepIdx).trim().toLowerCase();
  const fmt  = schemaType.slice(sepIdx + 2);

  if (type === 'number') {
    const num = parseFloat(val);
    if (isNaN(num)) return String(val);
    // 첫 숫자 포맷 토큰만 치환 (나머지 텍스트는 suffix/prefix 로 보존)
    return fmt.replace(/[#,0]+(?:\.0+)?/, (token) => {
      const [intTok, decTok] = token.split('.');
      const dec        = decTok ? decTok.length : 0;
      const hasComma   = intTok.includes(',');
      const isZeroPad  = /^0+$/.test(intTok); // '0000' 처럼 0 만
      if (isZeroPad && dec === 0) {
        return String(Math.trunc(num)).padStart(intTok.length, '0');
      }
      return num.toLocaleString('en-US', {
        minimumFractionDigits: dec,
        maximumFractionDigits: dec,
        useGrouping: hasComma,
      });
    });
  }

  if (type === 'date' || type === 'datetime') {
    const s = String(val);
    if (s === '0000-00-00' || s.startsWith('0000-00-00')) return '';
    let d;
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) d = new Date(s.replace(' ', 'T'));
    else d = new Date(s);
    if (isNaN(d.getTime())) return s;
    const map = {
      yyyy: d.getFullYear(),
      MM: String(d.getMonth() + 1).padStart(2, '0'),
      dd: String(d.getDate()).padStart(2, '0'),
      HH: String(d.getHours()).padStart(2, '0'),
      mm: String(d.getMinutes()).padStart(2, '0'),
      ss: String(d.getSeconds()).padStart(2, '0'),
    };
    return fmt.replace(/yyyy|MM|dd|HH|mm|ss/g, (t) => String(map[t]));
  }

  return String(val);
}

function CellValue({ val, html, schemaType }) {
  // __html 우선: 표시용 HTML이 있으면 렌더링 (원본 데이터는 보존)
  if (html !== undefined && html !== null && html !== '') {
    return <span className="text-primary cell-html" dangerouslySetInnerHTML={{ __html: String(html) }} />;
  }
  if (val === null || val === undefined || val === '') {
    return <span className="text-muted">-</span>;
  }
  const s = String(val);
  if (schemaType === 'html') {
    return <span className="text-primary cell-html" dangerouslySetInnerHTML={{ __html: s }} />;
  }
  // ^^ 포맷 (number / date with custom mask)
  if (typeof schemaType === 'string' && schemaType.includes('^^')) {
    const formatted = formatBySchema(val, schemaType);
    return <span className="text-primary tabular-nums" title={s}>{formatted}</span>;
  }
  if ((schemaType === 'datetime' || schemaType === 'date') && s.length >= 10) {
    return <span className="text-primary tabular-nums">{s.slice(0, schemaType === 'date' ? 10 : 16)}</span>;
  }
  return <span className="text-primary" title={s}>{s}</span>;
}

function PagerBtn({ label, active, disabled, onClick }) {
  return (
    <button
      className={[
        'min-w-[28px] h-btn-sm px-2 text-sm rounded border transition-colors',
        active
          ? 'bg-accent border-accent text-white font-semibold'
          : 'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
        disabled ? 'opacity-40 cursor-default' : 'cursor-pointer',
      ].join(' ')}
      disabled={disabled}
      onClick={onClick}
    >
      {label}
    </button>
  );
}

const clickModeCls = [
  'w-7 h-btn-sm flex items-center justify-center rounded border border-solid cursor-pointer transition-colors',
  'border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const clickModeActiveCls = [
  'w-7 h-btn-sm flex items-center justify-center rounded border border-solid cursor-pointer',
  'bg-accent border-accent text-white',
].join(' ');

const thCls = [
  'sticky top-0 z-10 relative',
  'h-row px-3 text-left bg-surface-2',
  'text-xs font-bold uppercase tracking-wide text-primary',
  'border border-solid border-border-base',
  'cursor-pointer select-none whitespace-nowrap overflow-hidden',
].join(' ');

// 그룹 라벨 띠 — 컬럼 헤더(bg-surface-2)와 다른 톤 (밝은 surface, 가운데정렬, 작은 글자)
const groupBandThCls = [
  'sticky top-0 z-10',
  'h-7 px-2 text-center bg-surface',
  'text-[11px] font-semibold tracking-wide text-secondary',
  'border border-solid border-border-base',
  'select-none whitespace-nowrap overflow-hidden',
].join(' ');

// 2-row 헤더의 하단 컬럼 행 — sticky top 은 상단 띠 높이(28px)만큼 밀림
const subThCls = [
  'sticky z-10 relative',
  'h-row px-3 text-left bg-surface-2',
  'text-xs font-bold uppercase tracking-wide text-primary',
  'border border-solid border-border-base',
  'cursor-pointer select-none whitespace-nowrap overflow-hidden',
  'top-7',
].join(' ');

const tdCls = 'px-1.5 h-row align-middle border-b border-r border-solid border-grid-line';

const filterInputCls = [
  'h-btn-sm px-2 text-sm rounded border border-solid border-border-base',
  'bg-surface text-primary outline-none',
  'focus:border-accent transition-colors',
].join(' ');

const recentlyOnCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid border-accent cursor-pointer transition-colors',
  'bg-accent text-white',
].join(' ');

const recentlyOffCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid border-border-base cursor-pointer transition-colors',
  'bg-surface text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const resetBtnCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid border-border-base cursor-pointer transition-colors',
  'bg-surface text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const urlBtnCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid border-border-base cursor-pointer transition-colors',
  'bg-surface text-muted hover:bg-surface-2 hover:text-secondary',
].join(' ');

const excelBtnCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid cursor-pointer transition-colors',
  'bg-surface border-success text-success hover:bg-success-dim',
].join(' ');

const printBtnCls = [
  'h-btn-sm px-3 text-sm rounded border border-solid cursor-pointer transition-colors',
  'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const viewSizeCls = [
  'w-7 h-btn-sm text-sm rounded border border-solid cursor-pointer transition-colors',
  'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const viewSizeActiveCls = [
  'w-7 h-btn-sm text-sm rounded border border-solid cursor-pointer transition-colors',
  'bg-accent border-accent text-white font-semibold',
].join(' ');

// 패널 닫힌 상태에서의 크기 선택 버튼 (비활성 강조)
const viewSizeDimActiveCls = [
  'w-7 h-btn-sm text-sm rounded border border-solid cursor-pointer transition-colors',
  'bg-surface-2 border-border-base text-primary font-semibold underline',
].join(' ');

const moreCls = [
  'w-8 h-btn-sm text-base rounded border border-solid cursor-pointer transition-colors leading-none',
  'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const moreActiveCls = [
  'w-8 h-btn-sm text-base rounded border border-solid cursor-pointer transition-colors leading-none',
  'bg-surface-2 border-border-base text-primary',
].join(' ');

const dropItemCls = [
  'w-full text-left px-3 h-btn-sm text-sm rounded cursor-pointer transition-colors whitespace-nowrap border-0',
  'bg-transparent text-secondary hover:bg-surface-2 hover:text-primary',
].join(' ');

const btnEditCls = [
  'mr-1 px-2 h-btn-sm text-xs rounded border border-solid cursor-pointer',
  'bg-surface border-accent text-link',
  'hover:bg-accent-dim transition-colors',
].join(' ');

const btnDelCls = [
  'px-2 h-btn-sm text-xs rounded border border-solid cursor-pointer',
  'bg-surface border-danger text-danger',
  'hover:bg-danger-dim transition-colors',
].join(' ');
