import React, { useState, useEffect, useRef, useMemo, useCallback, lazy, Suspense } from 'react';
import { createPortal } from 'react-dom';
import api, { apiPath } from '../api';
import SearchableSelect, { SEARCHABLE_THRESHOLD } from './SearchableSelect';
import { showToast } from './Toast';
import { formatBySchema } from './DataGrid';

const MonacoEditor = lazy(() => import('@monaco-editor/react'));
const GanttChartLazy = lazy(() => import('./GanttChart'));

/** grid_align → flex 컨테이너용 justify 클래스 (text-align은 flex 안에서 무시되므로 별도 필요) */
function flexAlign(align) {
  if (align === 'center') return ' justify-center';
  if (align === 'right')  return ' justify-end';
  return '';
}

function AlimTooltip({ text }) {
  const [open, setOpen] = useState(false);
  const [pos, setPos]   = useState({ top: 0, left: 0 });
  const btnRef = useRef(null);

  useEffect(() => {
    if (!open) return;
    function close(e) {
      if (!btnRef.current?.contains(e.target)) setOpen(false);
    }
    document.addEventListener('mousedown', close);
    document.addEventListener('touchstart', close);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('touchstart', close);
    };
  }, [open]);

  function toggle(e) {
    e.stopPropagation();
    if (!open) {
      const r = btnRef.current.getBoundingClientRect();
      setPos({ top: r.top - 8, left: r.left + r.width / 2 });
    }
    setOpen(v => !v);
  }

  return (
    <>
      <span
        ref={btnRef}
        onClick={toggle}
        className="inline-flex items-center justify-center w-4 h-4 rounded-full bg-accent text-white text-[10px] font-bold cursor-pointer select-none leading-none flex-shrink-0"
      >?</span>
      {open && createPortal(
        <div
          className="fixed z-[9999] px-3 py-2 rounded shadow-lg text-xs leading-relaxed text-white whitespace-pre-wrap max-w-[260px]"
          style={{ background: '#1e2a35', top: pos.top, left: pos.left, transform: 'translate(-50%, -100%)' }}
        >
          {text}
          <span className="absolute top-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-t-[#1e2a35]" />
        </div>,
        document.body
      )}
    </>
  );
}

const ReactQuill = lazy(() =>
  Promise.all([
    import('react-quill'),
    import('react-quill/dist/quill.snow.css'),
  ]).then(([m]) => ({ default: m.default }))
);

/** SQL 가독성 포맷: 주요 절 앞에 줄바꿈 (서버 포맷 SQL은 그대로 반환) */
function formatSQL(sql) {
  if (!sql) return sql;
  // 서버에서 이미 포맷된 SQL (줄바꿈 또는 주석 포함)
  if (sql.includes('\n') || sql.trimStart().startsWith('--')) return sql.trim();
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

function buildCopyText(devSql) {
  return ['-- 1. SELECT', formatSQL(buildCompleteSQL(devSql.sql, devSql.bindings)) + ';'].join('\n');
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

const DEFAULT_TAB = '기본폼';

// 반응형 브레이크포인트 — 폼 컨테이너 내부 폭(panelW) 기준
// Bootstrap 5 표준:  XS <576 / SM ≥576 / MD ≥768 / LG ≥992 / XL ≥1200
const BP = { sm: 576, md: 768, lg: 992, xl: 1200 };

function intOrNull(v) {
  if (v === null || v === undefined || v === '') return null;
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : null;
}

// grid_view_class 파싱 — "col-xxs-N col-xs-N col-sm-N col-md-N col-lg-N col-xl-N row-N"
function parseViewClass(cls) {
  const r = { xxs: null, xs: null, sm: null, md: null, lg: null, xl: null, height: null, isMaxHeight: false };
  if (!cls) return r;
  for (const p of String(cls).split(/\s+/)) {
    const m = p.match(/^col-(xxs|xs|sm|md|lg|xl)-(\d+)$/);
    if (m) r[m[1]] = parseInt(m[2], 10);
    const h = p.match(/^row-(\d+)$/);
    if (h) {
      const n = parseInt(h[1], 10);
      if (n >= 52) { r.height = n - 51; r.isMaxHeight = true; }
      else          { r.height = n;      r.isMaxHeight = false; }
    }
  }
  return r;
}

// 개별 컬럼(grid_view_sm/md/lg/xl) 우선, 없으면 grid_view_class 파싱값 사용
// XS 는 개별 컬럼 없음 → grid_view_class 내부 xs/xxs 만 사용
// panelW ≤ 375 (모바일 소형) 은 무조건 100% (span=12)
function getSpan(vc, f, w) {
  if (w <= 375) return 12;
  const xs = vc.xs ?? vc.xxs;
  const sm = intOrNull(f.grid_view_sm) ?? vc.sm;
  const md = intOrNull(f.grid_view_md) ?? vc.md;
  const lg = intOrNull(f.grid_view_lg) ?? vc.lg;
  const xl = intOrNull(f.grid_view_xl) ?? vc.xl;
  let span = xs ?? sm ?? md ?? lg ?? xl ?? 12;
  if (w >= BP.sm && sm != null) span = sm;
  if (w >= BP.md && md != null) span = md;
  if (w >= BP.lg && lg != null) span = lg;
  if (w >= BP.xl && xl != null) span = xl;
  return Math.min(Math.max(span, 1), 12);
}

// 높이 해석: 개별 컬럼 grid_view_hight 우선 / 없으면 grid_view_class 의 row-N 폴백
function parseHeight(f, vc) {
  const h = intOrNull(f.grid_view_hight);
  if (h != null) {
    if (h >= 52) return { rows: h - 51, isMaxHeight: true };
    return { rows: Math.max(1, h), isMaxHeight: false };
  }
  if (vc.height != null) {
    return { rows: Math.max(1, vc.height), isMaxHeight: vc.isMaxHeight };
  }
  return { rows: 1, isMaxHeight: false };
}

function formLabel(colTitle, aliasName) {
  const s = colTitle ?? aliasName ?? '';
  const ci = s.indexOf(',');
  return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || aliasName;
}

export default function DataForm({ gubun, idx, mode, user, onSaved, onCancel, onModify, onDelete,
                                   activeTab: activeTabProp, onTabChange, onTabsChange, onSqlBtn,
                                   onSaveSql, filterGroups = null, hideActions = false, menuReadOnly = false }) {
  const [fields,   setFields]   = useState([]);
  const [values,   setValues]   = useState({});
  // textdecrypt2: 저장 포함 alias Set ("체크후 저장가능" 체크된 필드만 저장 대상)
  const [decryptEnabled, setDecryptEnabled] = useState(() => new Set());
  // 첨부파일 임시 토큰: { alias: [token, token, ...] }
  const [tempAttach, setTempAttach] = useState({});
  const [loading,  setLoading]  = useState(mode !== 'write');
  const [saving,   setSaving]   = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error,    setError]    = useState('');
  const [devSql,       setDevSql]       = useState(null);
  const [showSqlBtn,   setShowSqlBtn]   = useState(false);
  const [sqlModalOpen, setSqlModalOpen] = useState(false);
  const [printHtml,   setPrintHtml]   = useState(null);
  // 서버 훅 _client_formButtons 로 주입되는 폼 상단 버튼 [{label, realPid, gubun, idx, openFull}]
  const [formButtons, setFormButtons] = useState(null);
  const [saveAndNew, setSaveAndNew] = useState(false);
  // 서버 훅 _client_belowForm — 폼 하단 패널 (sibling 이력 등)
  const [belowForm, setBelowForm] = useState(null);
  const submitModeRef = useRef('normal');
  // 서버 훅 row_buttons 로 주입되는 필드별 버튼 HTML 맵 {alias_name: '<button>...</button>...'}
  const [fieldButtons, setFieldButtons] = useState({});
  const devMode = localStorage.getItem('mis_dev_mode') === '1';
  // tabid 해석: activeTabProp(URL ?tabid 또는 menu.add_url 의 tabid) 이 tab label 직접매치 안 되면
  // 필드 alias_name(대소문자 정확) 으로 form_group 찾아서 변환. gubun 당 1회만 발화.
  const didResolveTabidRef = useRef(false);
  useEffect(() => { didResolveTabidRef.current = false; }, [gubun]);
  // 외부에서 폼 데이터만 다시 조회시키는 이벤트 — addLogic_treat 의 reloadView 가 dispatch
  // (풀-리로드 location.reload() 대신 act=view 만 재호출해서 폼 상태/탭 유지)
  const [reloadKey, setReloadKey] = useState(0);
  useEffect(() => {
    const onReload = (e) => {
      const d = e.detail || {};
      // 다른 폼 인스턴스에 영향 안 미치게 gubun/idx 매치 (detail 없으면 모두 매치)
      if ((d.gubun != null && Number(d.gubun) !== Number(gubun)) ||
          (d.idx   != null && String(d.idx)   !== String(idx))) return;
      setReloadKey(k => k + 1);
    };
    window.addEventListener('mis:reloadView', onReload);
    return () => window.removeEventListener('mis:reloadView', onReload);
  }, [gubun, idx]);
  useEffect(() => {
    if (didResolveTabidRef.current) return;
    if (!fields || fields.length === 0) return;
    if (!activeTabProp || activeTabProp === '기본폼') return;
    const normTab = g => {
      let s = (g ?? '').trim();
      if (s.endsWith('!')) s = s.slice(0, -1).trim();
      return (s && s !== 'Y') ? s : '기본폼';
    };
    // 이미 tab label 자체면 변환 불필요
    const labels = new Set(fields.map(f => normTab(f.form_group)));
    if (labels.has(activeTabProp)) { didResolveTabidRef.current = true; return; }
    // alias_name 정확매치 → form_group 으로 변환
    const fld = fields.find(f => (f.alias_name ?? '') === activeTabProp);
    if (fld) {
      const resolved = normTab(fld.form_group);
      if (resolved && resolved !== activeTabProp) {
        didResolveTabidRef.current = true;
        onTabChange?.(resolved);
      }
    }
  }, [activeTabProp, fields, onTabChange]);

  // 패널(컨테이너) 폭 추적 — callback ref로 처리 (loading 중엔 form 미렌더)
  const containerRef = useRef(null);
  const resizeObsRef = useRef(null);
  const [panelW, setPanelW] = useState(600);

  // 뷰 디자이너 iframe 등 외부 리스너용: panelW 변경 브로드캐스트
  useEffect(() => {
    try {
      window.dispatchEvent(new CustomEvent('mis:panelWidthChange', { detail: { width: panelW } }));
    } catch {}
  }, [panelW]);

  // 그룹별 섹션 DOM ref (스크롤 이동용)
  const groupRefsMap = useRef({});

  const formRefCallback = useCallback(el => {
    if (resizeObsRef.current) {
      resizeObsRef.current.disconnect();
      resizeObsRef.current = null;
    }
    containerRef.current = el;
    if (!el) return;
    setPanelW(el.offsetWidth);
    const obs = new ResizeObserver(entries => {
      for (const e of entries) setPanelW(e.contentRect.width);
    });
    obs.observe(el);
    resizeObsRef.current = obs;
  }, []);

  // SQL 버튼 상태 변경 시 부모에게 알림 + 8초 자동 숨김
  useEffect(() => {
    onSqlBtn?.(showSqlBtn, () => setSqlModalOpen(true));
    if (!showSqlBtn) return;
    const t = setTimeout(() => setShowSqlBtn(false), 8000);
    return () => clearTimeout(t);
  }, [showSqlBtn, onSqlBtn]);

  useEffect(() => () => { onSqlBtn?.(false, null); }, [onSqlBtn]);

  const readOnly = mode === 'view';
  // 서버에서 계산된 행 단위 읽기전용 플래그 (read_only_cond 평가 결과 1)
  const rowReadOnly = values && (values.__readonly === 1 || values.__readonly === '1' || values.__readonly === true);

  // max_length 가 ! 로 끝나는 필드 — 행 단위 readonly 를 무시하고 편집 허용 (쓰기권한 있을 때만).
  // 첨부/이미지(attach/image) 는 ! 가 multi-attach 의미라서 override 대상이 아님.
  const isOverrideField = (f) => {
    const ml = String(f?.max_length ?? '');
    if (!ml.endsWith('!')) return false;
    const ctl = f?.grid_ctl_name ?? '';
    return ctl !== 'attach' && ctl !== 'image';
  };

  useEffect(() => {
    const applyFields = (flds) => {
      setFields(flds);
      // form_group 끝의 '!' = 전용탭 (기본폼에선 숨김, 해당 탭 클릭해야만 표시)
      const normalize = g => {
        let s = (g ?? '').trim();
        if (s.endsWith('!')) s = s.slice(0, -1).trim();
        return (s && s !== 'Y') ? s : DEFAULT_TAB;
      };
      const isDedicated = g => (g ?? '').trim().endsWith('!');

      const sortedFlds = [...flds].sort((a, b) =>
        (parseInt(a.sort_order ?? '0', 10)) - (parseInt(b.sort_order ?? '0', 10))
      );
      const seen = new Set();
      const unifiedTabs = [];
      for (const f of sortedFlds) {
        if (f.grid_ctl_name === 'child' && f.default_value) {
          const realPid = String(f.default_value).trim();
          const key = `child:${realPid}`;
          if (!seen.has(key)) {
            seen.add(key);
            unifiedTabs.push({ type: 'child', label: formLabel(f.col_title, f.alias_name), realPid });
          }
        } else if (parseInt(f.col_width ?? '0', 10) >= 0) {
          const group = normalize(f.form_group);
          const key = `form:${group}`;
          if (!seen.has(key)) {
            seen.add(key);
            unifiedTabs.push({ type: 'form', label: group, dedicated: isDedicated(f.form_group) });
          }
        }
      }
      onTabsChange?.(unifiedTabs);
      // tabid → form_group 변환은 별도 useEffect 에서 처리 (activeTabProp/fields 변화 감지용)
    };

    if (mode === 'write') {
      api.list(gubun, { pageSize: 1, actionFlag: 'write' })
        .then(data => {
          const flds = data.fields ?? [];
          applyFields(flds);
          const defaults = {};
          flds.forEach(f => { if (f.default_value) defaults[f.alias_name] = f.default_value; });
          // referInsert 로 진입한 경우: sessionStorage 에 저장된 참조 record 의 값으로 prefill
          try {
            const prefillKey = `mis_referInsert_${gubun}`;
            const stored = sessionStorage.getItem(prefillKey);
            if (stored) {
              const prefill = JSON.parse(stored);
              Object.assign(defaults, prefill);
              sessionStorage.removeItem(prefillKey);
            }
          } catch {}
          setValues(defaults);
          // write 모드: textdecrypt2 필드는 자동 enable — 신규 사용자는 비번 필수 입력이라
          // '체크후 저장가능' 체크박스를 명시적으로 누르지 않아도 input 이 활성화 + required 검증 정상 작동.
          // (modify 모드는 그대로 default unchecked — 변경 의도일 때만 enable)
          const writeEnabled = new Set();
          flds.forEach(f => { if (f.grid_ctl_name === 'textdecrypt2') writeEnabled.add(f.alias_name); });
          setDecryptEnabled(writeEnabled);
          setSaveAndNew(!!data._client_saveAndNew);
          if (data._client_alert) alert(data._client_alert);
          if (data._client_toast) showToast(data._client_toast);
        })
        .catch(() => {});
      return;
    }
    // idx 가 null/undefined/빈문자열/0 이면 호출 스킵 — (undefined <= 0) 은 false 이므로 명시 체크 필요
    if (idx === undefined || idx === null || idx === '' || idx === 0 || idx === '0') return;
    setLoading(true);
    // view 응답에 fields 가 포함되어 별도 act=list 호출 불필요 (대용량 테이블에서 list pageSize=1 이
    // SELECT 전체를 빌드하던 비용 제거).
    api.view(gubun, idx, devMode, mode).then(viewData => {
      const fields = viewData.fields ?? [];
      // textdecrypt2 alias 값은 view/modify 진입 시 빈값으로 초기화
      // (DB 의 비번값을 폼에 노출하지 않음 — modify 모드에서 체크박스 활성 후 신규 입력만 받음)
      const initValues = { ...(viewData.data ?? {}) };
      fields.forEach(f => {
        if (f.grid_ctl_name === 'textdecrypt2') initValues[f.alias_name] = '';
      });
      setValues(initValues);
      applyFields(fields);
      setPrintHtml(viewData.printHtml ?? null);
      setFormButtons(viewData._client_formButtons ?? null);
      setBelowForm(viewData._client_belowForm ?? null);
      setSaveAndNew(!!viewData._client_saveAndNew);
      setFieldButtons(viewData._field_buttons ?? {});
      if (viewData._sql || viewData._execSql) { setDevSql({ sql: viewData._sql, bindings: viewData._bindings, execSql: viewData._execSql ?? null }); setShowSqlBtn(true); }
      if (viewData._client_alert) alert(viewData._client_alert);
      if (viewData._client_toast) showToast(viewData._client_toast);
      if (viewData._client_openTab) {
        const t = viewData._client_openTab;
        window.dispatchEvent(new CustomEvent('mis:openTab', { detail: { gubun: t.gubun, label: t.label ?? '', idx: t.idx ?? 0, linkVal: t.linkVal ?? t.idx ?? 0, openFull: !!t.openFull } }));
      }
      setLoading(false);
    }).catch(e => {
      setError(e.message);
      setLoading(false);
    });
  }, [gubun, idx, mode, reloadKey]);

  // activeTab 변경 또는 로딩 완료 → 해당 섹션으로 스크롤
  // scrollIntoView 는 그리드 등 상위 레이아웃까지 스크롤시키므로
  // form 을 감싸는 overflow-auto 컨테이너를 직접 찾아 scrollTop 조정
  // loading 의존성 추가: 초기 로딩 시 ref 가 아직 없는 시점에 useEffect 가 한 번만 실행되어
  // 스크롤이 누락되는 문제 방지 (팝업 진입 직후 ?tabid=도움말 같은 케이스)
  useEffect(() => {
    if (loading) return;
    if (!activeTabProp || activeTabProp.startsWith('child-')) return;
    const raf = requestAnimationFrame(() => {
      const el = groupRefsMap.current[activeTabProp];
      if (!el) return;
      // 가장 가까운 overflow-auto/scroll 조상 찾기
      let container = el.parentElement;
      while (container && container !== document.body) {
        const { overflowY } = getComputedStyle(container);
        if (overflowY === 'auto' || overflowY === 'scroll') break;
        container = container.parentElement;
      }
      // groupRefsMap 등록 순서 = sort_order 순 = 폼 렌더 순서. 첫 키 = 첫 번째 그룹.
      // 첫 번째 그룹: 컨테이너 맨 위 (scrollTop=0) — 폼 상단 '연결' 버튼 보존, 깜빡임 없음.
      // 두 번째 이후 그룹: 헤더 기준 점프 + 깜빡임.
      const groupKeys = Object.keys(groupRefsMap.current);
      const isFirstGroup = groupKeys[0] === activeTabProp;

      if (container && container !== document.body) {
        if (isFirstGroup) {
          container.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          const top = el.offsetTop - container.offsetTop - 15;
          container.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }
      }
      if (!isFirstGroup) {
        // 그룹 타이틀 깜빡임 효과 (두 번째 이후 그룹만)
        el.classList.add('flash-highlight');
        setTimeout(() => el.classList.remove('flash-highlight'), 1200);
      }
    });
    return () => cancelAnimationFrame(raf);
  }, [activeTabProp, loading]);

  const formFields = fields.length > 0
    ? fields.filter(f => parseInt(f.col_width ?? '0', 10) >= 0 && f.grid_ctl_name !== 'child')
    : Object.keys(values).filter(k => k !== 'idx').map(k => ({
        alias_name: k, col_title: k, schema_type: 'text', grid_ctl_name: '',
      }));

  // Qn display 필드 분석 + dropdownlist 숨김 처리:
  // 1. table_XXXQnYYY → display필드에 selectbox, XXX(value) 숨김
  // 2. grid_ctl_name=dropdownlist + prime_key → 직전 display필드에 selectbox, 자신 숨김
  // ※ view(읽기전용) 모드에서는 selectbox 가 활성화되지 않으므로 value 필드를 함께 노출
  //    (작성자명 + 작성자사번 둘 다 보이게) — 편집 모드에서만 숨김
  // ※ value 필드의 col_width=-1 (코드 숨김) 이라도 prev display 위치에는 selectbox 가 떠야 하므로
  //    매칭은 formFields(>=0) 가 아닌 fields 전체에서 검사한다.
  const qnDisplayToValue = {};  // displayAlias → valueAlias
  const qnHiddenAliases  = new Set();  // 숨길 value 필드 alias 목록
  const hideValueFields = mode !== 'view';
  fields.forEach((f, i) => {
    // 패턴1: Qn alias
    const valueAlias = parseQnAlias(f.alias_name ?? '');
    if (valueAlias) {
      const valueField = fields.find(vf => vf.alias_name === valueAlias && vf.prime_key);
      if (valueField) {
        qnDisplayToValue[f.alias_name] = valueAlias;
        if (hideValueFields) qnHiddenAliases.add(valueAlias);
      }
      return;
    }
    // 패턴2: dropdownlist + prime_key → 직전 필드가 display
    if ((f.grid_ctl_name === 'dropdownlist' || f.grid_ctl_name === 'dropdownitem') && f.prime_key && i > 0) {
      const prev = fields[i - 1];
      // 직전 필드가 JOIN 테이블(table_m이 아닌)이면 display 필드
      if (prev && prev.db_table && prev.db_table !== 'table_m') {
        qnDisplayToValue[prev.alias_name] = f.alias_name;
        if (hideValueFields) qnHiddenAliases.add(f.alias_name);
      }
    }
  });

  // value 필드 alias → display 필드 역방향 매핑
  // (숨겨진 value 필드의 필수표시·검증 메시지를 보이는 display 필드 쪽으로 옮기기 위함)
  const qnValueToDisplay = {};
  Object.entries(qnDisplayToValue).forEach(([disp, valAlias]) => {
    qnValueToDisplay[valAlias] = disp;
  });

  // form_group 끝의 '!' = 전용탭 — 기본폼에선 숨겨지고, 해당 탭 클릭해야만 노출
  const normalizeGroup = g => {
    let s = (g ?? '').trim();
    if (s.endsWith('!')) s = s.slice(0, -1).trim();
    return (s && s !== 'Y') ? s : DEFAULT_TAB;
  };
  const isDedicatedGroup = g => (g ?? '').trim().endsWith('!');

  const groups = [];
  const dedicatedGroupSet = new Set();
  formFields.forEach(f => {
    const g = normalizeGroup(f.form_group);
    if (!groups.includes(g)) groups.push(g);
    if (isDedicatedGroup(f.form_group)) dedicatedGroupSet.add(g);
  });

  // filterGroups 가 지정된 경우 해당 그룹만 표시
  let displayGroups = (filterGroups && filterGroups.length > 0)
    ? groups.filter(g => filterGroups.includes(g))
    : groups;

  // 활성 탭이 전용탭이면 그 그룹만 / 아니면 전용탭 그룹들은 모두 숨김
  if (dedicatedGroupSet.has(activeTabProp)) {
    displayGroups = [activeTabProp];
  } else {
    displayGroups = displayGroups.filter(g => !dedicatedGroupSet.has(g));
  }

  const multiGroup = displayGroups.length > 1;

  // PK(key) alias 결정 — 수정 모드에서 편집 불가 처리용
  // sort_order 기준 첫 번째가 col_width=-1 이면 두 번째가 visible key
  const pkAlias = (() => {
    if (fields.length === 0) return 'idx';
    const first = fields[0];
    const w = parseInt(first.col_width ?? '0', 10);
    if (w === -1 || w === -2) return fields[1]?.alias_name ?? first.alias_name ?? 'idx';
    return first.alias_name ?? 'idx';
  })();

  // 코드 에디터 / 간트차트 그룹 감지 (전체 높이 사용)
  const codeGroupSet = new Set();
  const ganttGroupSet = new Set();
  formFields.forEach(f => {
    if (f.schema_validation === 'code') codeGroupSet.add(normalizeGroup(f.form_group));
    if (f.schema_validation === 'gantt') ganttGroupSet.add(normalizeGroup(f.form_group));
  });
  const activeIsCode = codeGroupSet.has(activeTabProp);
  const activeIsGantt = ganttGroupSet.has(activeTabProp);

  // 폼 더티 상태 추적 — beforeunload 경고용 전역 카운터
  const dirtyRef = useRef(false);
  const markDirty = () => {
    if (dirtyRef.current) return;
    dirtyRef.current = true;
    if (typeof window.__misFormDirtyCount !== 'number') window.__misFormDirtyCount = 0;
    window.__misFormDirtyCount++;
  };
  const clearDirty = () => {
    if (!dirtyRef.current) return;
    dirtyRef.current = false;
    window.__misFormDirtyCount = Math.max(0, (window.__misFormDirtyCount ?? 1) - 1);
  };
  // 언마운트 시 더티였으면 카운터 정리 (저장/취소 없이 탭 닫힘 등)
  useEffect(() => () => clearDirty(), []); // eslint-disable-line react-hooks/exhaustive-deps
  // 모드 변경(수정→조회) 시 더티 리셋
  useEffect(() => { clearDirty(); }, [mode, idx]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleChange(alias, val) {
    if (mode === 'write' || mode === 'modify') markDirty();
    setValues(prev => ({ ...prev, [alias]: val }));
  }

  // FileAttach → 부모로 임시 토큰 통지
  const handleTempAttachChange = useCallback((alias, tokens) => {
    setTempAttach(prev => {
      const cur = prev[alias] ?? [];
      // 동일 배열이면 setState 생략
      if (cur.length === tokens.length && cur.every((t, i) => t === tokens[i])) return prev;
      return { ...prev, [alias]: tokens };
    });
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    if (readOnly || saving) return;

    // 필수 입력 검증
    const missing = formFields.filter(f => {
      if (f.required !== 'Y') return false;
      const w = parseInt(f.col_width ?? '0', 10);
      if (w === -1 || w === -2) return false; // 숨김 필드 제외
      // textdecrypt2: write 시 일반 필수 체크 / modify 시 '체크후 저장가능' 체크된 경우만 체크
      if (f.grid_ctl_name === 'textdecrypt2' && mode === 'modify' && !decryptEnabled.has(f.alias_name)) return false;
      const v = values[f.alias_name];
      return v === undefined || v === null || String(v).trim() === '';
    });
    if (missing.length > 0) {
      const names = missing.map(f => {
        // 숨겨진 value 필드(selectbox)면 보이는 display 필드 제목으로 표기 (재직코드 → 재직구분)
        const dispAlias = qnValueToDisplay[f.alias_name];
        const df = dispAlias ? fields.find(x => x.alias_name === dispAlias) : null;
        return (df ? df.col_title : f.col_title) || f.alias_name;
      }).join(', ');
      setError(`필수 입력: ${names}`);
      return;
    }

    setSaving(true);
    setError('');
    try {
      const saveBody = { ...values };
      // textdecrypt2: 체크 안 된 암호화 필드는 저장 payload 에서 제외
      formFields.forEach(f => {
        if (f.grid_ctl_name === 'textdecrypt2' && !decryptEnabled.has(f.alias_name)) {
          delete saveBody[f.alias_name];
        }
      });
      // 첨부파일 임시 토큰 동봉 (서버에서 finalize)
      const validTempAttach = Object.fromEntries(
        Object.entries(tempAttach).filter(([, v]) => Array.isArray(v) && v.length > 0)
      );
      if (Object.keys(validTempAttach).length > 0) saveBody._tempAttach = validTempAttach;
      const res = await api.save(gubun, saveBody, mode === 'modify' ? idx : 0, devMode);

      // 서버 confirm 요청 → 사용자 확인 후 _confirmed 플래그 붙여 재전송
      if (res._confirm) {
        setSaving(false);
        if (!window.confirm(res._confirm)) return;
        setSaving(true);
        const res2 = await api.save(gubun, { ...saveBody, _confirmed: true }, mode === 'modify' ? idx : 0, devMode);
        if (res2._sql || res2._execSql) {
          onSaveSql?.({ sql: res2._sql, bindings: res2._bindings ?? [], execSql: res2._execSql ?? null });
        }
        if (res2._client_openTab) {
          const t = res2._client_openTab;
          window.dispatchEvent(new CustomEvent('mis:openTab', { detail: { gubun: t.gubun, label: t.label ?? '', idx: t.idx ?? 0, linkVal: t.linkVal ?? t.idx ?? 0, openFull: !!t.openFull } }));
        }
        clearDirty();
        // 사용자 정의 메시지 우선, 없으면 기본 '저장되었습니다.'
        if (res2._client_alert) alert(res2._client_alert);
        if (res2._client_toast) showToast(res2._client_toast);
        else if (!res2._client_alert) showToast('저장되었습니다.', 'success');
        const isPopup2 = new URLSearchParams(window.location.search).get('isPopup') === 'Y';
        if (isPopup2 && window.parent !== window) {
          try { window.parent.postMessage({ type: 'mis:closePopup', reloadParent: true }, '*'); } catch {}
        } else {
          const thenWrite2 = submitModeRef.current === 'thenWrite';
          submitModeRef.current = 'normal';
          onSaved(res2.idx, { stayOnModify: !!res2._stayOnModify, thenWrite: thenWrite2 });
        }
        return;
      }

      if (res._sql || res._execSql) {
        onSaveSql?.({ sql: res._sql, bindings: res._bindings ?? [], execSql: res._execSql ?? null });
      }
      if (res._client_openTab) {
        const t = res._client_openTab;
        window.dispatchEvent(new CustomEvent('mis:openTab', { detail: { gubun: t.gubun, label: t.label ?? '', idx: t.idx ?? 0, linkVal: t.linkVal ?? t.idx ?? 0, openFull: !!t.openFull } }));
      }
      clearDirty();
      // 사용자 정의 메시지 우선, 없으면 기본 '저장되었습니다.'
      if (res._client_alert) alert(res._client_alert);
      if (res._client_toast) showToast(res._client_toast);
      else if (!res._client_alert) showToast('저장되었습니다.', 'success');
      // 팝업 모드: 저장 성공 시 부모에 닫기 + 새로고침 요청
      const isPopup = new URLSearchParams(window.location.search).get('isPopup') === 'Y';
      if (isPopup && window.parent !== window) {
        try { window.parent.postMessage({ type: 'mis:closePopup', reloadParent: true }, '*'); } catch {}
      } else {
        const thenWrite = submitModeRef.current === 'thenWrite';
        submitModeRef.current = 'normal';
        onSaved(res.idx, { stayOnModify: !!res._stayOnModify, thenWrite });
      }
    } catch (ex) {
      setError(ex.message);
      if (ex._sqlData) {
        onSaveSql?.({ sql: ex._sqlData.sql, bindings: ex._sqlData.bindings ?? [], error: ex._sqlData.error });
      }
    } finally {
      setSaving(false);
    }
  }

  if (loading) return (
    <div className="p-10 text-center">
      <div className="skeleton h-4 w-48 rounded mx-auto mb-3" />
      <div className="skeleton h-4 w-64 rounded mx-auto mb-3" />
      <div className="skeleton h-4 w-56 rounded mx-auto" />
    </div>
  );

  // 액션 버튼 — 폼 상단/하단 동일 구성 (모든 speedmis 공통 요구사항)
  //   - renderEditActions: 수정/등록 모드 (저장 / 저장후 새로입력 / 취소·닫기)
  //   - renderViewActions: 조회 모드   (수정·부분수정 / 인쇄 / 삭제 + 읽기전용 뱃지)
  // 두 곳에서 같은 핸들러를 공유하므로 saving/deleting 상태가 양쪽에 즉시 반영됨.
  const renderEditActions = (wrapClass) => {
    const isPopup = new URLSearchParams(window.location.search).get('isPopup') === 'Y';
    return (
      <div className={wrapClass}>
        <button
          type="submit"
          disabled={saving}
          onClick={() => { submitModeRef.current = 'normal'; }}
          className="h-btn px-5 rounded bg-accent text-white text-base font-medium border-0 cursor-pointer disabled:opacity-50 hover:bg-accent-hover transition-colors flex items-center gap-2"
        >
          {saving && <span className="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin" />}
          {saving ? '저장 중...' : '저장'}
        </button>
        {saveAndNew && mode === 'modify' && (
          <button
            type="submit"
            disabled={saving}
            onClick={() => { submitModeRef.current = 'thenWrite'; }}
            className="h-btn px-5 rounded bg-surface border border-accent text-primary text-base font-medium cursor-pointer disabled:opacity-50 hover:bg-accent/10 transition-colors"
            title="저장 후 새로입력 모드로 전환"
          >저장후 새로입력</button>
        )}
        <button
          type="button"
          className="h-btn px-5 rounded bg-surface border border-border-base text-secondary text-base cursor-pointer hover:bg-surface-2 hover:text-primary transition-colors"
          onClick={() => {
            clearDirty();
            // 팝업(iframe) 모드면 부모창에 닫기 요청, 아니면 취소(폼 닫기)
            if (isPopup && window.parent !== window) {
              try { window.parent.postMessage({ type: 'mis:closePopup' }, '*'); } catch {}
            } else {
              onCancel?.();
            }
          }}
        >{isPopup ? '닫기' : '취소'}</button>
      </div>
    );
  };

  const renderViewActions = (wrapClass) => {
    // rowReadOnly 행이라도 max_length 가 ! 로 끝나는 필드(override) 가 있으면 수정 모드로 진입 가능 — 그 필드만 편집됨
    const _hasOverride = formFields.some(isOverrideField);
    const _showModify  = !rowReadOnly || _hasOverride;
    return (
      <div className={wrapClass}>
        {_showModify && (
          <button
            type="button"
            className="h-btn px-5 rounded bg-accent text-white text-base font-medium border-0 cursor-pointer hover:bg-accent-hover transition-colors"
            onClick={() => onModify?.()}
          >{rowReadOnly ? '부분수정' : '수정'}</button>
        )}
        {rowReadOnly && !_hasOverride && (
          <span className="text-xs px-2 py-1.5 rounded bg-surface-2 text-muted font-bold self-center">🔒 읽기전용 행</span>
        )}
        {rowReadOnly && _hasOverride && (
          <span className="text-xs px-2 py-1.5 rounded bg-surface-2 text-muted font-bold self-center">🔒 읽기전용 행 (일부 필드만 수정 가능)</span>
        )}
        {printHtml && (
          <button
            type="button"
            className="h-btn px-5 rounded bg-surface border border-border-base text-primary text-base cursor-pointer hover:bg-surface-2 transition-colors"
            onClick={() => {
              const w = window.open('', '_blank', 'width=900,height=700');
              if (!w) return;
              w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>인쇄</title>
                <style>body{font-family:Pretendard,sans-serif;padding:20px;font-size:13px;color:#191F28}
                table{border-collapse:collapse;width:100%} th,td{border:1px solid #E5E8EB;padding:6px 10px;text-align:left}
                th{background:#F5F6F8;font-weight:600} h1,h2,h3{margin:0 0 12px}
                .no-print{margin-top:24px;text-align:center}
                @media print{.no-print{display:none!important}}</style>
                </head><body>${printHtml}
                <div class="no-print">
                  <button onclick="window.print()" style="padding:10px 32px;font-size:15px;cursor:pointer;border:1px solid #E5E8EB;border-radius:10px;background:#4F6EF7;color:#fff;font-weight:600">🖨 인쇄하기</button>
                  <button onclick="window.close()" style="padding:10px 32px;font-size:15px;cursor:pointer;border:1px solid #E5E8EB;border-radius:10px;margin-left:8px;background:#fff;color:#4E5968;font-weight:600">닫기</button>
                </div></body></html>`);
              w.document.close();
              setTimeout(() => w.print(), 300);
            }}
          >🖨 인쇄</button>
        )}
        {onDelete && !rowReadOnly && (
          <button
            type="button"
            disabled={deleting}
            className="h-btn px-5 rounded bg-surface border border-danger text-danger text-base cursor-pointer disabled:opacity-50 hover:bg-danger-dim transition-colors flex items-center gap-2"
            onClick={async () => {
              if (!window.confirm('삭제하시겠습니까?')) return;
              setDeleting(true);
              try {
                const res = await api.delete(gubun, idx);
                // 사용자 정의 메시지 우선, 없으면 기본 '삭제되었습니다.'
                if (res?._client_alert) alert(res._client_alert);
                if (res?._client_toast) showToast(res._client_toast);
                else if (!res?._client_alert) showToast('삭제되었습니다.', 'success');
                onDelete?.();
              } catch (e) {
                showToast(e.message, 'error');
              } finally {
                setDeleting(false);
              }
            }}
          >
            {deleting && <span className="inline-block w-3 h-3 border-2 border-danger border-t-transparent rounded-full animate-spin" />}
            {deleting ? '삭제 중...' : '삭제'}
          </button>
        )}
      </div>
    );
  };

  const showEditActions = !readOnly && !hideActions && !menuReadOnly;
  const showViewActions =  readOnly && !hideActions && !menuReadOnly;

  return (
    <form onSubmit={handleSubmit} className={activeIsCode || activeIsGantt ? 'flex flex-col h-full' : ''}>
      {/* 에러 */}
      {error && (
        <div className="flex items-center gap-2 px-4 py-3 mb-3 rounded border border-danger bg-danger-dim text-danger text-base flex-shrink-0">
          {error}
        </div>
      )}

      {/* 액션 버튼 (상단) — 하단과 동일 구성 미러링 */}
      {showEditActions && renderEditActions("flex gap-2 mb-3 flex-shrink-0")}
      {showViewActions && renderViewActions("flex gap-2 mb-3 flex-shrink-0")}

      {/* 서버 주입 폼 버튼 (_client_formButtons)
          - b.action 있으면: treat API 호출 후 리스트로 복귀 (onDelete: 패널 닫기 + 목록 새로고침)
          - b.action 없으면: data-opentab → App.jsx 전역 위임이 탭 오픈 */}
      {formButtons && formButtons.length > 0 && (
        <div className="flex items-center gap-2 mb-2 flex-shrink-0">
          {formButtons.map((b, i) => {
            // referInsert / forwardPlan: 현재 폼 값을 prefill 로 보존 + 같은 탭에서 write 모드 전환
            //   forwardPlan 만 추가 변환: '향후계획(hyanghugyehoek)' → '내용(naeyong)' 이동 + 향후계획 공란
            if (b.action === 'referInsert' || b.action === 'forwardPlan') {
              return (
                <button
                  key={i}
                  type="button"
                  className="h-btn-sm px-3 rounded border border-accent bg-accent-dim text-accent text-sm font-semibold cursor-pointer hover:bg-accent hover:text-white transition-colors"
                  onClick={() => {
                    // 자동/시스템 필드 제외
                    const skip = new Set(['idx','wdate','wdater','lastupdate','lastupdater','useflag','__readonly']);
                    const prefill = {};
                    Object.entries(values || {}).forEach(([k, v]) => {
                      if (skip.has(k)) return;
                      if (v === null || v === undefined) return;
                      prefill[k] = v;
                    });
                    if (b.action === 'forwardPlan') {
                      prefill.naeyong = values.hyanghugyehoek ?? '';
                      prefill.hyanghugyehoek = '';
                    }
                    try { sessionStorage.setItem(`mis_referInsert_${gubun}`, JSON.stringify(prefill)); } catch {}
                    // 새 탭 X — 같은 탭에서 write 모드 전환 (panelSize 그대로 유지)
                    window.dispatchEvent(new CustomEvent('mis:openIdxModify', {
                      detail: { mode: 'write' }
                    }));
                  }}
                >{b.label ?? (b.action === 'forwardPlan' ? '향후계획처리' : '참조하여 신규입력')}</button>
              );
            }
            if (b.action) {
              return (
                <button
                  key={i}
                  type="button"
                  className="h-btn-sm px-3 rounded border border-accent bg-accent-dim text-accent text-sm font-semibold cursor-pointer hover:bg-accent hover:text-white transition-colors"
                  onClick={async () => {
                    if (b.confirm && !window.confirm(b.confirm)) return;
                    try {
                      const res = await api.treat(gubun, {
                        action: b.action,
                        idx: b.idx ?? idx,
                      });
                      const d = res.data ?? {};
                      if (d._client_alert) alert(d._client_alert);
                      if (d._client_toast) showToast(d._client_toast);
                      if (d.success === false) return;
                      if (d.reloadList) onDelete?.();
                    } catch (e) {
                      showToast(e.message || '실행 실패', 'error');
                    }
                  }}
                >{b.label ?? '실행'}</button>
              );
            }
            const detail = {};
            if (b.gubun)    detail.gubun    = b.gubun;
            if (b.realPid)  detail.realPid  = b.realPid;
            if (b.idx)      detail.idx      = b.idx;
            if (b.label)    detail.label    = b.label;
            if (b.openFull) detail.openFull = true;
            return (
              <button
                key={i}
                type="button"
                className="h-btn-sm px-3 rounded border border-accent bg-accent-dim text-accent text-sm font-semibold cursor-pointer hover:bg-accent hover:text-white transition-colors"
                data-opentab={JSON.stringify(detail)}
              >{b.label ?? '열기'}</button>
            );
          })}
        </div>
      )}

      {/* SQL 상세 모달 */}
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
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-border-base bg-surface-2 flex-shrink-0">
              <span className="text-sm font-bold text-primary">실행 쿼리 — VIEW (개발자모드)</span>
              <div className="flex items-center gap-2">
                <button type="button" className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors" onClick={() => { copyText(buildCopyText(devSql)); showToast('복사되었습니다'); }}>복사</button>
                <button type="button" className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors" onClick={() => setSqlModalOpen(false)}>✕ 닫기</button>
              </div>
            </div>
            <div className="flex-1 overflow-auto p-4 flex flex-col gap-4">
              <div>
                <div className="text-xs font-bold text-secondary mb-1 uppercase tracking-wide">SELECT</div>
                <pre className="text-xs text-primary bg-surface-2 rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6">{formatSQL(devSql.sql)}</pre>
              </div>
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

      {activeIsGantt ? (
        /* ── 간트차트 전체화면 모드 ── */
        <div className="flex flex-col flex-1 min-h-0">
          <GanttChartLazy projectIdx={idx || 0} gubun={gubun} />
        </div>
      ) : activeIsCode ? (
        /* ── 코드 에디터 전체화면 모드 ── */
        <div className="flex flex-col flex-1 min-h-0" ref={formRefCallback}>
          {(() => {
            const codeField = formFields.find(f => normalizeGroup(f.form_group) === activeTabProp && f.schema_validation === 'code');
            if (!codeField) return null;
            const alias = codeField.alias_name ?? '';
            return (
              <div className="flex-1 min-h-0 border border-border-base rounded overflow-hidden" style={{ minHeight: '400px' }}>
                <CodeEditor
                  alias={alias}
                  val={values[alias] ?? ''}
                  readOnly={readOnly}
                  onChange={handleChange}
                />
              </div>
            );
          })()}
        </div>
      ) : (
        /* ── 일반 그룹 렌더링 ── */
        <div className="flex flex-col gap-0" ref={formRefCallback}>
          {displayGroups.filter(g => !codeGroupSet.has(g)).map((group, gi) => {
              const gFields = formFields.filter(f => normalizeGroup(f.form_group) === group);

              return (
                <div
                  key={group}
                  ref={el => { groupRefsMap.current[group] = el; }}
                  className={gi > 0 ? 'mt-1' : ''}
                >
                  {/* 그룹 구분선 — 그룹이 2개 이상일 때만 표시 */}
                  {multiGroup && (
                    <div className="flex items-center gap-2.5 mb-2 mt-6 first:mt-0 py-1">
                      <div className="w-1 h-5 rounded-sm bg-accent flex-shrink-0" />
                      <span className="text-sm font-bold text-primary tracking-wide select-none">
                        {group}
                      </span>
                      <div className="flex-1 h-px bg-border-base" />
                    </div>
                  )}

                  {/* 필드 그리드 — 빈공간은 흰색(surface) 으로, 셀 사이 1px 갭에는 보더선이 안 보임 */}
                  <div
                    className="grid border border-border-base"
                    style={{ gridTemplateColumns: 'repeat(12, 1fr)', gridAutoRows: 'minmax(62px, auto)', gridAutoFlow: 'dense', gap: '1px', background: 'var(--color-surface)' }}
                  >
                {(() => {
                  // ── subgroup 멤버십 맵 ──
                  const subgroupMap = new Array(gFields.length);
                  {
                    let cur = null;
                    for (let i = 0; i < gFields.length; i++) {
                      const ct = gFields[i].col_title ?? '';
                      const ci = ct.indexOf(',');
                      if (ci === -1) cur = null;                              // standalone
                      else if (ct.slice(0, ci) !== '') cur = ct.slice(0, ci); // 새 subgroup 시작
                      // else '' → 이전 subgroup 계속
                      subgroupMap[i] = cur;
                    }
                  }

                  // 인접한 같은 subgroup 끼리 묶어서 items 생성
                  const items = [];
                  let i = 0;
                  while (i < gFields.length) {
                    const sg = subgroupMap[i];
                    if (sg == null) {
                      items.push({ type: 'standalone', f: gFields[i], fi: i });
                      i++;
                    } else {
                      const start = i;
                      while (i < gFields.length && subgroupMap[i] === sg) i++;
                      items.push({ type: 'group', label: sg, fields: gFields.slice(start, i), startFi: start });
                    }
                  }

                  // 필드 셀 렌더 (기존 flatMap 콜백 추출 — 단일 element 또는 null 반환)
                  const renderFieldCell = (f, fi) => {
                  const alias = f.alias_name ?? '';

                  // Qn value 필드는 숨김 (display 필드에서 selectbox로 대체)
                  if (qnHiddenAliases.has(alias)) return null;

                  // col_title에 콜론(:) 포함 → 섹션 제목 (데이터 영역 없음, 전체 너비)
                  const _titleText = (f.col_title ?? '').trim();
                  if (_titleText.includes(':')) {
                    const cleanTitle = _titleText.replace(/:/g, '').trim();
                    if (!cleanTitle) return null;
                    return (
                      <div key={alias} style={{ gridColumn: '1 / -1' }} className="bg-surface-2 px-3 py-2">
                        <span className="text-xs font-bold text-secondary tracking-wide">{cleanTitle}</span>
                      </div>
                    );
                  }

                  // max_length 규칙 (v6 view_inc.php 와 동일):
                  //  - max_length < 0  : write(등록) 시 abs(N) 까지 입력 가능, modify(수정) 시 읽기전용
                  //  - max_length === 0: write/modify 모두 읽기전용
                  //  - 그 외(양수/빈값): 양쪽 모두 편집 가능
                  //  - 끝이 ! : 행 단위 readonly(rowReadOnly) 를 무시하고 편집 허용 (첨부/이미지 제외)
                  const _mlRaw = parseInt(f.max_length ?? '', 10);
                  const _mlIsZero = !isNaN(_mlRaw) && _mlRaw === 0;
                  const _mlIsNeg  = !isNaN(_mlRaw) && _mlRaw < 0;
                  const _isOverride = isOverrideField(f);
                  const maxLenReadOnly = _mlIsZero || (_mlIsNeg && mode === 'modify');
                  // virtual_field 의 treat 액션 (default_value='treat:...') 은 행 readonly 와 무관하게 항상 활성
                  const _isVirtualTreat = f.db_table === 'virtual_field'
                    && String(f.default_value ?? '').trim().startsWith('treat:');
                  // key 필드는 수정 모드에서도 읽기전용. rowReadOnly 행은 override 필드만 편집 허용.
                  const fieldReadOnly = _isVirtualTreat
                    ? false
                    : (readOnly
                       || (mode === 'modify' && alias === pkAlias)
                       || maxLenReadOnly
                       || (rowReadOnly && mode === 'modify' && !_isOverride));

                  const val      = values[alias] ?? '';
                  const vc       = parseViewClass(f.grid_view_class);
                  const colSpan  = getSpan(vc, f, panelW);
                  const colStart = (f.grid_enter === '1' || f.grid_enter === 1) ? 1 : 'auto';
                  const hInfo    = parseHeight(f, vc);
                  const isHtmlCtl     = f.grid_ctl_name === 'html';
                  const isTextareaCtl = f.grid_ctl_name === 'textarea';
                  const hRows    = isHtmlCtl ? Math.max(4, hInfo.rows) : hInfo.rows;
                  // 기하학적 정렬: 셀 전체는 h=1 셀을 hRows 개 스택한 것과 동일 높이
                  // grid-auto-rows=62px, gap=1px → span n 셀 높이 = 62n + (n-1)
                  const cellPx  = 62 * hRows + Math.max(0, hRows - 1);
                  const inputPx = cellPx - 28; // 라벨 헤더 ~28px 제외
                  // max-height 모드(grid_view_hight ≥ 52): 내용이 있을 때만 커지고 없으면 1행 수축
                  //  → 고정 row-span 대신 minHeight=1행, maxHeight=N행 으로 제한
                  // 단, textarea 컨트롤은 뷰 디자이너 설정값을 "고정 높이" 로 취급 (빈 상태에도 전체 높이 유지)
                  const heightStyle = isHtmlCtl
                    ? { height: `${inputPx}px` }
                    : isTextareaCtl
                      ? { height: `${inputPx}px` }
                      : hInfo.isMaxHeight
                        ? { minHeight: '34px', maxHeight: `${inputPx}px` }
                        : { minHeight: `${inputPx}px` };

                  // Qn display 필드: 연결된 value 필드의 prime_key로 selectbox 렌더링
                  // value 필드의 col_width=-1 일 수도 있으므로 fields 전체에서 검색
                  const linkedValueAlias = qnDisplayToValue[alias];
                  const linkedValueField = linkedValueAlias
                    ? fields.find(vf => vf.alias_name === linkedValueAlias)
                    : null;

                  // Qn selectbox인 경우 코드값
                  const isQnSelect = !!linkedValueField;
                  const qnCodeVal  = isQnSelect ? (values[linkedValueAlias] ?? '') : '';

                  // 필수 표시(*): 자신이 필수이거나, 숨겨진 value 필드(selectbox)가 필수면
                  // 보이는 display 필드 쪽에 * 를 표시 (재직코드 필수 → 재직구분에 *)
                  const showRequiredMark = f.required === 'Y'
                    || (linkedValueField && qnHiddenAliases.has(linkedValueAlias) && linkedValueField.required === 'Y');

                  // schema_validation=zipcode → 우편번호 검색 UI
                  const isZipcode = f.schema_validation === 'zipcode';
                  const zipcodeAliases = isZipcode ? {
                    zipcode: alias,                                  // 현재 필드 = 우편번호
                    address: gFields[fi + 1]?.alias_name ?? null,   // 직후 필드 = 우편주소
                    detail:  gFields[fi + 2]?.alias_name ?? null,   // 직직후 필드 = 상세주소
                  } : null;

                  const isAttach = f.grid_ctl_name === 'attach' || f.grid_ctl_name === 'image';
                  const attachInfo = isAttach ? parseAttachLimit(f.max_length) : null;

                  const inputEl = isZipcode
                    ? <ZipcodeInput
                        val={val}
                        readOnly={fieldReadOnly}
                        aliases={zipcodeAliases}
                        onChange={handleChange}
                      />
                    : isAttach
                    ? <FileAttach
                        gubun={gubun}
                        idx={idx}
                        realPid={f.field_real_pid ?? ''}
                        alias={alias}
                        readOnly={fieldReadOnly}
                        multi={attachInfo.multi}
                        maxMB={attachInfo.maxMB}
                        maxCount={attachInfo.maxCount}
                        allowExts={f.schema_validation || ''}
                        mode={mode}
                        midx={parseInt(values[alias + '_midx'] ?? 0, 10) || 0}
                        onTempChange={handleTempAttachChange}
                      />
                    : isQnSelect
                    ? (() => {
                        // 연결된 value 필드에 ctl_name이 없으면 텍스트만 표시
                        if (!linkedValueField.grid_ctl_name) {
                          return <span className="w-full h-full px-2 text-base text-secondary bg-transparent cursor-default flex items-center">{val ?? ''}</span>;
                        }
                        // helplist 감지 — schema_validation 에 "helplist" 키 → 팝업식 페어 컨트롤
                        if (/"helplist"/.test(linkedValueField.schema_validation ?? '')) {
                          return (
                            <HelplistPair
                              gubun={gubun}
                              displayField={f}
                              valueField={linkedValueField}
                              displayVal={val}
                              codeVal={values[linkedValueAlias] ?? ''}
                              readOnly={fieldReadOnly}
                              onChange={(displayText, codeVal) => {
                                handleChange(alias, displayText);
                                handleChange(linkedValueAlias, codeVal);
                              }}
                            />
                          );
                        }
                        const baseCls  = 'w-full h-full px-2 text-base text-primary bg-transparent outline-none border-0';
                        const ROCls    = baseCls + ' text-secondary cursor-default';
                        const inputCls = fieldReadOnly ? ROCls : baseCls + ' border-b border-accent/30 focus:border-accent transition-colors';
                        return (
                          <DropdownSelect
                            gubun={gubun}
                            field={linkedValueField}
                            val={values[linkedValueAlias] ?? ''}
                            readOnly={fieldReadOnly}
                            onChange={(valueAlias, codeVal, displayText) => {
                              handleChange(valueAlias, codeVal);
                              handleChange(alias, displayText);
                            }}
                            baseCls={baseCls}
                            ROCls={ROCls}
                            inputCls={inputCls}
                            recordIdx={idx}
                            formValues={values}
                          />
                        );
                      })()
                    : renderInput(f, val, fieldReadOnly, handleChange, hRows, gubun, inputPx, {
                        idx,
                        mode,
                        formValues: values,
                        decryptEnabled: decryptEnabled.has(alias),
                        passwdInput: values[alias] ?? '',
                        onDecryptToggle: () => {
                          setDecryptEnabled(prev => {
                            const next = new Set(prev);
                            if (next.has(alias)) { next.delete(alias); handleChange(alias, ''); }
                            else { next.add(alias); handleChange(alias, ''); }
                            return next;
                          });
                          markDirty();
                        },
                      });

                  // 개발자모드: 라벨 tooltip 생성
                  const devTitle = devMode
                    ? `field: ${f.db_field ?? ''}` +
                      (f.db_table ? ` (${f.db_table})` : '') +
                      `\nalias: ${alias}` +
                      (isQnSelect && qnCodeVal !== '' ? `\ncode: ${qnCodeVal}` : '') +
                      ((f.schema_type === 'dropdownitem' || f.grid_ctl_name === 'dropdownlist' || f.grid_ctl_name === 'dropdownitem') && val !== '' ? `\ncode: ${val}` : '')
                    : undefined;

                  // 행 span: 일반 셀 및 textarea 는 hRows 행 고정 / max-height 셀(textarea 제외) 은 1행부터 시작해 콘텐츠 auto 성장
                  const rowSpan = (hInfo.isMaxHeight && !isTextareaCtl) ? 1 : Math.max(1, hRows);

                  const fieldCell = (
                    <div
                      key={alias}
                      style={{
                        gridColumnStart: colStart,
                        gridColumnEnd:  `span ${colSpan}`,
                        gridRowEnd:     `span ${rowSpan}`,
                        // 고정높이 셀: 외곽 높이를 cellPx 로 고정해 row track 이 콘텐츠로 부풀어 이웃 셀(row-1)이 따라 커지는 것을 방지.
                        // isMaxHeight(row-52+) 는 auto-grow 필요하므로 제외 — 단 textarea 는 항상 고정.
                        ...((hInfo.isMaxHeight && !isTextareaCtl) ? {} : { height: `${cellPx}px` }),
                      }}
                      className="flex flex-col bg-surface overflow-hidden"
                    >
                      <div
                        className="px-2 py-1 bg-surface-2 border-b border-border-base flex-shrink-0 flex items-center gap-1"
                        title={devTitle}
                      >
                        <span className="text-sm font-semibold text-secondary whitespace-nowrap truncate">
                          {formLabel(f.col_title, alias)}
                        </span>
                        {showRequiredMark && <span className="text-danger text-xs flex-shrink-0">*</span>}
                        {!readOnly && f.grid_alim && (
                          <AlimTooltip text={f.grid_alim} />
                        )}
                        {fieldButtons[alias] && (
                          <span className="cell-html ml-auto flex-shrink-0 inline-flex items-center gap-1" dangerouslySetInnerHTML={{ __html: fieldButtons[alias] }} />
                        )}
                      </div>
                      <div
                        className={`flex-1 min-h-0${hInfo.isMaxHeight ? ' overflow-auto' : ''}`}
                        style={heightStyle}
                      >
                        {inputEl}
                      </div>
                    </div>
                  );
                  return fieldCell;
                  };  // end of renderFieldCell

                  // ── items 렌더 — standalone 은 그대로 grid item, group 은 fieldset+legend 컨테이너로 감싸 sub-grid 안에 배치 ──
                  return items.map((item, ii) => {
                    if (item.type === 'standalone') {
                      const cell = renderFieldCell(item.f, item.fi);
                      return cell ? <React.Fragment key={'st-' + item.fi}>{cell}</React.Fragment> : null;
                    }
                    // 그룹 컨테이너: 곡선 테두리 + 라벨 (legend 스타일, '인쇄' 글씨 중앙 높이로 라인 갈라짐)
                    return (
                      <div
                        key={'sg-' + item.startFi}
                        style={{
                          gridColumn: '1 / -1',
                          position: 'relative',
                          margin: '14px 0 6px 0',
                          padding: '14px 6px 6px 6px',
                          border: '1px solid #14b8a6',
                          borderRadius: 12,
                          background: 'transparent',
                        }}
                      >
                        <span
                          style={{
                            position: 'absolute',
                            top: -9,
                            left: 18,
                            background: 'var(--color-surface)',
                            padding: '0 8px',
                            fontSize: 12,
                            fontWeight: 500,
                            color: '#0d9488',
                            whiteSpace: 'nowrap',
                            letterSpacing: '0.02em',
                          }}
                        >
                          {item.label}
                        </span>
                        <div
                          className="grid"
                          style={{
                            gridTemplateColumns: 'repeat(12, 1fr)',
                            gridAutoRows: 'minmax(62px, auto)',
                            gridAutoFlow: 'dense',
                            gap: '1px',
                            background: 'var(--color-surface)',
                          }}
                        >
                          {item.fields.map((f, idx) => {
                            const cell = renderFieldCell(f, item.startFi + idx);
                            return cell ? <React.Fragment key={'sgcell-' + item.startFi + '-' + idx}>{cell}</React.Fragment> : null;
                          })}
                        </div>
                      </div>
                    );
                  });
                })()}
              </div>
            </div>
          );
        })}
        </div>
      )}

      {/* 액션 버튼 (하단) — 상단과 동일 구성 (helper 재사용) */}
      {showEditActions && renderEditActions("flex gap-2 mt-4 flex-shrink-0")}
      {showViewActions && renderViewActions("flex gap-2 mt-4")}

      {/* 서버 훅 _client_belowForm — 폼 하단 패널 (sibling 이력 등) */}
      {belowForm && belowForm.type === 'siblingList' && Array.isArray(belowForm.rows) && belowForm.rows.length > 0 && (
        <div className="mt-4 border border-border-base rounded bg-surface">
          <div className="px-3 py-2 bg-surface-2 border-b border-border-base text-sm font-semibold text-primary">
            {belowForm.title ?? '관련 이력'}
          </div>
          <div className="overflow-auto max-h-72">
            <table className="w-full text-xs">
              <thead className="bg-surface-2 sticky top-0">
                <tr>
                  {(belowForm.columns ?? []).map((c, ci) => (
                    <th key={ci} className="px-2 py-1.5 text-left font-bold text-muted uppercase border-b border-border-base whitespace-nowrap"
                        style={{ width: c.width ? `${c.width}px` : undefined }}>{c.label ?? c.key}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {belowForm.rows.map((r, ri) => {
                  const isCurrent = String(r.idx) === String(belowForm.currentIdx);
                  return (
                    <tr key={ri}
                        className={`cursor-pointer transition-colors ${isCurrent ? 'bg-accent-dim font-semibold' : 'hover:bg-surface-2'}`}
                        onClick={() => {
                          if (isCurrent) return;
                          // 새 탭 X — 같은 탭에서 idx 만 view 모드로 갱신 (panelSize 그대로 유지)
                          window.dispatchEvent(new CustomEvent('mis:openIdxModify', {
                            detail: { idx: r.idx, mode: 'view' }
                          }));
                        }}>
                      {(belowForm.columns ?? []).map((c, ci) => (
                        <td key={ci} className="px-2 py-1 text-primary border-b border-border-light whitespace-nowrap overflow-hidden text-ellipsis"
                            style={{ maxWidth: c.width ? `${c.width}px` : undefined }}>
                          {isCurrent && ci === 0 ? `▶ ${r[c.key] ?? ''}` : (r[c.key] ?? '')}
                        </td>
                      ))}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </form>
  );
}

const QUILL_MODULES = {
  toolbar: [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ indent: '-1' }, { indent: '+1' }],
    [{ align: [] }],
    ['link', 'image'],
    ['clean'],
  ],
};
const QUILL_FORMATS = [
  'header', 'bold', 'italic', 'underline', 'strike',
  'color', 'background', 'list', 'bullet', 'indent',
  'align', 'link', 'image',
];

function HtmlEditor({ alias, val, readOnly, onChange, heightPx = 136 }) {
  const editorBodyPx = Math.max(60, heightPx - 42); // 42px = Quill 툴바 높이
  const [htmlMode, setHtmlMode] = useState(false);
  if (readOnly) {
    return (
      <div
        className="w-full h-full px-2 py-1.5 text-base text-primary overflow-auto prose-sm"
        style={{ minHeight: `${heightPx}px` }}
        dangerouslySetInnerHTML={{ __html: val || '' }}
      />
    );
  }
  return (
    <div className="relative w-full h-full">
      {/* HTML 직접 편집 토글 — 툴바 우측 끝에 겹쳐 띄움 */}
      <button
        type="button"
        onClick={() => setHtmlMode(m => !m)}
        title={htmlMode ? 'WYSIWYG 편집기로 전환' : 'HTML 소스 직접 편집'}
        className={
          'absolute top-1 right-2 z-10 h-7 px-2 rounded border text-xs font-mono cursor-pointer transition-colors ' +
          (htmlMode
            ? 'border-accent bg-accent text-white hover:bg-accent/90'
            : 'border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary')
        }
      >&lt;/&gt;</button>
      {htmlMode ? (
        <textarea
          className="w-full h-full px-2 py-1.5 text-sm font-mono text-primary bg-surface border border-border-base rounded resize-none outline-none focus:border-accent"
          style={{ height: `${heightPx}px` }}
          value={val || ''}
          onChange={e => onChange(alias, e.target.value)}
          spellCheck={false}
        />
      ) : (
        <Suspense fallback={<div className="flex items-center justify-center text-muted text-sm" style={{ height: `${heightPx}px` }}>에디터 로딩 중...</div>}>
          <ReactQuill
            theme="snow"
            value={val || ''}
            onChange={v => onChange(alias, v)}
            modules={QUILL_MODULES}
            formats={QUILL_FORMATS}
            style={{ height: `${editorBodyPx}px` }}
          />
        </Suspense>
      )}
    </div>
  );
}

/* SearchableSelect는 SearchableSelect.jsx로 분리됨 */

/**
 * 코드 에디터 (Monaco Editor)
 * schema_validation='code' 일 때 사용
 */
function guessLanguage(val) {
  const s = (val ?? '').trimStart();
  if (/^<\?php/i.test(s) || /function\s+\w+\s*\(.*\$/.test(s)) return 'php';
  if (/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER)\b/i.test(s)) return 'sql';
  if (/<html|<div|<span|<!DOCTYPE/i.test(s)) return 'html';
  if (/^import\s|^export\s|^const\s|^function\s|=>\s*{/.test(s)) return 'javascript';
  if (/^\.\w|^#\w|^\*\s*{|^@media/m.test(s)) return 'css';
  return 'php';
}

const HOOK_GLOBALS = `
/*
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  사용 가능한 전역변수 (global 선언 후 사용)                           │
 * ├──────────────────────────┬───────────────────────────────────────────┤
 * │ $actionFlag              │ 현재 액션 (list/view/modify/write/delete) │
 * │ $gubun                   │ 메뉴 idx (정수)                           │
 * │ $idx                     │ 레코드 idx (정수)                         │
 * │ $real_pid                │ 프로그램 real_pid (speedmis000036 형태)   │
 * │ $menu_name               │ 프로그램명                                │
 * │ $parent_idx              │ 마스터-디테일 상위 idx                    │
 * │ $misSessionUserId        │ 로그인 사용자 ID                         │
 * │ $misSessionIsAdmin       │ 관리자 여부 ('Y' 또는 '')                │
 * │ $misSessionPositionCode  │ 직급 코드                                │
 * │ $isFirstLoad             │ 프로그램 최초 로딩 여부 (bool)            │
 * │ $isListEdit              │ 목록편집(인라인) 저장 여부 (bool)         │
 * │ $listEditField           │ 목록편집 시 변경된 필드명 배열            │
 * │ $customAction            │ 사용자 정의 버튼 action 값               │
 * │ $allFilter               │ 필터 JSON 문자열                         │
 * │ $orderby                 │ 정렬 문자열                              │
 * │ $page                    │ 현재 페이지                              │
 * │ $pageSize                │ 페이지당 건수                            │
 * │ $__pdo                   │ PDO 인스턴스 (DB 직접 접근)              │
 * │ $full_site               │ 사이트 주소                              │
 * ├──────────────────────────┴───────────────────────────────────────────┤
 * │  클라이언트 제어 ($GLOBALS['...'] = 값)                              │
 * ├──────────────────────────┬───────────────────────────────────────────┤
 * │ _client_alert            │ alert() 팝업 표시                        │
 * │ _client_toast            │ 토스트 알림 표시                         │
 * │ _client_confirm          │ 저장 전 확인 (Yes→저장, No→취소)         │
 * │ _client_openTab          │ 새 탭 열기 {gubun, label, idx, openFull} │
 * │ _client_redirect         │ 현재 탭 교체 {gubun, label}              │
 * │ _client_css              │ CSS 주입 (문자열)                        │
 * │ _client_buttonText       │ 버튼 텍스트 변경 {write, reset}          │
 * │ _client_buttons          │ 사용자정의 버튼 [{label, action}]        │
 * │ _onlyList                │ 리스트전용 모드 (true)                   │
 * └──────────────────────────┴───────────────────────────────────────────┘
 *
 *  SQL 실행 헬퍼:
 *    $result = execSql("INSERT INTO t (name) VALUES (?)", ['홍길동']);
 *    $result = execSql("UPDATE a SET x=1; DELETE FROM b WHERE y=2");
 *    // 결과: resultCode, resultMessage, lastInsertId, rowCount
 */`;

const HOOK_TEMPLATES = [
  { group: '공통(Common)', items: [
    { label: 'pageLoad — 프로그램 속성 선언 (1회)', fn: `
function pageLoad() {
    global $actionFlag, $gubun, $misSessionUserId, $misSessionIsAdmin;
${HOOK_GLOBALS}

    /*
     * ■ 리스트전용 프로그램 (조회만, 등록/수정 불가)
     * $GLOBALS['_onlyList'] = true;
     *
     * ■ 버튼 텍스트 변경
     * $GLOBALS['_client_buttonText'] = [
     *     'write' => '접수하기',     // +등록 → 접수하기
     *     'reset' => '전체보기',     // 초기화 → 전체보기
     * ];
     *
     * ■ 사용자 정의 버튼 추가 (list_json_init에서 $customAction으로 감지)
     * $GLOBALS['_client_buttons'] = [
     *     ['label' => '일괄적용', 'action' => 'apply'],
     *     ['label' => '마감처리', 'action' => 'close'],
     *     ['label' => '엑셀가져오기', 'action' => 'importExcel'],
     * ];
     *
     * ■ CSS 주입 (특정 요소 숨기기/스타일링)
     * $GLOBALS['_client_css'] = '
     *     #mis-btn-write { display: none; }
     *     #mis-btn-reset { background: #3182F6; color: #fff; }
     *     #mis-header { background: #f0f8ff; }
     * ';
     * // 주요 CSS ID: #mis-program, #mis-header, #mis-title,
     * //   #mis-header-actions, #mis-btn-write, #mis-btn-reset, #mis-btn-custom-0
     */
}` },
    { label: 'before_query — 쿼리 빌드 전 초기화', fn: `
function before_query($menu, $fields, $params) {
    global $actionFlag, $gubun, $idx, $misSessionUserId, $__pdo;
    /*
     * 리스트·조회·수정·저장 모든 액션에서 쿼리 생성 전에 호출됨
     * $menu:   메뉴 정보 배열 (table_name, real_pid, base_filter 등)
     * $fields: 필드 정의 배열 (alias_name, db_field, col_width 등)
     * $params: 요청 파라미터 (gubun, idx, allFilter, page, pageSize 등)
     *
     * ■ 전역변수 세팅 (다른 훅에서 활용)
     * $GLOBALS['my_dept'] = $__pdo->query(
     *     "SELECT station_idx FROM mis_users WHERE user_id='{$misSessionUserId}'"
     * )->fetchColumn();
     *
     * ■ 추가 SQL 실행
     * execSql("INSERT INTO access_log (gubun, user_id, wdate) VALUES (?, ?, NOW())",
     *     [$gubun, $misSessionUserId]);
     */
}` },
  ]},
  { group: '목록(List)', items: [
    { label: 'list_query — 목록 쿼리문 가로채기', fn: `
function list_query(&$selectQuery, &$countQuery) {
    global $misSessionUserId, $__pdo;
    /*
     * 목록 SELECT/COUNT 쿼리를 직접 수정 가능
     * $selectQuery: "SELECT ... FROM ... WHERE ..."
     * $countQuery:  "SELECT COUNT(*) FROM ... WHERE ..."
     *
     * ■ WHERE 조건 추가
     * $selectQuery = str_replace('WHERE 1=1',
     *     "WHERE 1=1 AND table_m.wdater='{$misSessionUserId}'", $selectQuery);
     * $countQuery = str_replace('WHERE 1=1',
     *     "WHERE 1=1 AND table_m.wdater='{$misSessionUserId}'", $countQuery);
     *
     * ■ JOIN 추가
     * $join = " LEFT JOIN mis_users u ON u.user_id = table_m.wdater";
     * $selectQuery = str_replace('WHERE', $join . ' WHERE', $selectQuery);
     *
     * ■ INFORMATION_SCHEMA JOIN에 TABLE_SCHEMA 조건 추가
     * $dbName = $_ENV['DB_NAME'] ?? 'speedmis_v7';
     * $selectQuery = str_replace('table_COLUMNS.TABLE_NAME=',
     *     "table_COLUMNS.TABLE_SCHEMA='{$dbName}' AND table_COLUMNS.TABLE_NAME=",
     *     $selectQuery);
     */
}` },
    { label: 'list_json_init — 목록 로딩 전 초기화', fn: `
function list_json_init() {
    global $actionFlag, $gubun, $misSessionUserId, $isFirstLoad, $customAction, $__pdo;
    /*
     * 목록 데이터를 가져오기 전에 실행
     * 매 조회마다 실행됨 (페이지 이동, 필터, 정렬 변경 시마다)
     *
     * ■ 최초 로딩 시에만 실행
     * if ($isFirstLoad) {
     *     $GLOBALS['_client_toast'] = '환영합니다!';
     * }
     *
     * ■ 사용자 정의 버튼 클릭 감지
     * if ($customAction === 'apply') {
     *     execSql("UPDATE my_table SET status='적용' WHERE status='대기'");
     *     $GLOBALS['_client_toast'] = '일괄 적용 완료!';
     * }
     * if ($customAction === 'close') {
     *     execSql("UPDATE my_table SET closed=1 WHERE closed=0");
     *     $GLOBALS['_client_toast'] = '마감 처리 완료!';
     * }
     *
     * ■ 다른 프로그램을 새 탭으로 열기
     * if ($isFirstLoad) {
     *     $GLOBALS['_client_openTab'] = [
     *         'gubun' => 314, 'label' => '대시보드',
     *         'idx' => 0, 'openFull' => true,
     *     ];
     * }
     *
     * ■ 현재 탭을 다른 프로그램으로 교체 (리다이렉트)
     * if ($isFirstLoad && $misSessionUserId !== 'admin') {
     *     $GLOBALS['_client_redirect'] = ['gubun' => 36, 'label' => '그룹관리'];
     * }
     *
     * ■ alert / toast
     * $GLOBALS['_client_alert'] = '중요 공지사항입니다!';
     * $GLOBALS['_client_toast'] = '새 데이터 3건이 등록되었습니다.';
     */
}` },
    { label: 'list_json_load — 목록 각 행 변환', fn: `
function list_json_load(&$data) {
    /*
     * 목록의 각 행(row)마다 호출됨
     * $data: 연관배열 (alias_name => 값)
     *
     * ■ 데이터 값 자체를 변경 (폼에서도 변경된 값 사용)
     * $data['total'] = (int)$data['price'] * (int)$data['qty'];
     * $data['full_name'] = $data['last_name'] . ' ' . $data['first_name'];
     *
     * ■ 그리드 표시만 변경 (원본 데이터는 보존)
     * $data['__html']['필드명'] = 'HTML 문자열';
     *
     * 예1) 링크로 표시
     * $data['__html']['site_name'] = '<a href="'.$data['site_url']
     *     .'" target="_blank">'.$data['site_name'].'</a>';
     *
     * 예2) 상태 뱃지
     * $st = $data['status'];
     * $colors = ['완료'=>'#22c55e', '진행중'=>'#3b82f6', '대기'=>'#f59e0b'];
     * $bg = $colors[$st] ?? '#6b7280';
     * $data['__html']['status'] = '<span class="badge" style="background:'
     *     .$bg.';color:#fff;padding:2px 8px;border-radius:4px;font-size:11px">'
     *     .$st.'</span>';
     *
     * 예3) 조건부 색상
     * $amt = (int)$data['amount'];
     * $color = $amt >= 100000 ? '#ef4444' : ($amt >= 50000 ? '#f59e0b' : '#22c55e');
     * $data['__html']['amount'] = '<span style="color:'.$color.';font-weight:700">'
     *     .number_format($amt).'</span>';
     *
     * 예4) 이미지 썸네일
     * if ($data['photo_url']) {
     *     $data['__html']['photo'] = '<img src="'.$data['photo_url']
     *         .'" style="height:24px;border-radius:4px">';
     * }
     */
}` },
  ]},
  { group: '저장(Update)', items: [
    { label: 'save_updateReady — 저장 전 검증/확인', fn: `
function save_updateReady(&$saveList) {
    global $isListEdit, $listEditField, $idx, $__pdo;
    /*
     * 저장 버튼 클릭 직후, 데이터 필터링 전에 호출
     * $saveList: POST로 전달된 원본 데이터 (alias_name => 값)
     *
     * ■ 값 추가/수정
     * $saveList['updated_by'] = $GLOBALS['misSessionUserId'];
     * $saveList['total'] = (int)$saveList['price'] * (int)$saveList['qty'];
     *
     * ■ 저장 전 확인 다이얼로그
     * $GLOBALS['_client_confirm'] = '정말로 저장할까요?';
     * // → 브라우저 confirm → Yes → _confirmed=true로 재전송 → 저장
     * // → No → 저장 취소
     *
     * ■ 조건부 확인
     * if ((int)$saveList['amount'] > 1000000) {
     *     $GLOBALS['_client_confirm'] = '100만원 이상입니다. 승인하시겠습니까?';
     * }
     *
     * ■ 중복 체크
     * $cnt = $__pdo->prepare("SELECT COUNT(*) FROM my_table WHERE name=? AND idx<>?");
     * $cnt->execute([$saveList['name'], $idx]);
     * if ($cnt->fetchColumn() > 0) {
     *     $GLOBALS['_client_confirm'] = '동일한 이름이 이미 존재합니다. 계속할까요?';
     * }
     *
     * ■ 목록편집(인라인) 감지
     * if ($isListEdit) {
     *     // $listEditField = ['status'] — 변경된 필드명 배열
     *     if (in_array('status', $listEditField)) {
     *         $GLOBALS['_client_toast'] = '상태가 변경되었습니다.';
     *     }
     * }
     */
}` },
    { label: 'save_updateBefore — UPDATE 직전 데이터 수정', fn: `
function save_updateBefore(&$updateList) {
    global $misSessionUserId;
    /*
     * DB 컬럼명 기준 UPDATE 데이터 (alias가 아닌 실제 컬럼명)
     * 여기서 값을 바꾸면 UPDATE 쿼리에 반영됨
     *
     * ■ 자동 계산 필드
     * $updateList['total_price'] = (int)$updateList['price'] * (int)$updateList['qty'];
     *
     * ■ 특정 필드 강제 세팅
     * $updateList['modifier'] = $misSessionUserId;
     * $updateList['modify_date'] = date('Y-m-d H:i:s');
     *
     * ■ 특정 필드 제거 (UPDATE에서 제외)
     * unset($updateList['readonly_field']);
     */
}` },
    { label: 'save_updateQueryBefore — UPDATE SQL 가로채기', fn: `
function save_updateQueryBefore(&$sql, &$bindings) {
    /*
     * 최종 UPDATE SQL과 바인딩을 직접 수정 가능
     * $sql: "UPDATE \`table\` SET col1=?, col2=? WHERE idx=?"
     * $bindings: [값1, 값2, idx값]
     *
     * ■ SQL 직접 수정 (위험 — 신중하게)
     * $sql = str_replace('SET', 'SET version=version+1,', $sql);
     */
}` },
    { label: 'save_updateAfter — UPDATE 완료 후 처리', fn: `
function save_updateAfter($idx, &$afterScript) {
    global $__pdo, $misSessionUserId, $gubun;
    /*
     * UPDATE 쿼리 실행 완료 후 호출
     * $idx: 저장된 레코드 idx
     *
     * ■ 다른 테이블 연동
     * $__pdo->prepare("UPDATE related SET synced=NOW() WHERE link_idx=?")
     *     ->execute([$idx]);
     *
     * ■ 여러 쿼리 실행
     * execSql("UPDATE log SET status='done' WHERE ref_idx={$idx};
     *          INSERT INTO history (gubun, idx, action, user_id, wdate)
     *          VALUES ({$gubun}, {$idx}, 'update', '{$misSessionUserId}', NOW())");
     *
     * ■ 알림/토스트
     * $GLOBALS['_client_toast'] = "idx={$idx} 저장 완료";
     *
     * ■ 저장 후 다른 탭 열기
     * $GLOBALS['_client_openTab'] = [
     *     'gubun' => 100, 'label' => '결과확인', 'idx' => $idx,
     * ];
     */
}` },
  ]},
  { group: '등록(Insert)', items: [
    { label: 'save_writeBefore — INSERT 직전 데이터 수정', fn: `
function save_writeBefore(&$updateList) {
    global $misSessionUserId, $__pdo;
    /*
     * INSERT 데이터를 수정/추가 가능
     *
     * ■ 자동 채번
     * $max = $__pdo->query("SELECT MAX(seq)+1 FROM my_table")->fetchColumn();
     * $updateList['seq'] = $max ?: 1;
     *
     * ■ 작성자 자동 세팅
     * $updateList['creator'] = $misSessionUserId;
     *
     * ■ 기본값 설정
     * if (empty($updateList['status'])) $updateList['status'] = '대기';
     */
}` },
    { label: 'save_writeQueryBefore — INSERT SQL 가로채기', fn: `
function save_writeQueryBefore(&$sql, &$bindings) {
    /*
     * 최종 INSERT SQL과 바인딩 직접 수정 가능
     * $sql: "INSERT INTO \`table\` (col1, col2) VALUES (?, ?)"
     * $bindings: [값1, 값2]
     */
}` },
    { label: 'save_writeAfter — INSERT 완료 후 처리', fn: `
function save_writeAfter($newIdx, &$afterScript) {
    global $__pdo, $misSessionUserId;
    /*
     * INSERT 완료 후 호출
     * $newIdx: 새로 생성된 레코드 idx (AUTO_INCREMENT)
     *
     * ■ 연관 데이터 자동 생성
     * $__pdo->prepare("INSERT INTO child_table (parent_idx, wdater, wdate)
     *     VALUES (?, ?, NOW())")->execute([$newIdx, $misSessionUserId]);
     *
     * ■ 알림
     * $GLOBALS['_client_toast'] = "새 레코드(#{$newIdx}) 등록 완료";
     */
}` },
  ]},
  { group: '삭제(Delete)', items: [
    { label: 'save_deleteBefore — 삭제 전 검증/취소', fn: `
function save_deleteBefore($idx, &$cancelDelete) {
    global $__pdo, $misSessionUserId, $misSessionIsAdmin;
    /*
     * $cancelDelete = true; → 삭제 취소
     *
     * ■ 관리자만 삭제 허용
     * if ($misSessionIsAdmin !== 'Y') {
     *     $cancelDelete = true;
     *     $GLOBALS['_client_alert'] = '관리자만 삭제할 수 있습니다.';
     * }
     *
     * ■ 하위 데이터 존재 시 삭제 방지
     * $cnt = $__pdo->query("SELECT COUNT(*) FROM child_table
     *     WHERE parent_idx={$idx}")->fetchColumn();
     * if ($cnt > 0) {
     *     $cancelDelete = true;
     *     $GLOBALS['_client_alert'] = "하위 데이터 {$cnt}건이 있어 삭제할 수 없습니다.";
     * }
     */
}` },
    { label: 'save_deleteAfter — 삭제 완료 후 처리', fn: `
function save_deleteAfter($idx, &$afterScript) {
    global $__pdo;
    /*
     * ■ 연관 데이터 정리
     * execSql("DELETE FROM child_table WHERE parent_idx={$idx};
     *          DELETE FROM log_table WHERE ref_idx={$idx}");
     *
     * ■ 알림
     * $GLOBALS['_client_toast'] = '삭제 완료';
     */
}` },
  ]},
  { group: '폼(View/Modify)', items: [
    { label: 'view_query — 조회 쿼리문 가로채기', fn: `
function view_query(&$viewSql) {
    /*
     * 단건 조회 SELECT 쿼리 수정 가능
     * $viewSql: "SELECT ... FROM ... WHERE idx=? LIMIT 1"
     *
     * ■ JOIN 조건 추가
     * $viewSql = str_replace('WHERE',
     *     "LEFT JOIN extra_table e ON e.id = table_m.extra_id WHERE", $viewSql);
     *
     * ■ INFORMATION_SCHEMA TABLE_SCHEMA 조건 추가 (267번 참고)
     * $dbName = $_ENV['DB_NAME'] ?? 'speedmis_v7';
     * if (!str_contains($viewSql, 'TABLE_SCHEMA')) {
     *     $viewSql = str_replace('table_COLUMNS.TABLE_NAME=',
     *         "table_COLUMNS.TABLE_SCHEMA='{$dbName}' AND table_COLUMNS.TABLE_NAME=",
     *         $viewSql);
     * }
     */
}` },
    { label: 'view_load — 폼 데이터 로딩 후 처리', fn: `
function view_load(&$row) {
    global $actionFlag, $gubun, $idx, $misSessionUserId, $__pdo;
    /*
     * 조회/수정 폼 데이터 로딩 직후 실행
     * $row: 조회된 레코드 연관배열 (수정 가능)
     * $actionFlag: 'view' 또는 'modify'
     *
     * ■ 파일에서 값 로드 (266번 웹소스 참고)
     * $filePath = PROGRAMS_PATH . '/' . $row['real_pid'] . '.php';
     * if (file_exists($filePath)) {
     *     $row['add_logic'] = file_get_contents($filePath);
     * }
     *
     * ■ 계산 필드 추가
     * $row['total'] = (int)($row['price'] ?? 0) * (int)($row['qty'] ?? 0);
     *
     * ■ 수정 모드에서 경고
     * if ($actionFlag === 'modify') {
     *     $GLOBALS['_client_toast'] = '수정 모드입니다. 변경 후 저장해주세요.';
     * }
     *
     * ■ 특정 조건에서 새 탭 열기
     * if ($row['status'] === '긴급') {
     *     $GLOBALS['_client_alert'] = '긴급 건입니다!';
     * }
     */
}` },
  ]},
  { group: '기타(Etc)', items: [
    { label: 'addLogic_treat — 커스텀 API 액션', fn: `
function addLogic_treat(&$result) {
    global $__pdo, $gubun, $idx, $misSessionUserId;
    /*
     * act=treat&gubun=XX 호출 시 실행되는 커스텀 로직
     * 프론트에서: api.treat(gubun, { key: 'value' })
     *
     * ■ 데이터 조회 반환
     * $stmt = $__pdo->query("SELECT * FROM my_table WHERE status='Y'");
     * $result['success'] = true;
     * $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
     *
     * ■ 처리 후 메시지
     * execSql("UPDATE my_table SET processed=1 WHERE idx={$idx}");
     * $result['success'] = true;
     * $result['message'] = '처리 완료';
     */
}` },
  ]},
];

function CodeEditor({ alias, val, readOnly, onChange }) {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const editorRef = useRef(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  // 외부 클릭 닫기
  useEffect(() => {
    if (!menuOpen) return;
    const h = e => { if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, [menuOpen]);

  // 함수명 추출: "function xxx(" → "xxx"
  const getFuncName = (code) => {
    const m = code.match(/function\s+(\w+)\s*\(/);
    return m ? m[1] : '';
  };

  // 에디터에서 함수가 존재하는 줄 번호 찾기
  const findFuncLine = (funcName) => {
    const editor = editorRef.current;
    if (!editor || !funcName) return 0;
    const model = editor.getModel();
    const pattern = new RegExp('function\\s+' + funcName + '\\s*\\(');
    for (let i = 1; i <= model.getLineCount(); i++) {
      if (pattern.test(model.getLineContent(i))) return i;
    }
    return 0;
  };

  // 코드에 함수가 이미 있는지 검사
  const hasFuncInCode = (funcName) => {
    if (!funcName) return false;
    const code = val ?? '';
    return new RegExp('function\\s+' + funcName + '\\s*\\(').test(code);
  };

  const insertSnippet = (code) => {
    const editor = editorRef.current;
    if (!editor) {
      onChange(alias, (val ?? '') + '\n' + code.trim() + '\n');
      setMenuOpen(false);
      return;
    }
    const model = editor.getModel();
    const lastLine = model.getLineCount();
    const lastCol  = model.getLineMaxColumn(lastLine);
    const range = { startLineNumber: lastLine, startColumn: lastCol, endLineNumber: lastLine, endColumn: lastCol };
    const text  = '\n' + code.trim() + '\n';
    editor.executeEdits('snippet', [{ range, text }]);
    const newLastLine = model.getLineCount();
    editor.setPosition({ lineNumber: newLastLine, column: 1 });
    editor.revealLineInCenter(newLastLine);
    editor.focus();
    setMenuOpen(false);
  };

  // 이미 존재하는 함수로 이동
  const goToFunc = (funcName) => {
    const line = findFuncLine(funcName);
    if (!line) return;
    const editor = editorRef.current;
    if (!editor) return;
    editor.revealLineInCenter(line);
    editor.setPosition({ lineNumber: line, column: 1 });
    editor.focus();
    setMenuOpen(false);
  };

  const ensurePhpTag = () => {
    const current = (val ?? '').trim();
    if (!current) {
      onChange(alias, '<?php\n\n');
    }
  };

  return (
    <div className="flex flex-col h-full">
      {/* 툴바 */}
      {!readOnly && (
        <div className="flex items-center gap-2 px-3 py-1.5 border-b border-border-base bg-surface-2 flex-shrink-0">
          <div ref={menuRef} className="relative">
            <button
              type="button"
              className="h-btn-sm px-3 rounded border border-border-base bg-surface text-link text-xs font-semibold cursor-pointer hover:bg-surface-2 transition-colors"
              onClick={() => { ensurePhpTag(); setMenuOpen(v => !v); }}
            >+ 함수 삽입</button>
            {menuOpen && (
              <div className="absolute left-0 top-full mt-1 z-[100] min-w-[360px] max-h-[400px] overflow-auto rounded border border-border-base bg-surface shadow-md">
                {HOOK_TEMPLATES.map(g => (
                  <div key={g.group}>
                    <div className="px-3 py-1.5 text-[10px] font-bold text-muted uppercase tracking-wider bg-surface-2 sticky top-0">{g.group}</div>
                    {g.items.map(item => {
                      const fn = getFuncName(item.fn);
                      const exists = hasFuncInCode(fn);
                      return (
                        <div
                          key={item.label}
                          className={[
                            'px-3 py-2 text-sm cursor-pointer transition-colors border-b border-border-base last:border-b-0 flex items-center gap-2',
                            exists ? 'bg-accent-dim text-link font-semibold hover:bg-accent/20' : 'text-primary hover:bg-surface-2',
                          ].join(' ')}
                          onClick={() => exists ? goToFunc(fn) : insertSnippet(item.fn)}
                        >
                          {exists && <span className="w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0" />}
                          <span className="flex-1">{item.label}</span>
                          {exists && <span className="text-[10px] text-accent flex-shrink-0">이동</span>}
                        </div>
                      );
                    })}
                  </div>
                ))}
              </div>
            )}
          </div>
          <span className="text-muted text-[10px]">저장 시 programs/ 파일에 자동 반영됩니다</span>
        </div>
      )}
      {/* Monaco 에디터 */}
      <div className="flex-1 min-h-0">
        <Suspense fallback={<div className="flex-1 flex items-center justify-center text-muted text-sm">에디터 로딩 중...</div>}>
          <MonacoEditor
            height="100%"
            language={guessLanguage(val)}
            theme={isDark ? 'vs-dark' : 'vs'}
            value={val ?? ''}
            onChange={v => onChange(alias, v ?? '')}
            onMount={editor => { editorRef.current = editor; }}
            options={{
              readOnly,
              minimap: { enabled: true },
              fontSize: 13,
              lineNumbers: 'on',
              wordWrap: 'on',
              scrollBeyondLastLine: false,
              automaticLayout: true,
              tabSize: 4,
              renderWhitespace: 'selection',
              bracketPairColorization: { enabled: true },
              folding: true,
              lineDecorationsWidth: 8,
              padding: { top: 8, bottom: 8 },
            }}
          />
        </Suspense>
      </div>
    </div>
  );
}

/**
 * prime_key 기반 동적 드롭다운
 * onChange(alias, codeValue, displayText) — 3번째 인자로 표시 텍스트 전달
 */
function DropdownSelect({ gubun, field, val, readOnly, onChange, baseCls, ROCls, inputCls, recordIdx, formValues }) {
  const [options, setOptions] = useState([]);
  const [dependsOn, setDependsOn] = useState([]); // ['ca_storage_id1', ...] — prime_key 5번째 세그먼트에서 추출됨

  // 의존하는 form 값들로 ctx 객체 구성 (의존 컬럼들의 현재 form 값)
  const ctxKey = JSON.stringify(dependsOn.reduce((acc, col) => {
    const v = formValues?.[col];
    if (v != null && v !== '') acc[`ctx_${col}`] = String(v);
    return acc;
  }, {}));

  useEffect(() => {
    if (!gubun || !field.alias_name) return;
    const ctx = JSON.parse(ctxKey);
    api.primeKeyItems(gubun, field.alias_name, recordIdx ?? '', ctx)
      .then(d => {
        const opts = Array.isArray(d.data) ? d.data : [];
        setOptions(opts);
        setDependsOn(Array.isArray(d.depends_on) ? d.depends_on : []);
        // 캐스케이드 reset: 현재 val 이 새 옵션에 없으면 자동 클리어
        // (편집 모드 + 의존 dropdown 보유 시에만 — 첫 로딩 무한 reset 방지)
        if (!readOnly && dependsOn.length > 0 && val !== '' && val != null) {
          const exists = opts.some(o => String(o.value) === String(val));
          if (!exists) onChange(field.alias_name, '', '');
        }
      })
      .catch(() => { setOptions([]); setDependsOn([]); });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gubun, field.alias_name, recordIdx, ctxKey]);

  if (readOnly) {
    const matched = options.find(o => o.value === String(val ?? ''));
    const label   = matched ? matched.text : (val ?? '');
    return <span className={ROCls + ' flex items-center' + flexAlign(field.grid_align)}>{label}</span>;
  }

  if (options.length > SEARCHABLE_THRESHOLD) {
    return (
      <SearchableSelect
        options={options}
        value={val ?? ''}
        className={inputCls + ' cursor-pointer'}
        onChange={(code, display) => onChange(field.alias_name, code, display ?? '')}
      />
    );
  }

  // group 필드 있으면 optgroup 으로 묶어 표시 (v6 prime_key 의 'g4name!...' 표기 의도)
  // group 이 모든 option 마다 unique(=1:1) 인 경우는 그루핑 의미 없으므로 flat 으로 표시 (트리 들여쓰기 케이스 등)
  const hasGroupRaw = options.some(o => o.group);
  const groupSet = hasGroupRaw ? new Set(options.map(o => o.group ?? '')) : null;
  const hasGroup = hasGroupRaw && groupSet.size < options.length;
  const groups = hasGroup ? (() => {
    const m = new Map();
    for (const o of options) {
      const k = o.group ?? '';
      if (!m.has(k)) m.set(k, []);
      m.get(k).push(o);
    }
    return [...m.entries()];
  })() : null;

  return (
    <select
      className={inputCls + ' appearance-none cursor-pointer'}
      value={val ?? ''}
      onChange={e => {
        const code    = e.target.value;
        const display = options.find(o => o.value === code)?.text ?? '';
        onChange(field.alias_name, code, display);
      }}
    >
      <option value="">-- 선택 --</option>
      {hasGroup
        ? groups.map(([gLabel, gOpts]) => (
            <optgroup key={gLabel || '_'} label={gLabel || '(기타)'}>
              {gOpts.map(o => <option key={o.value} value={o.value}>{o.text ?? o.value}</option>)}
            </optgroup>
          ))
        : options.map(o => <option key={o.value} value={o.value}>{o.text ?? o.value}</option>)
      }
    </select>
  );
}

/**
 * dropdownitem — grid_items 기반 selectbox
 * items: JSON 배열 [{value,text}] 또는 SELECT SQL
 */
/* ── helplist 팝업 — dropdownlist + prime_key + helplist 조합 ───────
 * v6 의 "..." 버튼 + 팝업 테이블 (다컬럼 표시 + 컬럼별 필터 + 페이징).
 * 행 클릭 시 디스플레이값(첫 컬럼) + 코드값을 동시에 form 에 채움.
 */
function HelplistPair({ gubun, displayField, valueField, displayVal, codeVal, readOnly, onChange }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="flex items-center gap-1 px-1 py-0.5 w-full h-full">
      <input
        type="text"
        readOnly
        className="flex-1 min-w-0 px-2 py-0.5 border border-border-base rounded text-[12px] text-primary bg-surface-2 cursor-default"
        value={displayVal ?? ''}
        title={String(displayVal ?? '')}
      />
      <input
        type="text"
        readOnly
        className="px-2 py-0.5 border border-border-base rounded text-[12px] text-primary bg-surface-2 cursor-default"
        style={{ width: '70px' }}
        value={codeVal ?? ''}
        title={`code: ${codeVal ?? ''}`}
      />
      {!readOnly && (
        <button
          type="button"
          className="w-7 h-6 flex items-center justify-center border border-border-base rounded bg-surface text-secondary hover:text-primary hover:bg-surface-2 cursor-pointer text-[14px] flex-shrink-0"
          onClick={() => setOpen(true)}
          title={`${displayField?.col_title ?? ''} 선택`}
        >…</button>
      )}
      {open && (
        <HelplistPopup
          gubun={gubun}
          fieldAlias={valueField.alias_name}
          title={`${displayField?.col_title ?? ''} 선택`}
          onSelect={(displayText, codeVal) => {
            onChange(displayText, codeVal);
            setOpen(false);
          }}
          onClose={() => setOpen(false)}
        />
      )}
    </div>
  );
}

function HelplistPopup({ gubun, fieldAlias, title, onSelect, onClose }) {
  const [columns, setColumns] = useState([]);
  const [rows, setRows]       = useState([]);
  const [total, setTotal]     = useState(0);
  const [page, setPage]       = useState(1);
  const [filters, setFilters] = useState({});
  const [loading, setLoading] = useState(false);
  const PAGE_SIZE = 20;

  const load = (pg = page, fl = filters) => {
    setLoading(true);
    api.helplistItems(gubun, fieldAlias, pg, PAGE_SIZE, fl)
      .then(d => {
        if (d?.success) {
          setColumns(d.columns || []);
          setRows(d.rows || []);
          setTotal(d.total || 0);
        }
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(1, {}); }, [gubun, fieldAlias]);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const applyFilter = (key, val) => {
    const next = { ...filters, [key]: val };
    setFilters(next);
    setPage(1);
    load(1, next);
  };

  const clearFilters = () => {
    setFilters({});
    setPage(1);
    load(1, {});
  };

  const goPage = (pg) => {
    const p = Math.max(1, Math.min(totalPages, pg));
    setPage(p);
    load(p, filters);
  };

  return createPortal(
    <>
      <div className="fixed inset-0 z-[400] bg-black/50" onClick={onClose} />
      <div className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[401] w-[min(720px,95vw)] max-h-[85vh] bg-surface border border-border-base rounded-lg shadow-pop flex flex-col">
        {/* 헤더 */}
        <div className="flex items-center px-3 py-2 border-b border-border-base bg-surface-2">
          <span className="text-sm font-bold text-primary">{title}</span>
          <span className="ml-2 text-[11px] text-muted">({total}건)</span>
          <div className="flex-1" />
          <button
            type="button"
            className="px-2 py-0.5 mr-1 text-[11px] rounded border border-border-base bg-surface text-secondary hover:text-primary cursor-pointer"
            onClick={clearFilters}
          >필터비우기</button>
          <button
            type="button"
            className="px-2 py-0.5 text-[11px] rounded border border-border-base bg-surface text-secondary hover:text-danger cursor-pointer"
            onClick={() => onSelect('', '')}
          >공백입력</button>
          <button
            type="button"
            className="ml-1 w-6 h-6 flex items-center justify-center text-secondary hover:text-danger border-0 bg-transparent cursor-pointer"
            onClick={onClose}
          >✕</button>
        </div>

        {/* 테이블 */}
        <div className="flex-1 overflow-auto">
          <table className="w-full text-[12px] border-collapse">
            <thead className="sticky top-0 bg-surface-2 z-[1]">
              <tr>
                <th className="px-2 py-1 text-center text-secondary font-semibold border-b border-border-base w-[40px]">No.</th>
                {columns.map(c => (
                  <th key={c.key}
                      className="px-2 py-1 text-left text-secondary font-semibold border-b border-border-base whitespace-nowrap">
                    {c.label}
                  </th>
                ))}
              </tr>
              <tr>
                <th></th>
                {columns.map(c => (
                  <th key={c.key} className="px-1 pb-1 border-b border-border-base">
                    <input
                      type="text"
                      className="w-full px-1.5 py-0.5 border border-border-base rounded text-[11px] outline-none focus:border-accent"
                      style={c.width ? { maxWidth: `${c.width * 12 + 16}px` } : null}
                      value={filters[c.key] ?? ''}
                      onChange={e => applyFilter(c.key, e.target.value)}
                      placeholder="검색..."
                    />
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr><td colSpan={columns.length + 1} className="text-center text-muted py-6 text-[11px]">불러오는 중…</td></tr>
              )}
              {!loading && rows.length === 0 && (
                <tr><td colSpan={columns.length + 1} className="text-center text-muted py-6 text-[11px]">결과 없음</td></tr>
              )}
              {!loading && rows.map((r, i) => (
                <tr key={i}
                    onClick={() => {
                      const displayText = String(r[columns[0]?.key] ?? '');
                      const codeVal = String(r._value ?? '');
                      onSelect(displayText, codeVal);
                    }}
                    className="cursor-pointer hover:bg-accent/10 border-b border-border-light">
                  <td className="px-2 py-1 text-center text-muted">{(page - 1) * PAGE_SIZE + i + 1}</td>
                  {columns.map(c => (
                    <td key={c.key} className="px-2 py-1 text-primary truncate" style={c.width ? { maxWidth: `${c.width * 12 + 16}px` } : null}>
                      {String(r[c.key] ?? '')}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* 페이징 */}
        <div className="flex items-center justify-center gap-2 px-3 py-2 border-t border-border-base bg-surface-2 text-[12px]">
          <button className="px-2 py-0.5 rounded border border-border-base bg-surface text-secondary hover:text-primary cursor-pointer" onClick={() => goPage(1)}>«</button>
          <button className="px-2 py-0.5 rounded border border-border-base bg-surface text-secondary hover:text-primary cursor-pointer" onClick={() => goPage(page - 1)}>‹</button>
          <span className="text-secondary">{page} / {totalPages}</span>
          <button className="px-2 py-0.5 rounded border border-border-base bg-surface text-secondary hover:text-primary cursor-pointer" onClick={() => goPage(page + 1)}>›</button>
          <button className="px-2 py-0.5 rounded border border-border-base bg-surface text-secondary hover:text-primary cursor-pointer" onClick={() => goPage(totalPages)}>»</button>
        </div>
      </div>
    </>,
    document.body
  );
}

/* ── 다중선택 — chip + dropdown UI (v6 스타일) ─────────────────────
 * 선택값은 콤마 구분 문자열로 저장. 표시는 'value | text' 형식.
 */
function MultiselectField({ gubun, field, val, readOnly, onChange, ROCls, inputCls }) {
  const alias    = field.alias_name ?? '';
  const rawItems = field.items ?? '';
  const isSql    = /^\s*select\s+/i.test(rawItems);
  const [options, setOptions] = useState([]);
  const [open, setOpen]       = useState(false);
  const [filter, setFilter]   = useState('');
  const wrapRef = useRef(null);

  useEffect(() => {
    if (isSql) {
      api.dropdownItems(gubun, alias)
        .then(d => setOptions(Array.isArray(d.data) ? d.data : []))
        .catch(() => setOptions([]));
    } else {
      try {
        const parsed = JSON.parse(rawItems);
        if (Array.isArray(parsed)) {
          setOptions(parsed.map(o =>
            typeof o === 'object'
              ? { value: String(o.value ?? ''), text: String(o.text ?? o.value ?? '') }
              : { value: String(o), text: String(o) }
          ));
          return;
        }
      } catch {}
      setOptions(rawItems.split(',').filter(Boolean).map(v => ({ value: v.trim(), text: v.trim() })));
    }
  }, [gubun, alias, rawItems, isSql]);

  // 외부 클릭 시 드롭다운 닫기
  useEffect(() => {
    if (!open) return;
    const onClick = (e) => { if (!wrapRef.current?.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const selected = useMemo(() => {
    if (!val && val !== 0) return new Set();
    return new Set(String(val).split(',').map(s => s.trim()).filter(Boolean));
  }, [val]);

  const toggle = (v) => {
    const next = new Set(selected);
    if (next.has(v)) next.delete(v); else next.add(v);
    onChange(alias, [...next].join(','));
  };

  const labelOf = (v) => {
    const opt = options.find(o => o.value === v);
    if (!opt) return v;
    return opt.text && opt.text !== opt.value ? `${opt.value} | ${opt.text}` : opt.value;
  };

  if (readOnly) {
    const texts = [...selected].map(labelOf);
    return <span className={(ROCls ?? '') + ' flex items-center flex-wrap gap-1'}>{texts.join(', ') || '—'}</span>;
  }

  const filtered = filter
    ? options.filter(o => (o.value + ' ' + (o.text ?? '')).toLowerCase().includes(filter.toLowerCase()))
    : options;

  return (
    <div ref={wrapRef} className="relative w-full">
      <div
        className="flex flex-wrap gap-1 px-1 py-1 border border-border-base rounded bg-surface min-h-[32px] cursor-text items-center overflow-y-auto"
        style={{ maxHeight: '120px' }}
        onClick={() => setOpen(true)}
      >
        {[...selected].map(v => (
          <span key={v}
                className="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 border border-border-base rounded bg-surface text-[12px] text-primary">
            <span>{labelOf(v)}</span>
            <button
              type="button"
              className="w-4 h-4 inline-flex items-center justify-center text-muted hover:text-danger border-0 bg-transparent cursor-pointer leading-none"
              onClick={e => { e.stopPropagation(); toggle(v); }}
              title="제거"
            >×</button>
          </span>
        ))}
        <input
          type="text"
          className="flex-1 min-w-[60px] outline-none border-0 bg-transparent text-[12px] text-primary px-1"
          value={filter}
          onChange={e => { setFilter(e.target.value); setOpen(true); }}
          onFocus={() => setOpen(true)}
          placeholder={selected.size === 0 ? '선택...' : ''}
        />
        {selected.size > 0 && (
          <button
            type="button"
            className="w-5 h-5 inline-flex items-center justify-center text-muted hover:text-danger border-0 bg-transparent cursor-pointer text-[14px]"
            onClick={e => { e.stopPropagation(); onChange(alias, ''); setFilter(''); }}
            title="전체 해제"
          >×</button>
        )}
      </div>
      {open && wrapRef.current && createPortal(
        (() => {
          const rect = wrapRef.current.getBoundingClientRect();
          return (
            <div
              style={{
                position: 'fixed',
                top   : `${rect.bottom + 2}px`,
                left  : `${rect.left}px`,
                width : `${rect.width}px`,
                maxHeight: '260px',
                zIndex: 9999,
              }}
              className="overflow-y-auto bg-surface border border-border-base rounded shadow-pop"
              onMouseDown={(e) => e.stopPropagation()}
            >
              {filtered.length === 0 && (
                <div className="px-2 py-1.5 text-[11px] text-muted">{filter ? '결과 없음' : '항목 없음'}</div>
              )}
              {filtered.map(o => {
                const isSel = selected.has(o.value);
                const label = o.text && o.text !== o.value ? `${o.value} | ${o.text}` : o.value;
                return (
                  <div key={o.value}
                       onClick={(e) => { e.stopPropagation(); toggle(o.value); }}
                       className={[
                         'px-2 py-1 text-[12px] cursor-pointer truncate',
                         isSel ? 'bg-accent text-white font-medium' : 'text-primary hover:bg-surface-2',
                       ].join(' ')}>
                    {label}
                  </div>
                );
              })}
            </div>
          );
        })(),
        document.body
      )}
    </div>
  );
}

/* ── 2단계 트리 + 체크박스 다중선택 ────────────────────────────────────
 * items SQL 의 text 가 "value | parent > leaf" 형식이면 parent 로 그룹화.
 * (saeopjang3 의 SELECT: concat(kname,' | ',kname2,' > ',kname))
 * 형식 다르면 평면 리스트로 폴백.
 */
function DropdownTreeField({ gubun, field, val, readOnly, onChange, ROCls, inputCls }) {
  const alias    = field.alias_name ?? '';
  const rawItems = field.items ?? '';
  const isSql    = /^\s*select\s+/i.test(rawItems);
  const [options, setOptions] = useState([]);
  const [collapsed, setCollapsed] = useState(() => new Set());

  useEffect(() => {
    if (isSql) {
      api.dropdownItems(gubun, alias)
        .then(d => setOptions(Array.isArray(d.data) ? d.data : []))
        .catch(() => setOptions([]));
    } else {
      try {
        const parsed = JSON.parse(rawItems);
        if (Array.isArray(parsed)) {
          setOptions(parsed.map(o =>
            typeof o === 'object'
              ? { value: String(o.value ?? ''), text: String(o.text ?? o.value ?? '') }
              : { value: String(o), text: String(o) }
          ));
          return;
        }
      } catch {}
      setOptions(rawItems.split(',').filter(Boolean).map(v => ({ value: v.trim(), text: v.trim() })));
    }
  }, [gubun, alias, rawItems, isSql]);

  // text "value | parent > leaf" → 그룹화
  const groups = useMemo(() => {
    const m = new Map();
    for (const o of options) {
      const t = String(o.text ?? '');
      // "code | parent > leaf" 또는 "parent > leaf" 모두 처리
      let parent = '', leaf = t;
      const mm = t.match(/^(?:.+?\s*\|\s*)?(.+?)\s*>\s*(.+)$/);
      if (mm) { parent = mm[1].trim(); leaf = mm[2].trim(); }
      else { parent = '(기타)'; leaf = t; }
      if (!m.has(parent)) m.set(parent, []);
      m.get(parent).push({ value: o.value, text: leaf });
    }
    return [...m.entries()];
  }, [options]);

  const selected = useMemo(() => {
    if (!val && val !== 0) return new Set();
    return new Set(String(val).split(',').map(s => s.trim()).filter(Boolean));
  }, [val]);

  const toggleVal = (v) => {
    const next = new Set(selected);
    if (next.has(v)) next.delete(v); else next.add(v);
    onChange(alias, [...next].join(','));
  };

  // 부모 그룹 모든 자식 토글 (전체선택 / 전체해제)
  const toggleParent = (children) => {
    const allChecked = children.every(c => selected.has(c.value));
    const next = new Set(selected);
    if (allChecked) children.forEach(c => next.delete(c.value));
    else children.forEach(c => next.add(c.value));
    onChange(alias, [...next].join(','));
  };

  const toggleCollapse = (parent) => {
    setCollapsed(prev => {
      const n = new Set(prev);
      if (n.has(parent)) n.delete(parent); else n.add(parent);
      return n;
    });
  };

  if (readOnly) {
    const texts = [...selected].map(v => {
      const opt = options.find(o => o.value === v);
      const t = opt?.text ?? v;
      const mm = t.match(/^(?:.+?\s*\|\s*)?(.+?)\s*>\s*(.+)$/);
      return mm ? `${mm[1].trim()} > ${mm[2].trim()}` : t;
    });
    return <span className={(ROCls ?? '') + ' flex items-center flex-wrap gap-1'}>{texts.join(', ') || '—'}</span>;
  }

  return (
    <div className="w-full h-full overflow-y-auto border border-border-base rounded bg-surface text-[12px] min-h-[32px] py-0.5">
      {groups.length === 0 && <span className="text-[11px] text-muted px-2 py-1 block">선택 항목 없음</span>}
      {groups.map(([parent, children]) => {
        const allChecked  = children.every(c => selected.has(c.value));
        const someChecked = !allChecked && children.some(c => selected.has(c.value));
        const isCollapsed = collapsed.has(parent);
        return (
          <div key={parent}>
            <div className="flex items-center gap-1 px-1 py-0.5 bg-surface-2 cursor-pointer hover:bg-base"
                 onClick={() => toggleCollapse(parent)}>
              <span className="text-[9px] w-3 text-center text-muted select-none">{isCollapsed ? '▶' : '▼'}</span>
              <input
                type="checkbox"
                className="w-3.5 h-3.5 cursor-pointer accent-accent"
                checked={allChecked}
                ref={el => { if (el) el.indeterminate = someChecked; }}
                onChange={() => toggleParent(children)}
                onClick={e => e.stopPropagation()}
              />
              <span className="font-semibold text-secondary truncate">{parent}</span>
              <span className="text-[10px] text-muted">({children.filter(c => selected.has(c.value)).length}/{children.length})</span>
            </div>
            {!isCollapsed && children.map(c => (
              <label key={c.value}
                     className="flex items-center gap-1 pl-7 pr-1 py-0.5 hover:bg-surface-2 cursor-pointer">
                <input
                  type="checkbox"
                  className="w-3.5 h-3.5 cursor-pointer accent-accent"
                  checked={selected.has(c.value)}
                  onChange={() => toggleVal(c.value)}
                />
                <span className="truncate">{c.text}</span>
              </label>
            ))}
          </div>
        );
      })}
    </div>
  );
}

function DropdownItemSelect({ gubun, field, val, readOnly, onChange, baseCls, ROCls, inputCls }) {
  const alias   = field.alias_name ?? '';
  const rawItems = field.items ?? '';
  const isSql   = /^\s*select\s+/i.test(rawItems);
  const [options, setOptions] = useState([]);

  useEffect(() => {
    if (isSql) {
      api.dropdownItems(gubun, alias)
        .then(d => setOptions(Array.isArray(d.data) ? d.data : []))
        .catch(() => setOptions([]));
    } else {
      try {
        const parsed = JSON.parse(rawItems);
        setOptions(Array.isArray(parsed)
          ? parsed.map(o => typeof o === 'object' ? { value: String(o.value ?? ''), text: String(o.text ?? o.value ?? '') } : { value: String(o), text: String(o) })
          : []);
      } catch {
        setOptions(rawItems.split(',').filter(Boolean).map(v => ({ value: v.trim(), text: v.trim() })));
      }
    }
  }, [gubun, alias, rawItems, isSql]);

  if (readOnly) {
    const matched = options.find(o => o.value === String(val ?? ''));
    return <span className={(ROCls ?? '') + ' flex items-center' + flexAlign(field.grid_align)}>{matched ? matched.text : (val ?? '')}</span>;
  }

  if (options.length > SEARCHABLE_THRESHOLD) {
    return (
      <SearchableSelect
        options={options}
        value={val ?? ''}
        className={(inputCls ?? '') + ' cursor-pointer'}
        onChange={(code) => onChange(alias, code)}
      />
    );
  }

  return (
    <select
      className={(inputCls ?? '') + ' appearance-none cursor-pointer'}
      value={val ?? ''}
      onChange={e => onChange(alias, e.target.value)}
    >
      <option value="">-- 선택 --</option>
      {options.map(o => <option key={o.value} value={o.value}>{o.text}</option>)}
    </select>
  );
}

/**
 * 첨부파일 max_length 파싱
 * '5' → { maxMB: 5, multi: false }
 * '5!' → { maxMB: 5, multi: true }
 */
export function parseAttachLimit(raw) {
  // 형식:
  //   "50"     → 단일, 50MB 까지
  //   "50!"    → 다중, 50MB 까지, 갯수제한 없음
  //   "50!20"  → 다중, 50MB 까지, 최대 20개
  const s = String(raw ?? '').trim();
  const m = /^(\d+)(!(\d*))?$/.exec(s);
  if (!m) return { maxMB: 20, multi: false, maxCount: 0 };
  const maxMB    = parseInt(m[1], 10) || 20;
  const multi    = m[2] !== undefined;
  const maxCount = m[3] ? parseInt(m[3], 10) : 0; // 0 = 제한 없음
  return { maxMB, multi, maxCount };
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + 'B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB';
  return (bytes / (1024 * 1024)).toFixed(1) + 'MB';
}

/**
 * 첨부파일 업로드/목록/다운로드/삭제 컴포넌트
 */
const IMG_EXTS = new Set(['jpg','jpeg','png','gif','webp','bmp','svg']);
const FILE_ICONS = {
  pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊', csv: '📊',
  ppt: '📎', pptx: '📎', hwp: '📝', txt: '📃', zip: '📦', rar: '📦', '7z': '📦',
};
function getFileExt(name) { return (name ?? '').split('.').pop()?.toLowerCase() ?? ''; }
function isImageMime(mime) { return (mime ?? '').startsWith('image/'); }
function fileIcon(name, mime) {
  if (isImageMime(mime)) return null; // 이미지는 썸네일로 대체
  return FILE_ICONS[getFileExt(name)] ?? '📎';
}
/**
 * 이미지 첨부 → 섬네일 URL 변환 (tools/thumbnail.php 경유)
 * 허용 prefix: /data/item/...  /uploadFiles/...   (그 외엔 원본 URL 그대로)
 * 클릭 시 라이트박스는 원본을 사용 — 본 함수는 썸네일 표시용 URL 만 반환.
 */
function thumbUrl(url, width = 128) {
  if (!url) return url;
  if (typeof url !== 'string') return url;
  if (!/^\/(data\/item|uploadFiles)\//.test(url)) return url;
  // 쿼리스트링 안전 처리 — '+' 는 공백으로 디코딩되므로 명시적 %2B, '#' 도 인코딩
  const safe = url.replace(/\+/g, '%2B').replace(/#/g, '%23').replace(/\?/g, '%3F').replace(/&/g, '%26');
  return apiPath('/tools/thumbnail.php') + '?w=' + width + '&' + safe;
}

/**
 * 첨부 원본 URL — `/uploadFiles/...` `/data/item/...` 그대로 사용.
 * nginx 에 `/uploadFiles/` 와 `/data/item/` 모두 자체 location 이 매핑되어 있어
 * v7 prefix 없이 정적 접근 가능.
 */
function attachUrl(url) { return url; }

export function FileAttach({ gubun, idx, realPid, alias, readOnly, multi, maxMB, maxCount = 0, allowExts, mode, midx, onTempChange, immediate = false }) {
  // 기존 저장된 파일 (midx 기준으로 로드)
  const [files, setFiles]         = useState([]);
  // immediate(목록 인라인) 모드: 업로드 즉시 finalize 하므로 midx 가 0→N 으로 바뀔 수 있어 내부 상태로 추적
  const [curMidx, setCurMidx]     = useState(midx);
  // temp 업로드된 파일 목록 [{ token, orig_name, size, mime }]
  const [tempFiles, setTempFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [error, setError]         = useState('');
  const [lightbox, setLightbox]   = useState(null); // null | index (number)
  const fileRef = React.useRef(null);

  const extList = (() => {
    const raw = (allowExts || '').trim();
    if (!raw) return [];
    // JSON 또는 불완전한 JSON ("allowedExtensions": [...]) 감지
    if (raw.includes('allowedExtensions') || raw.startsWith('{') || raw.startsWith('"')) {
      try {
        const json = raw.startsWith('{') ? raw : `{${raw}}`;
        const parsed = JSON.parse(json);
        const arr = parsed.allowedExtensions ?? parsed.ext ?? [];
        return arr.map(s => s.replace(/^\./, '').trim().toLowerCase()).filter(Boolean);
      } catch { /* fallthrough */ }
    }
    return raw.split(',').map(s => s.replace(/^\./, '').trim().toLowerCase()).filter(Boolean);
  })();
  const acceptAttr = extList.length > 0 ? extList.map(e => '.' + e).join(',') : undefined;

  // 부모가 넘긴 midx 변경 시 내부 상태 동기화
  useEffect(() => { setCurMidx(midx); }, [midx]);

  // midx 변경 시 기존 파일 로드
  useEffect(() => {
    if (!curMidx || curMidx <= 0) { setFiles([]); return; }
    api.fileList(curMidx).then(d => setFiles(d.data ?? [])).catch(() => {});
  }, [curMidx]);

  // temp 토큰 리스트를 부모(form)에 실시간 통지
  useEffect(() => {
    onTempChange?.(alias, tempFiles.map(t => t.token));
  }, [tempFiles, alias, onTempChange]);

  const handleUpload = async (e) => {
    const selected = Array.from(e.target.files ?? []);
    if (!selected.length) return;
    e.target.value = '';
    setError('');

    let toUpload = multi ? selected : [selected[0]];

    // 갯수 제한 (maxCount > 0 인 경우만 적용)
    if (maxCount > 0) {
      const already   = files.length + tempFiles.length;
      const remaining = Math.max(0, maxCount - already);
      if (remaining === 0) {
        setError(`최대 ${maxCount}개까지만 업로드 가능합니다. (현재 ${already}개)`);
        return;
      }
      if (toUpload.length > remaining) {
        setError(`최대 ${maxCount}개까지만 업로드 가능합니다. ${remaining}개만 추가됩니다.`);
        toUpload = toUpload.slice(0, remaining);
      }
    }

    if (extList.length > 0) {
      const badExt = toUpload.filter(f => !extList.includes(getFileExt(f.name)));
      if (badExt.length) {
        setError(`허용되지 않는 파일 형식 (${extList.join(', ')}만 가능): ${badExt.map(f => f.name).join(', ')}`);
        return;
      }
    }

    const oversized = toUpload.filter(f => f.size > maxMB * 1024 * 1024);
    if (oversized.length) {
      setError(`파일 크기 초과 (최대 ${maxMB}MB): ${oversized.map(f => f.name).join(', ')}`);
      return;
    }

    setUploading(true);
    try {
      const added = [];
      for (const f of toUpload) {
        const res = await api.fileUpload(f);
        if (!res.success) { setError(res.message ?? '업로드 실패'); break; }
        added.push({
          token: res.token,
          orig_name: res.orig_name,
          size: res.size,
          mime: res.mime,
          previewUrl: f.type.startsWith('image/') ? URL.createObjectURL(f) : null,
          isImage: f.type.startsWith('image/'),
        });
      }
      setTempFiles(prev => multi ? [...prev, ...added] : added);

      // immediate(목록 인라인) 모드: 폼 저장이 없으므로 업로드 즉시 finalize (수정폼의 저장과 동일 경로)
      if (immediate && idx > 0 && added.length) {
        await api.save(gubun, { _tempAttach: { [alias]: added.map(a => a.token) }, _listEdit: true }, idx);
        setTempFiles([]); // finalize 완료 → 대기 목록 비움
        let nm = curMidx;
        if (!nm || nm <= 0) {
          // 신규 그룹: 갱신된 _midx 를 행에서 다시 읽어옴
          const v = await api.view(gubun, idx);
          nm = parseInt(v?.data?.[alias + '_midx'] ?? 0, 10) || 0;
          setCurMidx(nm);
        }
        if (nm > 0) { const d = await api.fileList(nm); setFiles(d.data ?? []); }
        window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
      }
    } catch (ex) {
      setError(ex.message);
    } finally {
      setUploading(false);
    }
  };

  const handleDeleteTemp = (token) => {
    setTempFiles(prev => prev.filter(t => t.token !== token));
  };

  const handleDeleteSaved = async (attachIdx) => {
    if (!confirm('파일을 삭제하시겠습니까?')) return;
    try {
      await api.fileDelete(attachIdx);
      setFiles(prev => prev.filter(f => f.idx !== attachIdx));
      if (immediate) window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
    } catch (ex) {
      setError(ex.message);
    }
  };

  const isImgMime = (m) => /^image\//.test(m ?? '');
  const total = files.length + tempFiles.length;

  return (
    <div className="flex flex-col gap-1.5 px-2 py-1.5 h-full overflow-auto">
      {error && <div className="text-xs text-danger">{error}</div>}

      {/* 저장된 이미지 썸네일 (midx 기준) */}
      {files.filter(f => isImgMime(f.attach_mime)).length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {files.filter(f => isImgMime(f.attach_mime)).map((f, i) => {
            // 6083 의 it_img_mis 필드: 첫 이미지엔 일괄다운로드, 나머지엔 대표이미지로 버튼
            const showActions = !readOnly && realPid === 'carparts006083' && alias === 'it_img_mis' && idx;
            return (
              <div key={`s-${f.idx}`} className="flex flex-col items-center gap-0.5">
                <div className="relative group rounded border border-border-base bg-surface-2 overflow-hidden cursor-pointer"
                  style={{ width: 64, height: 64 }}
                  onClick={() => setLightbox(i)}>
                  <img src={thumbUrl(f.attach_url, 128)} alt={f.attach_name} className="w-full h-full object-cover" loading="lazy" />
                  {/* 다운로드 (원본 파일명 보존) — 항상 노출 */}
                  <a
                    href={attachUrl(f.attach_url)}
                    download={f.attach_name}
                    target="_blank" rel="noopener noreferrer"
                    className="absolute bottom-0 left-0 w-4 h-4 flex items-center justify-center bg-black/60 text-white text-[9px] leading-none opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer no-underline rounded-tr"
                    onClick={(e) => e.stopPropagation()}
                    title={`다운로드: ${f.attach_name}`}>💾</a>
                  {!readOnly && (
                    <button type="button"
                      className="absolute top-0 right-0 w-4 h-4 flex items-center justify-center bg-danger text-white text-[9px] leading-none opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer border-0 rounded-bl"
                      onClick={(e) => { e.stopPropagation(); handleDeleteSaved(f.idx); }}
                      title="삭제">✕</button>
                  )}
                </div>
                {showActions && (
                  i === 0 ? (
                    <a
                      href={`${(window.__APP_CONFIG__?.basePath ?? '')}/api.php?act=zipDownloadImages&it_id=${encodeURIComponent(idx)}`}
                      target="_blank" rel="noopener noreferrer"
                      className="text-[10px] text-link hover:underline whitespace-nowrap"
                      title="이 상품의 첨부 이미지 전체 zip 다운로드"
                    >📦 일괄다운로드</a>
                  ) : (
                    <button
                      type="button"
                      className="text-[10px] text-link hover:underline cursor-pointer bg-transparent border-0 px-0 whitespace-nowrap"
                      onClick={async () => {
                        const fileName = (f.attach_url || '').split('/').pop().split('?')[0];
                        if (!fileName) return;
                        try {
                          const res = await api.treat(gubun, { action: 'select_top_img', idx, top_img: fileName });
                          const d = res.data ?? {};
                          if (d._client_toast) showToast(d._client_toast);
                          if (d._client_alert) alert(d._client_alert);
                          // 폼 + 이미지 리스트 재로딩
                          if (d.success !== false) {
                            window.dispatchEvent(new CustomEvent('mis:reloadForm'));
                            window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
                          }
                        } catch (e) {
                          showToast(e.message || '대표이미지 변경 실패');
                        }
                      }}
                      title="이 이미지를 대표(첫번째)로 이동"
                    >👑 대표이미지로</button>
                  )
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* 임시 업로드된 이미지 썸네일 */}
      {tempFiles.filter(t => t.isImage).length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {tempFiles.filter(t => t.isImage).map(t => (
            <div key={`t-${t.token}`} className="relative group rounded border border-accent bg-surface-2 overflow-hidden"
              style={{ width: 64, height: 64 }}>
              <img src={t.previewUrl} alt={t.orig_name} className="w-full h-full object-cover" />
              <span className="absolute bottom-0 left-0 right-0 bg-accent text-white text-[9px] text-center py-[1px]">대기</span>
              {!readOnly && (
                <button type="button"
                  className="absolute top-0 right-0 w-4 h-4 flex items-center justify-center bg-danger text-white text-[9px] leading-none opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer border-0 rounded-bl"
                  onClick={() => handleDeleteTemp(t.token)} title="삭제">✕</button>
              )}
            </div>
          ))}
        </div>
      )}

      {/* 저장된 비-이미지 파일 */}
      {files.filter(f => !isImgMime(f.attach_mime)).map(f => (
        <div key={`sf-${f.idx}`} className="flex items-center gap-1.5 text-xs group">
          <span className="flex-shrink-0">{fileIcon(f.attach_name, f.attach_mime) ?? '📎'}</span>
          <a href={attachUrl(f.attach_url)} target="_blank" rel="noopener noreferrer"
            className="text-link hover:underline truncate flex-1 min-w-0" title={f.attach_name}
          >{f.attach_name}</a>
          <span className="text-muted flex-shrink-0">{formatFileSize(f.attach_size)}</span>
          {/* 다운로드 (원본 파일명 보존) */}
          <a href={attachUrl(f.attach_url)} download={f.attach_name}
            target="_blank" rel="noopener noreferrer"
            className="flex-shrink-0 text-secondary hover:text-primary opacity-0 group-hover:opacity-100 transition-opacity no-underline px-0.5"
            title={`다운로드: ${f.attach_name}`}>💾</a>
          {!readOnly && (
            <button type="button"
              className="text-danger opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer bg-transparent border-0 text-xs px-0.5"
              onClick={() => handleDeleteSaved(f.idx)} title="삭제">✕</button>
          )}
        </div>
      ))}

      {/* 임시 업로드된 비-이미지 파일 */}
      {tempFiles.filter(t => !t.isImage).map(t => (
        <div key={`tf-${t.token}`} className="flex items-center gap-1.5 text-xs group">
          <span className="flex-shrink-0">{fileIcon(t.orig_name, t.mime) ?? '📎'}</span>
          <span className="text-accent truncate flex-1 min-w-0" title={t.orig_name}>{t.orig_name}</span>
          <span className="text-accent text-[10px] flex-shrink-0">대기</span>
          <span className="text-muted flex-shrink-0">{formatFileSize(t.size)}</span>
          {!readOnly && (
            <button type="button"
              className="text-danger opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer bg-transparent border-0 text-xs px-0.5"
              onClick={() => handleDeleteTemp(t.token)} title="삭제">✕</button>
          )}
        </div>
      ))}

      {/* 업로드 버튼 — 갯수제한 도달 시 자동 숨김 */}
      {!readOnly && (multi || total === 0) && !(maxCount > 0 && total >= maxCount) && (
        <div className="flex items-center gap-1.5">
          <input ref={fileRef} type="file" className="hidden" multiple={multi} accept={acceptAttr} onChange={handleUpload} />
          <button type="button" disabled={uploading}
            className="h-btn-sm px-2.5 rounded border border-border-base bg-surface-2 text-secondary text-xs cursor-pointer hover:bg-surface hover:text-primary transition-colors flex-shrink-0 whitespace-nowrap disabled:opacity-60"
            onClick={() => fileRef.current?.click()}
          >{uploading ? '업로드 중...' : `파일 첨부 (${maxMB}MB${extList.length > 0 ? ` · ${extList.join(',')}` : ''}${maxCount > 0 ? ` · ${total}/${maxCount}` : ''})`}</button>
          {multi && <span className="text-muted text-[10px]">{maxCount > 0 ? `최대 ${maxCount}개` : '복수 가능'}</span>}
        </div>
      )}
      {!readOnly && maxCount > 0 && total >= maxCount && (
        <span className="text-muted text-[10px]">최대 {maxCount}개 첨부됨 — 추가하려면 일부 삭제</span>
      )}

      {readOnly && total === 0 && (
        <span className="text-muted text-sm flex items-center h-full">-</span>
      )}

      {/* 이미지 라이트박스 — 좌우 네비 + 하단 썸네일 스트립 */}
      {lightbox !== null && (
        <ImageGallery
          images={files.filter(f => isImgMime(f.attach_mime)).map(f => ({ url: attachUrl(f.attach_url), name: f.attach_name }))}
          startIndex={typeof lightbox === 'number' ? lightbox : 0}
          onClose={() => setLightbox(null)}
        />
      )}
    </div>
  );
}

/**
 * 첨부 이미지 라이트박스 갤러리 — 좌우 네비, 하단 썸네일, 키보드, 스와이프(터치)
 * props: images=[{url, name}], startIndex, onClose
 */
function ImageGallery({ images, startIndex, onClose }) {
  const [idx, setIdx] = useState(() => {
    const n = Number(startIndex) || 0;
    return Math.max(0, Math.min(images.length - 1, n));
  });
  const total = images.length;
  const cur = images[idx];

  const prev = useCallback(() => setIdx(i => (i - 1 + total) % total), [total]);
  const next = useCallback(() => setIdx(i => (i + 1) % total), [total]);

  // 키보드 — 좌/우/Esc
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); prev(); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
      else if (e.key === 'Escape') { e.preventDefault(); onClose?.(); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [prev, next, onClose]);

  // 터치 스와이프 — 좌우
  const touchRef = useRef({ x: 0, y: 0 });
  const onTouchStart = (e) => {
    const t = e.touches[0];
    touchRef.current = { x: t.clientX, y: t.clientY };
  };
  const onTouchEnd = (e) => {
    const t = e.changedTouches[0];
    const dx = t.clientX - touchRef.current.x;
    const dy = t.clientY - touchRef.current.y;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
      if (dx > 0) prev(); else next();
    }
  };

  // 활성 썸네일을 화면 안으로 자동 스크롤
  const stripRef = useRef(null);
  useEffect(() => {
    const strip = stripRef.current; if (!strip) return;
    const active = strip.querySelector(`[data-idx="${idx}"]`);
    if (active && active.scrollIntoView) {
      active.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
  }, [idx]);

  if (!cur) return null;

  return (
    <div
      className="fixed inset-0 z-[200] flex flex-col"
      style={{ background: 'rgba(0,0,0,0.85)' }}
      onClick={onClose}
      onTouchStart={onTouchStart}
      onTouchEnd={onTouchEnd}
    >
      {/* 상단 헤더 — 카운트 + 닫기 */}
      <div className="flex items-center justify-between px-4 py-2 text-white/80 text-xs flex-shrink-0"
           onClick={(e) => e.stopPropagation()}>
        <span className="truncate" title={cur.name}>{cur.name ?? ''}</span>
        <span className="ml-2 flex-shrink-0">{idx + 1} / {total}</span>
        <button
          type="button"
          onClick={onClose}
          className="ml-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white text-lg cursor-pointer border-0"
          title="닫기 (Esc)"
        >✕</button>
      </div>

      {/* 메인 이미지 영역 */}
      <div className="flex-1 flex items-center justify-center relative overflow-hidden" onClick={(e) => e.stopPropagation()}>
        {total > 1 && (
          <button
            type="button"
            onClick={prev}
            className="absolute left-2 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl cursor-pointer border-0 transition-colors"
            title="이전 (←)"
          >‹</button>
        )}
        <img
          src={cur.url}
          alt={cur.name ?? ''}
          className="max-w-[92vw] max-h-[78vh] rounded shadow-lg object-contain select-none"
          draggable={false}
        />
        {total > 1 && (
          <button
            type="button"
            onClick={next}
            className="absolute right-2 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl cursor-pointer border-0 transition-colors"
            title="다음 (→)"
          >›</button>
        )}
      </div>

      {/* 하단 썸네일 스트립 */}
      {total > 1 && (
        <div
          ref={stripRef}
          className="flex-shrink-0 flex gap-1.5 px-3 py-2 overflow-x-auto"
          style={{ background: 'rgba(0,0,0,0.5)', scrollbarWidth: 'thin' }}
          onClick={(e) => e.stopPropagation()}
        >
          {images.map((im, i) => (
            <button
              key={i}
              type="button"
              data-idx={i}
              onClick={() => setIdx(i)}
              className="flex-shrink-0 cursor-pointer rounded overflow-hidden border-2 transition-all bg-transparent p-0"
              style={{
                width: 60, height: 60,
                borderColor: i === idx ? '#4F6EF7' : 'transparent',
                opacity: i === idx ? 1 : 0.55,
              }}
              title={im.name ?? ''}
            >
              <img src={thumbUrl(im.url, 128)} alt={im.name ?? ''} className="w-full h-full object-cover" loading="lazy" draggable={false} />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

/**
 * 다음(Daum) 우편번호 검색
 * aliases: { zipcode: 직전필드alias, address: 현재필드alias, detail: 직후필드alias }
 */
let daumScriptLoaded = false;
function loadDaumPostcode() {
  return new Promise((resolve) => {
    if (daumScriptLoaded && window.daum?.Postcode) { resolve(); return; }
    const s = document.createElement('script');
    s.src = '//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
    s.onload = () => { daumScriptLoaded = true; resolve(); };
    document.head.appendChild(s);
  });
}

function ZipcodeInput({ val, readOnly, aliases, onChange }) {
  const baseCls = 'w-full h-full px-2 text-base text-primary bg-transparent outline-none border-0';
  const ROCls   = baseCls + ' text-secondary cursor-default';
  const inputCls = readOnly ? ROCls : baseCls + ' focus:ring-1 focus:ring-inset focus:ring-accent';

  if (readOnly) return <span className={ROCls + ' flex items-center'}>{val ?? ''}</span>;

  const handleSearch = async () => {
    await loadDaumPostcode();
    new window.daum.Postcode({
      oncomplete(data) {
        const addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;
        if (aliases.zipcode) onChange(aliases.zipcode, data.zonecode);
        onChange(aliases.address, addr);
        if (aliases.detail)  onChange(aliases.detail, '');
      },
    }).open();
  };

  return (
    <div className="flex items-center h-full gap-1 pr-1">
      <input className={inputCls + ' flex-1 min-w-0'} type="text" value={val ?? ''} readOnly
        placeholder="우편번호 검색 버튼을 누르세요" />
      <button type="button"
        className="h-btn-sm px-2.5 rounded border border-border-base bg-surface-2 text-secondary text-xs cursor-pointer hover:bg-surface hover:text-primary transition-colors flex-shrink-0 whitespace-nowrap"
        onClick={handleSearch}
      >우편번호 검색</button>
    </div>
  );
}

/**
 * table_XXX_qnYYY 패턴에서 valueAlias(XXX) 추출
 * 예: table_auth_code_qnkname → 'auth_code'
 *     table_new_gidx_qngname → 'new_gidx'
 */
function parseQnAlias(alias) {
  if (!alias.startsWith('table_')) return null;
  const inner = alias.slice('table_'.length);          // position_codeQnkname
  // Qn (대문자) 또는 _qn (소문자) 모두 지원
  let idx = inner.indexOf('Qn');
  if (idx <= 0) idx = inner.indexOf('_qn');
  if (idx <= 0) return null;
  // Qn 앞에 _ 가 있으면 제거
  let val = inner.slice(0, idx);
  if (val.endsWith('_')) val = val.slice(0, -1);
  return val;                                          // position_code
}

/**
 * 포맷이 있는 number 필드 (number^^#,##0 원 등) 의 편집 모드 input.
 * 포커스 = raw 숫자 (편집), blur = 포맷된 표시. Excel 셀 동작과 동일.
 */
function FormattedNumberInput({ alias, val, schemaType, maxLen, inputCls, onChange }) {
  const [focused, setFocused] = useState(false);
  const display = focused ? (val ?? '') : formatBySchema(val, schemaType);
  return (
    <input
      className={inputCls + ' text-right tabular-nums'}
      type="text"
      inputMode="numeric"
      value={display}
      maxLength={maxLen}
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
      onChange={(e) => {
        // 입력 시 콤마·단위 같이 입력돼도 raw 숫자만 추출 (소수점·음수 허용)
        const raw = e.target.value.replace(/[^0-9.\-]/g, '');
        onChange(alias, raw);
      }}
    />
  );
}

function renderInput(field, val, readOnly, onChange, hRows = 1, gubun = 0, inputPx = 0, extra = {}) {
  const alias   = field.alias_name     ?? '';
  const type    = field.schema_type    ?? 'text';
  const ctlName = field.grid_ctl_name ?? '';
  // max_length 음수(-14)는 절댓값(14)을 입력 한도로 사용. 0/빈값은 기본 200.
  const maxLen  = Math.abs(parseInt(field.max_length ?? '200', 10)) || 200;
  // grid_align: input 은 text-align, flex 표시(<span readonly>)는 justify-content
  const alignCls = field.grid_align === 'center' ? ' text-center'
                 : field.grid_align === 'right'  ? ' text-right'
                 : '';
  const flexAlignCls = field.grid_align === 'center' ? ' justify-center'
                     : field.grid_align === 'right'  ? ' justify-end'
                     : '';

  // schema_type=html 은 읽기전용/제어없음 모드에서 태그를 실제로 렌더링
  if (type === 'html' && (readOnly || !ctlName)) {
    return (
      <div
        className={"w-full h-full px-2 py-1 text-base text-primary overflow-auto cell-html flex items-center" + flexAlignCls}
        dangerouslySetInnerHTML={{ __html: String(val ?? '') }}
      />
    );
  }

  // 객체명(컨트롤)이 없으면 → 읽기전용 텍스트 출력 (입력글수만 있어도 편집 불가)
  // schema_type 에 ^^ 포맷(예: number^^#,##0, date^^MM-dd) 이 있으면 그대로 적용
  if (!ctlName) {
    const hasFmt = typeof type === 'string' && type.includes('^^');
    const display = hasFmt ? formatBySchema(val, type) : (val ?? '');
    return <span className={"w-full h-full px-2 text-base text-secondary bg-transparent cursor-default flex items-center" + flexAlignCls}>{display}</span>;
  }
  const items   = field.items ?? '';

  const baseCls = 'w-full h-full px-2 text-base text-primary bg-transparent outline-none border-0' + alignCls;
  const ROCls   = baseCls + ' text-secondary cursor-default';

  const inputCls = readOnly
    ? ROCls
    : baseCls + ' border-b border-accent/30 focus:border-accent transition-colors';

  // grid_ctl_name='datepicker' / 'timepicker' — schema_type 이 비어있어도 native picker 사용
  if (ctlName === 'datepicker' || ctlName === 'timepicker') {
    const isTime = ctlName === 'timepicker';
    const slice  = isTime ? 8 : 10;
    const dv     = val ? String(val).slice(0, slice) : '';
    if (readOnly) {
      // schema_type 에 커스텀 포맷(date^^MM-dd 등) 있으면 적용
      if (typeof type === 'string' && type.includes('^^')) {
        return <span className={ROCls + ' flex items-center' + flexAlignCls} title={String(val ?? '')}>{formatBySchema(val, type)}</span>;
      }
      return <span className={ROCls + ' flex items-center' + flexAlignCls}>{dv}</span>;
    }
    return (
      <input className={inputCls} type={isTime ? 'time' : 'date'} value={dv}
        onChange={e => onChange(alias, e.target.value)} />
    );
  }

  // attach/image 는 메인 렌더 루프에서 FileAttach 로 처리됨

  // textdecrypt2: 암호화 필드 — 모드별 처리
  //   view(readOnly): ••••• 마스킹
  //   write: 일반 text input + default 값 그대로 보임 (체크박스 X — 신규는 무조건 새 값)
  //   modify: 빈 input + 체크박스 (체크 후 신규 입력 — 기존 값 노출 X)
  if (ctlName === 'textdecrypt2') {
    if (readOnly) return <span className={ROCls + ' flex items-center' + flexAlignCls}>•••••</span>;

    // write: default 값을 그대로 보여주는 일반 text 입력
    if (extra.mode === 'write') {
      return (
        <input
          type="text"
          className={inputCls}
          value={val ?? ''}
          maxLength={maxLen}
          onChange={e => onChange(alias, e.target.value)}
          autoComplete="new-password"
        />
      );
    }

    // modify: 빈 input + 체크박스 (체크 후 신규 입력)
    const enabled = !!extra.decryptEnabled;
    return (
      <div className="flex items-center gap-2 w-full h-full px-1">
        <input
          type="password"
          className={baseCls + ' flex-1 border-b ' + (enabled ? 'border-accent/30 focus:border-accent transition-colors' : 'border-border-base bg-surface-2 text-muted cursor-not-allowed')}
          value={enabled ? (extra.passwdInput ?? '') : ''}
          disabled={!enabled}
          placeholder={enabled ? '새 비밀번호 입력' : '체크 후 변경 가능'}
          maxLength={maxLen}
          onChange={e => onChange(alias, e.target.value)}
          autoComplete="new-password"
        />
        <label className="flex items-center gap-1 text-xs text-secondary whitespace-nowrap cursor-pointer flex-shrink-0">
          <input
            type="checkbox"
            className="w-3.5 h-3.5 accent-accent cursor-pointer"
            checked={enabled}
            onChange={() => extra.onDecryptToggle?.()}
          />
          체크후 저장가능
        </label>
      </div>
    );
  }

  // multiselect — 다중선택 (콤마 구분 저장)
  if (ctlName === 'multiselect' || type === 'multiselect') {
    return (
      <MultiselectField
        gubun={gubun}
        field={field}
        val={val}
        readOnly={readOnly}
        onChange={onChange}
        ROCls={ROCls}
        inputCls={inputCls}
      />
    );
  }

  // dropdowntree — 2단계 트리 + 체크박스 다중선택
  if (ctlName === 'dropdowntree' || type === 'dropdowntree') {
    return (
      <DropdownTreeField
        gubun={gubun}
        field={field}
        val={val}
        readOnly={readOnly}
        onChange={onChange}
        ROCls={ROCls}
        inputCls={inputCls}
      />
    );
  }

  if (type === 'dropdownitem' || ctlName === 'dropdownlist' || ctlName === 'dropdownitem') {
    // items(grid_items) 있으면 DropdownItemSelect
    if (items) {
      return (
        <DropdownItemSelect
          gubun={gubun}
          field={field}
          val={val}
          readOnly={readOnly}
          onChange={onChange}
          baseCls={baseCls}
          ROCls={ROCls}
          inputCls={inputCls}
        />
      );
    }
    // prime_key 있으면 동적 조회
    if (field.prime_key) {
      return (
        <DropdownSelect
          gubun={gubun}
          field={field}
          val={val}
          readOnly={readOnly}
          onChange={onChange}
          baseCls={baseCls}
          ROCls={ROCls}
          inputCls={inputCls}
          recordIdx={extra.idx ?? ''}
          formValues={extra.formValues}
        />
      );
    }
    if (readOnly) return <span className={ROCls + ' flex items-center' + flexAlignCls}>{val}</span>;
    return <select className={inputCls + ' appearance-none cursor-pointer'} value={val ?? ''} onChange={e => onChange(alias, e.target.value)}><option value="">-- 선택 --</option></select>;
  }

  // date / datetime — 'date^^MM-dd' 같은 커스텀 포맷도 prefix 로 인식
  {
    const sep = (typeof type === 'string') ? type.indexOf('^^') : -1;
    const baseT = sep > 0 ? type.slice(0, sep) : type;
    if (baseT === 'date' || baseT === 'datetime') {
      const dv = val ? String(val).slice(0, baseT === 'date' ? 10 : 16) : '';
      if (readOnly) {
        // 커스텀 포맷 우선 (예: date^^MM-dd → '04-22')
        if (sep > 0) return <span className={ROCls + ' flex items-center' + flexAlignCls} title={String(val ?? '')}>{formatBySchema(val, type)}</span>;
        return <span className={ROCls + ' flex items-center' + flexAlignCls}>{dv}</span>;
      }
      return (
        <input className={inputCls} type={baseT === 'date' ? 'date' : 'datetime-local'} value={dv}
          onChange={e => onChange(alias, e.target.value)} />
      );
    }
  }

  if (type === 'boolean') {
    const bChecked = val === 1 || val === '1' || val === true;
    if (readOnly) return <span className={ROCls + ' flex items-center' + flexAlignCls}>{bChecked ? '1' : '0'}</span>;
    return (
      <div className="flex items-center h-full px-2">
        <input type="checkbox" className="w-4 h-4 cursor-pointer accent-accent"
          checked={bChecked} onChange={e => onChange(alias, e.target.checked ? '1' : '0')} />
      </div>
    );
  }

  // ctlName='check': schema_type에 따라 체크값 결정
  // boolean → 0/1, 그 외 → Y/N
  if (ctlName === 'check') {
    const isBoolean = type === 'boolean';
    const checked   = isBoolean ? (val === 1 || val === '1' || val === true) : (val === 'Y');
    const onVal     = isBoolean ? '1' : 'Y';
    const offVal    = isBoolean ? '0' : 'N';
    const label     = checked ? onVal : offVal;
    // readOnly 라도 max_length 끝 '!' 인 check 는 confirm 후 즉시 저장 가능 (read_only_cond override)
    const _mlOverrideCheck = String(field.max_length ?? '').endsWith('!');
    if (readOnly && _mlOverrideCheck && extra?.idx) {
      const handleOverrideToggle = async () => {
        const msg = checked ? '체크를 해제하시겠습니까?' : '체크를 하시겠습니까?';
        if (!window.confirm(msg)) return;
        const newVal = checked ? offVal : onVal;
        try {
          const res = await api.save(gubun, { [alias]: newVal, _listEdit: true }, extra.idx);
          if (res?._client_toast) showToast(res._client_toast);
          else if (!res?._client_alert) showToast('저장되었습니다.', 'success');
          if (res?._client_alert) alert(res._client_alert);
          onChange(alias, newVal);
        } catch (e) {
          showToast(e.message || '저장 실패', 'error');
        }
      };
      return (
        <div className="flex items-center h-full px-2">
          <input type="checkbox" className="w-4 h-4 cursor-pointer accent-accent"
            checked={checked} onChange={handleOverrideToggle} />
        </div>
      );
    }
    if (readOnly) {
      // 객체명=check 는 readonly 에서도 0/1/Y/N 텍스트 대신 체크박스(비활성)로 표시
      return (
        <div className={"flex items-center h-full px-2" + flexAlignCls}>
          <input type="checkbox" className="w-4 h-4 cursor-default accent-accent" checked={checked} disabled readOnly />
        </div>
      );
    }

    // virtual_field + default_value='treat:<action>' → 클릭 즉시 treat 호출 (저장 X)
    const dv = String(field.default_value ?? '').trim();
    let treatAction = (field.db_table === 'virtual_field' && dv.startsWith('treat:'))
      ? dv.slice(6).trim() : '';
    // 6083 전용 폴백 — default_value 가 응답에서 누락된 경우에도 강제로 treat 매핑
    if (treatAction === '' && field.db_table === 'virtual_field') {
      const aliasMap = {
        'virtual_fieldQntreat': 'sale_treat',
        'virtual_fieldQnprint_request': 'print_request_toggle',
      };
      if (aliasMap[field.alias_name]) treatAction = aliasMap[field.alias_name];
    }
    if (field.alias_name === 'virtual_fieldQntreat') {
      console.log('[treat-debug] virtual_fieldQntreat field=', { db_table: field.db_table, default_value: field.default_value, treatAction, idx: extra?.idx });
    }

    if (treatAction !== '') {
      const targetIdx = extra?.idx ?? 0;
      const formVals = extra?.formValues ?? {};
      const handleTreatClick = async () => {
        console.log('[treat-debug] handleTreatClick fired. action=', treatAction, 'idx=', targetIdx, 'values=', formVals);
        if (!targetIdx) { showToast('대상 idx 가 없습니다.', 'error'); return; }
        document.body.style.cursor = 'progress';
        try {
          // 현재 폼 값 동봉 — 서버에서 검증/처리에 사용 (예: sale_treat 의 price/qty 등)
          const res = await api.treat(gubun, { action: treatAction, idx: targetIdx, values: formVals });
          const d = res.data ?? {};
          // 서버 confirm 요청: 사용자 확인 후 _confirmed=true 로 재전송
          if (d._client_confirm) {
            if (window.confirm(d._client_confirm)) {
              const res2 = await api.treat(gubun, { action: treatAction, idx: targetIdx, values: formVals, _confirmed: true });
              const d2 = res2.data ?? {};
              if (d2._client_alert) alert(d2._client_alert);
              if (d2._client_toast) showToast(d2._client_toast);
              if (d2.reloadList) window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
              if (d2.reloadView) window.dispatchEvent(new CustomEvent('mis:reloadForm'));
            }
            return;
          }
          if (d._client_alert) alert(d._client_alert);
          if (d._client_toast) showToast(d._client_toast);
          if (d.reloadList) window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
          if (d.reloadView) window.dispatchEvent(new CustomEvent('mis:reloadForm'));
          if (d.success === false && !d._client_alert && !d._client_toast) {
            showToast('실행 실패', 'error');
          }
        } catch (ex) {
          showToast(ex.message || '실행 실패', 'error');
        } finally {
          document.body.style.cursor = '';
        }
      };
      return (
        <div className="flex items-center h-full px-2">
          <input type="checkbox" className="w-4 h-4 cursor-pointer accent-accent"
            checked={checked}
            onChange={handleTreatClick}
            title={`클릭 → ${treatAction} 즉시 실행`} />
        </div>
      );
    }

    return (
      <div className="flex items-center h-full px-2">
        <input type="checkbox" className="w-4 h-4 cursor-pointer accent-accent"
          checked={checked} onChange={e => onChange(alias, e.target.checked ? onVal : offVal)} />
      </div>
    );
  }

  // html 에디터 (웹에디터) — 부모 셀이 할당한 inputPx 를 그대로 사용해 하단 여백 제거
  if (ctlName === 'html') {
    return <HtmlEditor alias={alias} val={val} readOnly={readOnly} onChange={onChange} heightPx={inputPx || hRows * 34} />;
  }

  // zipcode 는 별도 처리 (renderInput 밖에서 처리)
  // → renderInput 에서는 일반 텍스트로 fallback

  const isArea = type === 'content' || ctlName === 'textarea' || hRows > 1 || maxLen > 500;
  if (isArea) {
    // box-border: padding 이 h-full 을 초과하지 않도록 강제 (없으면 아래쪽 1~2px 넘쳐 y-스크롤 발생)
    const areaCls = readOnly
      ? 'block w-full h-full box-border px-2 py-1.5 text-base text-secondary bg-transparent outline-none border-0 cursor-default resize-none'
      : 'block w-full h-full box-border px-2 py-1.5 text-base text-primary bg-transparent outline-none border-0 border-b border-accent/30 focus:border-accent transition-colors resize-none';
    return (
      <textarea className={areaCls} value={val} readOnly={readOnly}
        maxLength={maxLen} onChange={e => onChange(alias, e.target.value)} />
    );
  }

  if (type === 'number' || type?.startsWith('number')) {
    const hasFmt = typeof type === 'string' && type.includes('^^');
    // readOnly + 커스텀 포맷 → 포맷된 텍스트
    if (readOnly && hasFmt) {
      return <span className={ROCls + ' flex items-center justify-end tabular-nums'} title={String(val ?? '')}>{formatBySchema(val, type)}</span>;
    }
    // 편집 가능 + 커스텀 포맷 → 포커스 시 raw, blur 시 포맷 (Excel 스타일)
    if (!readOnly && hasFmt) {
      return <FormattedNumberInput alias={alias} val={val} schemaType={type} maxLen={maxLen} inputCls={inputCls} onChange={onChange} />;
    }
    return (
      <input className={inputCls + ' text-right tabular-nums'} type="text" inputMode="numeric"
        value={val} readOnly={readOnly} maxLength={maxLen} onChange={e => onChange(alias, e.target.value)} />
    );
  }

  // date / datetime 커스텀 포맷 (예: date^^MM-dd) — readOnly 일 때만 포맷 적용 (편집 시엔 표준 input 유지)
  if (readOnly && typeof type === 'string' && type.includes('^^') && (type.startsWith('date') || type.startsWith('datetime'))) {
    return <span className={ROCls + ' flex items-center' + flexAlignCls} title={String(val ?? '')}>{formatBySchema(val, type)}</span>;
  }

  return (
    <input className={inputCls} type="text" value={val}
      readOnly={readOnly} maxLength={maxLen} onChange={e => onChange(alias, e.target.value)} />
  );
}
