import React, { useState, useEffect, useRef, useCallback } from 'react';
import GridLayout from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import api from '../api';

// ── 상수 ───────────────────────────────────────────────────────────────────────
const COLS      = 12;
const ROW_HEIGHT = 58;
const MARGIN    = [6, 6];
const DEFAULT_GRP = '기본폼';

// 브레이크포인트 정의 (DataForm의 BP 상수와 동기화)
const BREAKPOINTS = [
  { key: 'lg', label: '데스크탑', icon: '🖥', minW: 900,  canvasW: null  }, // null = full
  { key: 'md', label: '태블릿',   icon: '💻', minW: 640,  canvasW: 660   },
  { key: 'sm', label: '모바일L',  icon: '📱', minW: 480,  canvasW: 500   },
  { key: 'xs', label: '모바일S',  icon: '📱', minW: 360,  canvasW: 380   },
];

const TYPE_COLOR = {
  text:         'text-secondary',
  number:       'text-link',
  date:         'text-success',
  boolean:      'text-accent',
  dropdownitem: 'text-secondary',
  content:      'text-muted',
};

// ── 유틸 ───────────────────────────────────────────────────────────────────────
function normalizeGroup(g) {
  return (g && g.trim() && g.trim() !== 'Y') ? g.trim() : DEFAULT_GRP;
}

function formLabel(colTitle, aliasName) {
  const s = colTitle ?? aliasName ?? '';
  const ci = s.indexOf(',');
  return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || aliasName;
}

/** sort_order 순서로 자동 배치 */
function buildAutoLayout(groupFields, defaultW = 6) {
  let curX = 0, curY = 0;
  return groupFields.map(f => {
    const w = Math.min(Math.max(defaultW, 1), COLS);
    const h = 1;
    if (curX + w > COLS) { curX = 0; curY++; }
    const item = { i: String(f.idx), x: curX, y: curY, w, h, minW: 1, maxW: COLS, minH: 1 };
    curX += w;
    return item;
  });
}

/** 필드의 브레이크포인트별 레이아웃 초기값 계산 */
function buildInitialLayouts(allFields, groups) {
  // 구조: { groupName: { lg: [...], md: [...], sm: [...], xs: [...] } }
  const result = {};
  groups.forEach(g => {
    const gFlds = allFields.filter(f => normalizeGroup(f.form_group) === g);
    const lgItems = [];
    let hasLg = false;

    gFlds.forEach(f => {
      const hasExplicit = (f.grid_x ?? -1) >= 0;
      if (hasExplicit) hasLg = true;
      lgItems.push({
        i: String(f.idx),
        x: hasExplicit ? f.grid_x : 0,
        y: hasExplicit ? f.grid_y : 0,
        w: Math.max(f.grid_w > 0 ? f.grid_w : 6, 1),
        h: Math.max(f.grid_h > 0 ? f.grid_h : 1, 1),
        minW: 1, maxW: COLS, minH: 1,
      });
    });

    const lgLayout = hasLg ? lgItems : buildAutoLayout(gFlds, 6);

    // sm/xs: form_layout_responsive JSON에서 읽기, 없으면 자동 12컬럼
    const bpLayouts = { lg: lgLayout };
    BREAKPOINTS.filter(b => b.key !== 'lg').forEach(bp => {
      const responsive = gFlds.map(f => {
        const rpJson = f.form_layout_responsive;
        let rpData = null;
        try { rpData = rpJson ? JSON.parse(rpJson) : null; } catch {}
        const bpPos = rpData?.[bp.key];
        return bpPos
          ? { i: String(f.idx), x: bpPos.x, y: bpPos.y, w: bpPos.w, h: bpPos.h, minW: 1, maxW: COLS, minH: 1 }
          : null;
      });
      // 하나라도 있으면 사용, 없으면 자동 (모바일=전폭)
      const hasResponsive = responsive.some(Boolean);
      if (hasResponsive) {
        // null인 항목은 lg에서 가져옴
        bpLayouts[bp.key] = responsive.map((item, i) => item ?? { ...lgLayout[i], w: Math.min(lgLayout[i].w, 12) });
      } else {
        bpLayouts[bp.key] = buildAutoLayout(gFlds, bp.key === 'xs' ? 12 : 6);
      }
    });

    result[g] = bpLayouts;
  });
  return result;
}

// ── 컴포넌트 ──────────────────────────────────────────────────────────────────
export default function FormDesigner({ gubun, onClose }) {
  const [allFields,  setAllFields]  = useState([]);
  const [groups,     setGroups]     = useState([DEFAULT_GRP]);
  const [activeGrp,  setActiveGrp]  = useState(DEFAULT_GRP);
  const [activeBp,   setActiveBp]   = useState('lg');
  // layouts: { groupName: { lg: [...], md: [...], sm: [...], xs: [...] } }
  const [layouts,    setLayouts]    = useState({});
  const [loading,    setLoading]    = useState(true);
  const [saving,     setSaving]     = useState(false);
  const [dirty,      setDirty]      = useState(false);

  // 실제 캔버스 컨테이너 폭 측정 (lg 브레이크포인트용)
  const outerRef   = useRef(null);
  const [outerW, setOuterW] = useState(900);
  useEffect(() => {
    const el = outerRef.current;
    if (!el) return;
    setOuterW(el.offsetWidth || 900);
    const obs = new ResizeObserver(([e]) => setOuterW(e.contentRect.width || 900));
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  // 필드 로드
  useEffect(() => {
    setLoading(true);
    api.list(gubun, { pageSize: 1 }).then(data => {
      const flds = (data.fields ?? []).filter(f =>
        parseInt(f.col_width ?? '0', 10) >= 0 && f.grid_ctl_name !== 'child'
      );
      setAllFields(flds);

      const grpList = [];
      flds.forEach(f => {
        const g = normalizeGroup(f.form_group);
        if (!grpList.includes(g)) grpList.push(g);
      });
      const finalGrps = grpList.length ? grpList : [DEFAULT_GRP];
      setGroups(finalGrps);
      setActiveGrp(finalGrps[0]);
      setLayouts(buildInitialLayouts(flds, finalGrps));
      setLoading(false);
    }).catch(() => setLoading(false));
  }, [gubun]);

  // 현재 그룹+브레이크포인트 레이아웃
  const currentLayout = layouts[activeGrp]?.[activeBp] ?? [];
  const groupFields   = allFields.filter(f => normalizeGroup(f.form_group) === activeGrp);

  // 레이아웃 변경
  const handleLayoutChange = useCallback((newLayout) => {
    setLayouts(prev => ({
      ...prev,
      [activeGrp]: { ...prev[activeGrp], [activeBp]: newLayout },
    }));
    setDirty(true);
  }, [activeGrp, activeBp]);

  // 상위 브레이크포인트에서 현재로 복사
  const handleCopyFromUpper = () => {
    const idx = BREAKPOINTS.findIndex(b => b.key === activeBp);
    if (idx <= 0) return;
    const upperKey = BREAKPOINTS[idx - 1].key;
    const upperLayout = layouts[activeGrp]?.[upperKey] ?? [];
    // xs/sm로 복사 시 너비를 COLS로 늘려서 전폭으로
    const copied = upperLayout.map(item => ({
      ...item,
      w: activeBp === 'xs' ? COLS : item.w,
    }));
    setLayouts(prev => ({
      ...prev,
      [activeGrp]: { ...prev[activeGrp], [activeBp]: copied },
    }));
    setDirty(true);
  };

  // 현재 그룹 자동정렬
  const handleAutoAlign = () => {
    const gFlds = allFields.filter(f => normalizeGroup(f.form_group) === activeGrp);
    const defaultW = activeBp === 'xs' ? 12 : activeBp === 'sm' ? 12 : 6;
    setLayouts(prev => ({
      ...prev,
      [activeGrp]: { ...prev[activeGrp], [activeBp]: buildAutoLayout(gFlds, defaultW) },
    }));
    setDirty(true);
  };

  // 저장
  const handleSave = async () => {
    setSaving(true);
    try {
      // 필드 idx 기준으로 모든 그룹+브레이크포인트 레이아웃 집계
      const itemMap = {}; // idx → { idx, lg, md, sm, xs }
      Object.values(layouts).forEach(bpMap => {
        BREAKPOINTS.forEach(({ key: bp }) => {
          (bpMap[bp] ?? []).forEach(item => {
            const id = item.i;
            if (!itemMap[id]) itemMap[id] = { idx: parseInt(id, 10) };
            itemMap[id][bp] = { x: item.x, y: item.y, w: item.w, h: item.h };
          });
        });
      });
      await api.saveFormLayout(gubun, Object.values(itemMap));
      setDirty(false);
      onClose?.();
    } catch (e) {
      alert(e.message);
    } finally {
      setSaving(false);
    }
  };

  const handleClose = () => {
    if (dirty && !window.confirm('저장하지 않고 닫겠습니까?')) return;
    onClose?.();
  };

  // 현재 브레이크포인트 정보
  const bpInfo    = BREAKPOINTS.find(b => b.key === activeBp);
  const canvasW   = bpInfo.canvasW ?? outerW;
  const bpIdx     = BREAKPOINTS.findIndex(b => b.key === activeBp);
  const upperBp   = bpIdx > 0 ? BREAKPOINTS[bpIdx - 1] : null;

  if (loading) return (
    <div className="flex-1 flex items-center justify-center text-muted text-sm">로딩 중...</div>
  );

  return (
    <div className="flex flex-col h-full overflow-hidden bg-surface">

      {/* ── 헤더 ── */}
      <div className="flex items-center gap-3 px-4 py-2 border-b border-solid border-border-base bg-surface-2 flex-shrink-0">
        <span className="text-sm font-bold text-primary flex-shrink-0">폼 디자이너</span>

        {/* 폼 그룹 탭 */}
        <div className="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto scrollbar-hide">
          {groups.map(g => (
            <button
              key={g} type="button"
              className={[
                'h-btn-sm px-3 text-sm rounded border border-solid cursor-pointer transition-colors flex-shrink-0 whitespace-nowrap',
                activeGrp === g
                  ? 'bg-accent border-accent text-white font-semibold'
                  : 'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
              ].join(' ')}
              onClick={() => setActiveGrp(g)}
            >{g}</button>
          ))}
        </div>

        {/* 우측 액션 */}
        <div className="flex items-center gap-2 flex-shrink-0">
          {upperBp && (
            <button
              type="button"
              title={`${upperBp.label} 레이아웃을 현재 브레이크포인트로 복사`}
              className="h-btn-sm px-3 text-sm rounded border border-solid border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary cursor-pointer transition-colors"
              onClick={handleCopyFromUpper}
            >{upperBp.icon} 복사</button>
          )}
          <button
            type="button"
            className="h-btn-sm px-3 text-sm rounded border border-solid border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary cursor-pointer transition-colors"
            onClick={handleAutoAlign}
          >자동정렬</button>
          <button
            type="button"
            title="col_width / 컨트롤 타입 기준으로 폼 디자인 자동 적용 (보호된 필드 제외)"
            className="h-btn-sm px-3 text-sm rounded border border-solid border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary cursor-pointer transition-colors"
            onClick={async () => {
              if (!confirm('col_width 기준으로 모든 필드의 폼 디자인을 자동 적용합니다 (보호 필드 제외). 진행할까요?')) return;
              try {
                const r = await api.applyAutoDesign({ gubun });
                if (r.success) {
                  alert(r.message || '디자인 적용 완료. 페이지를 새로고침합니다.');
                  window.location.reload();
                } else {
                  alert(r.message || '실패');
                }
              } catch (e) { alert(e.message || '요청 실패'); }
            }}
          >디자인 적용</button>
          <button
            type="button"
            disabled={saving || !dirty}
            className={[
              'h-btn-sm px-4 text-sm rounded border-0 font-semibold cursor-pointer transition-colors flex items-center gap-1.5',
              dirty && !saving ? 'bg-accent text-white hover:bg-accent-hover' : 'bg-surface-2 text-muted cursor-not-allowed',
            ].join(' ')}
            onClick={handleSave}
          >
            {saving && <span className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin inline-block" />}
            {saving ? '저장 중...' : '저장'}
          </button>
          <button
            type="button"
            className="h-btn-sm px-3 text-sm rounded border border-solid border-border-base bg-surface text-secondary hover:bg-surface-2 hover:text-primary cursor-pointer transition-colors"
            onClick={handleClose}
          >✕ 닫기</button>
        </div>
      </div>

      {/* ── 브레이크포인트 선택 바 ── */}
      <div className="flex items-center gap-0 border-b border-solid border-border-base bg-base flex-shrink-0 px-4">
        {BREAKPOINTS.map((bp, i) => (
          <button
            key={bp.key} type="button"
            className={[
              'flex items-center gap-1.5 px-4 py-2 text-xs font-semibold border-b border-solid transition-colors cursor-pointer bg-transparent',
              activeBp === bp.key
                ? 'border-accent text-link'
                : 'border-transparent text-muted hover:text-secondary',
            ].join(' ')}
            onClick={() => setActiveBp(bp.key)}
          >
            <span>{bp.icon}</span>
            <span>{bp.label}</span>
            <span className="text-muted font-normal">
              {bp.canvasW ? `(${bp.minW}px~)` : `(${bp.minW}px+)`}
            </span>
          </button>
        ))}
        <div className="ml-auto text-xs text-muted py-2 pr-1">
          드래그 이동 · 우하단 핸들 크기조정
        </div>
      </div>

      {/* ── 캔버스 ── */}
      <div className="flex-1 overflow-auto bg-base" ref={outerRef}>
        <div className="p-4 flex flex-col items-center">

          {/* 브레이크포인트 폭 시뮬레이션 프레임 */}
          <div
            style={{
              width: bpInfo.canvasW ? `${bpInfo.canvasW}px` : '100%',
              maxWidth: '100%',
              transition: 'width 0.25s ease',
            }}
          >
            {/* 폭 표시 레이블 */}
            <div className="flex items-center justify-between mb-1 text-xs text-muted select-none">
              <span>{bpInfo.icon} {bpInfo.label} — {bpInfo.canvasW ? `${bpInfo.canvasW}px` : '전체 폭'}</span>
              <span>{groupFields.length}개 필드</span>
            </div>

            {/* 컬럼 번호 눈금 */}
            <div
              className="mb-1"
              style={{ display: 'grid', gridTemplateColumns: `repeat(${COLS}, 1fr)`, gap: `${MARGIN[0]}px` }}
            >
              {Array.from({ length: COLS }, (_, i) => (
                <div key={i} className="text-center text-xs text-muted select-none leading-4">{i + 1}</div>
              ))}
            </div>

            {/* 그리드 캔버스 */}
            <div className="relative">
              {/* 배경 격자 */}
              <div
                className="absolute inset-0 pointer-events-none"
                style={{ display: 'grid', gridTemplateColumns: `repeat(${COLS}, 1fr)`, gap: `${MARGIN[0]}px` }}
              >
                {Array.from({ length: COLS }, (_, i) => (
                  <div key={i} className="rounded" style={{ background: 'var(--color-border)', opacity: 0.2, minHeight: '100%' }} />
                ))}
              </div>

              <GridLayout
                layout={currentLayout}
                cols={COLS}
                rowHeight={ROW_HEIGHT}
                width={canvasW}
                margin={MARGIN}
                containerPadding={[0, 0]}
                onLayoutChange={handleLayoutChange}
                draggableHandle=".fd-drag"
                isResizable
                isDraggable
                compactType={null}
                preventCollision
                resizeHandles={['se']}
              >
                {groupFields.map(f => {
                  const label = formLabel(f.col_title, f.alias_name);
                  const type  = f.schema_type ?? 'text';
                  return (
                    <div
                      key={String(f.idx)}
                      className="flex flex-col border border-solid border-border-base rounded bg-surface shadow-sm overflow-hidden"
                    >
                      <div className="fd-drag flex items-center gap-1.5 px-2 py-1 bg-surface-2 border-b border-solid border-border-base cursor-grab active:cursor-grabbing flex-shrink-0 select-none">
                        <svg className="text-muted flex-shrink-0" width="10" height="12" viewBox="0 0 10 12" fill="currentColor">
                          <circle cx="2.5" cy="2"  r="1.1"/>
                          <circle cx="7.5" cy="2"  r="1.1"/>
                          <circle cx="2.5" cy="6"  r="1.1"/>
                          <circle cx="7.5" cy="6"  r="1.1"/>
                          <circle cx="2.5" cy="10" r="1.1"/>
                          <circle cx="7.5" cy="10" r="1.1"/>
                        </svg>
                        <span className="text-xs font-semibold text-secondary truncate flex-1">{label}</span>
                        {f.required === 'Y' && <span className="text-danger text-xs font-bold flex-shrink-0">*</span>}
                      </div>
                      <div className="flex items-center gap-1.5 px-2 py-1 flex-1 min-h-0 overflow-hidden">
                        <span className={`text-xs font-mono flex-shrink-0 ${TYPE_COLOR[type] ?? 'text-muted'}`}>{type}</span>
                        <span className="text-xs text-muted truncate font-mono">{f.alias_name}</span>
                      </div>
                    </div>
                  );
                })}
              </GridLayout>
            </div>

            {/* 브레이크포인트 경계 표시 */}
            {bpInfo.canvasW && (
              <div className="mt-2 text-center text-xs text-muted">
                ← 이 폭 이상에서는 상위 레이아웃({upperBp?.label}) 사용 →
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
