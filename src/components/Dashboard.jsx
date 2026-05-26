import React, { useEffect, useState, useMemo, useRef, lazy, Suspense } from 'react';
import { createPortal } from 'react-dom';
import api from '../api';
import { showToast } from './Toast';

const ChartModal = lazy(() => import('./ChartModal'));

/**
 * 대시보드 — mis_menus.is_main='1' 인 메뉴들을 카드로 노출.
 * - 항목 관리는 631번 프로그램에서 (brief_title / is_main / w2 / h2 / is_not_recently / add_url)
 * - 각 카드: 헤더(프로그램명) + list 미리보기 (5건, h2='1' 이면 10건)
 * - 폭: w2='1' → 100%, 기본 50%
 * - 행 클릭 → 해당 프로그램 상세 (현재창 modify), 헤더 클릭 → 해당 프로그램으로 이동
 * - 권한 없는 위젯은 서버에서 자동 제외
 */
export default function Dashboard({ user, onOpenTab, menu = null }) {
  const [widgets, setWidgets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [orderModal, setOrderModal] = useState(false);

  // 메뉴의 add_url 에서 refPid 추출 (예: "&refPid=speedmis000631") — 위젯 소스 프로그램
  const refPid = useMemo(() => {
    const url = menu?.add_url ?? '';
    if (!url) return '';
    const u = new URLSearchParams(url.startsWith('&') ? url.slice(1) : url);
    return u.get('refPid') ?? '';
  }, [menu?.add_url]);

  const reload = () => {
    setLoading(true);
    api.dashboardConfig(refPid).then(d => {
      setWidgets(d.data ?? []);
      setLoading(false);
    }).catch(() => setLoading(false));
  };

  useEffect(() => {
    let cancelled = false;
    api.dashboardConfig(refPid).then(d => {
      if (cancelled) return;
      setWidgets(d.data ?? []);
      setLoading(false);
    }).catch(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [refPid]);

  // 정렬하기 버튼 — 항상 노출 (저장은 서버가 admin 검증; 비-admin은 403)
  const sortButton = (
    <div className="flex justify-end mb-2">
      <button
        type="button"
        className="h-btn-sm px-3 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2 hover:text-primary transition-colors flex items-center gap-1"
        onClick={() => {
          if (widgets.length === 0) {
            showToast('정렬할 항목이 없습니다. 먼저 631 메뉴에서 항목을 추가하세요.');
            return;
          }
          setOrderModal(true);
        }}
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        정렬하기
      </button>
    </div>
  );

  if (loading) return <div className="p-4 text-muted text-sm">대시보드 로딩 중...</div>;
  if (widgets.length === 0) {
    return (
      <div className="h-full overflow-auto p-3 bg-base relative">
        {sortButton}
        <div className="p-4 text-muted text-sm">
          표시할 대시보드 항목이 없습니다.
          <button
            className="ml-2 text-link underline"
            onClick={() => onOpenTab?.({ gubun: 631, label: '대시보드 항목 관리' })}
          >항목 관리(631)로 이동</button>
        </div>
      </div>
    );
  }

  // 좌측 / 우측 분리
  const leftWidgets  = widgets.filter(w => w.pos !== 'R');
  const rightWidgets = widgets.filter(w => w.pos === 'R');

  return (
    <div className="h-full overflow-auto p-3 bg-base relative">
      {sortButton}

      {/* 1200(xl) 이상: 좌측 grid + 우측 300px 사이드 / 미만: 1열 통합 */}
      <div className="flex gap-3">
        {/* 좌측 — 카드 그리드 */}
        <div className="flex-1 min-w-0">
          <div className="grid grid-cols-12 gap-3 auto-rows-[224px]">
            {leftWidgets.map(w => (
              <DashboardCard key={w.gubun} widget={w} user={user} onOpenTab={onOpenTab} />
            ))}
          </div>
        </div>
        {/* 우측 — 300px 사이드 (xl 이상에서만 노출) */}
        {rightWidgets.length > 0 && (
          <div className="hidden xl:block w-[300px] flex-shrink-0">
            <div className="flex flex-col gap-3">
              {rightWidgets.map(w => (
                <DashboardCard key={w.gubun} widget={{ ...w, w2: false }} user={user} onOpenTab={onOpenTab} sideMode={true} />
              ))}
            </div>
          </div>
        )}
        {/* xl 미만 + 우측 위젯 있음 → 좌측 그리드에 합쳐서 전체폭으로 표시 */}
      </div>
      {/* xl 미만에서는 우측 위젯도 좌측 grid 에 합류 */}
      {rightWidgets.length > 0 && (
        <div className="xl:hidden mt-3">
          <div className="grid grid-cols-12 gap-3 auto-rows-[224px]">
            {rightWidgets.map(w => (
              <DashboardCard key={'r-'+w.gubun} widget={w} user={user} onOpenTab={onOpenTab} />
            ))}
          </div>
        </div>
      )}

      {orderModal && (
        <OrderModal
          widgets={widgets}
          onClose={() => setOrderModal(false)}
          onSaved={() => { setOrderModal(false); reload(); }}
        />
      )}
    </div>
  );
}

/** admin 전용 정렬 모달 — 좌/우 영역 + 마우스 드래그로 영역 간 이동 + 순서 변경 */
function OrderModal({ widgets, onClose, onSaved }) {
  // 초기 상태: pos 별로 분리
  const init = (() => ({
    L: widgets.filter(w => w.pos !== 'R'),
    R: widgets.filter(w => w.pos === 'R'),
  }));
  const [cols, setCols] = useState(init);
  const dragInfo = useRef(null); // { from: 'L'|'R', idx }

  const onDragStart = (zone, idx) => (e) => {
    dragInfo.current = { zone, idx };
    e.dataTransfer.effectAllowed = 'move';
  };
  const onDragOver = (e) => { e.preventDefault(); };
  const onDrop = (toZone, toIdx) => (e) => {
    e.preventDefault();
    const info = dragInfo.current;
    if (!info) return;
    const { zone: fromZone, idx: fromIdx } = info;
    setCols(prev => {
      const next = { L: [...prev.L], R: [...prev.R] };
      const [item] = next[fromZone].splice(fromIdx, 1);
      // 같은 zone 내 이동 시 인덱스 보정
      const insertAt = (fromZone === toZone && toIdx > fromIdx) ? toIdx - 1 : toIdx;
      next[toZone].splice(insertAt, 0, item);
      return next;
    });
    dragInfo.current = null;
  };
  const onDropToZoneEnd = (toZone) => (e) => {
    e.preventDefault();
    const info = dragInfo.current;
    if (!info) return;
    setCols(prev => {
      const next = { L: [...prev.L], R: [...prev.R] };
      const [item] = next[info.zone].splice(info.idx, 1);
      next[toZone].push(item);
      return next;
    });
    dragInfo.current = null;
  };

  const handleSave = () => {
    const items = [];
    cols.L.forEach(w => items.push({ real_pid: w.real_pid, pos: 'L' }));
    cols.R.forEach(w => items.push({ real_pid: w.real_pid, pos: 'R' }));
    api.dashboardSaveOrder(items).then(d => {
      if (d.success) { showToast('저장되었습니다'); onSaved(); }
      else showToast('실패: ' + (d.message ?? ''));
    });
  };

  const renderZone = (zoneKey, title) => (
    <div
      className="flex-1 min-w-0 flex flex-col bg-surface-2 rounded border border-border-base"
      onDragOver={onDragOver}
      onDrop={onDropToZoneEnd(zoneKey)}
    >
      <div className="px-3 py-2 border-b border-border-base text-xs font-bold text-primary flex-shrink-0">
        {title} <span className="text-muted font-normal">({cols[zoneKey].length})</span>
      </div>
      <div className="flex-1 overflow-auto p-2 min-h-[200px]">
        {cols[zoneKey].length === 0 && (
          <div className="text-xs text-muted text-center py-8">여기로 드래그</div>
        )}
        {cols[zoneKey].map((w, i) => (
          <div
            key={w.gubun}
            draggable
            onDragStart={onDragStart(zoneKey, i)}
            onDragOver={onDragOver}
            onDrop={onDrop(zoneKey, i)}
            className="flex items-center gap-2 px-3 py-2 mb-1.5 rounded border border-border-base bg-surface cursor-move hover:bg-accent-dim hover:border-accent transition-colors"
          >
            <span className="text-muted text-xs w-5">{i + 1}</span>
            <span className="text-secondary text-xs">⋮⋮</span>
            <span className="text-sm font-medium text-primary truncate flex-1">{w.label || w.menu_name}</span>
            <span className="text-[10px] text-muted">gubun={w.gubun}</span>
          </div>
        ))}
      </div>
    </div>
  );

  return createPortal(
    <div className="fixed inset-0 z-[200] flex items-center justify-center bg-overlay p-4" onClick={onClose}>
      <div className="bg-surface rounded-lg shadow-pop w-[820px] max-w-[95vw] max-h-[85vh] flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-4 py-3 border-b border-border-base flex items-center justify-between flex-shrink-0">
          <h3 className="text-sm font-bold text-primary">대시보드 정렬</h3>
          <button className="text-secondary hover:text-primary text-xl leading-none cursor-pointer bg-transparent border-0" onClick={onClose}>×</button>
        </div>
        <div className="px-4 pt-3 text-xs text-muted">항목을 드래그해서 좌/우 영역과 순서를 바꾸세요. (우측은 1200px 이상에서만 사이드 표시)</div>
        <div className="flex-1 overflow-hidden p-3 flex gap-3">
          {renderZone('L', '좌측 — 메인 그리드')}
          {renderZone('R', '우측 — 사이드(300px)')}
        </div>
        <div className="px-4 py-3 border-t border-border-base flex items-center justify-end gap-2 flex-shrink-0">
          <button className="h-btn-sm px-4 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2" onClick={onClose}>취소</button>
          <button className="h-btn-sm px-4 rounded bg-accent text-white text-xs font-semibold cursor-pointer hover:bg-accent-hover" onClick={handleSave}>저장</button>
        </div>
      </div>
    </div>,
    document.body
  );
}

function DashboardCard({ widget, user, onOpenTab, sideMode = false }) {
  const { gubun, real_pid, menu_name, label, add_url, is_not_recently, w2, h2, no_link } = widget;
  const pageSize = h2 ? 10 : 5;
  // 사이드(우측 300px) 모드: grid 가 아니라 flex 안. width 100% + 고정 높이.
  // 일반(좌측 grid) 모드:
  //   <768px : 모두 12col (1열)
  //   768~1199 : w2='Y' → 12col(1열), 그 외 → 6col(2열)
  //   ≥1200(xl) : w2='Y' → 6col(2열, 50%), 그 외 → 3col(4열, 25%)
  const colSpan = sideMode
    ? ''
    : (w2 ? 'col-span-12 xl:col-span-6' : 'col-span-12 md:col-span-6 xl:col-span-3');
  const rowSpan = sideMode ? '' : (h2 ? 'row-span-2' : 'row-span-1');
  const sideStyle = sideMode ? { height: h2 ? 448 : 224 } : undefined;

  const [rows, setRows] = useState([]);
  const [fields, setFields] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  // add_url 의 추가 파라미터 파싱 (memo: render에서도 사용)
  const addParams = useMemo(() => {
    const p = {};
    if (add_url) {
      const u = new URLSearchParams(add_url.startsWith('&') ? add_url.slice(1) : add_url);
      for (const [k, v] of u) p[k] = v;
    }
    return p;
  }, [add_url]);
  // 차트 모드 / 부분합 모드 감지
  const chartMode = !!addParams._chart;
  const aggMode   = !!addParams.aggregate;
  // 차트/부분합이면 모든 그룹을 받기 위해 pageSize 상한 해제
  const effectivePageSize = (chartMode || aggMode) ? 99999 : pageSize;

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    api.list(gubun, {
      page: 1,
      pageSize: effectivePageSize,
      recently: is_not_recently ? 'N' : 'Y',
      ...addParams,
    }).then(d => {
      if (cancelled) return;
      if (d.success === false) {
        setError(d.message || '조회 실패');
      } else {
        setRows(d.data ?? []);
        setFields(d.fields ?? []);
      }
      setLoading(false);
    }).catch(e => { if (!cancelled) { setError(String(e)); setLoading(false); } });
    return () => { cancelled = true; };
  }, [gubun, effectivePageSize, is_not_recently, add_url]);

  // 표시 컬럼: col_width > 0 이고 idx/wdater 류 시스템 컬럼 제외, 최대 5개
  const visible = useMemo(() => {
    const sysAlias = new Set(['idx','wdate','wdater','lastupdate','lastupdater','use_yn','useflag','useflag','remark']);
    return fields
      .filter(f => {
        const w = parseInt(f.col_width ?? '0', 10);
        if (w <= 0) return false;
        return !sysAlias.has(f.alias_name);
      })
      .slice(0, 5);
  }, [fields]);

  const openProgram = () => {
    if (no_link) return;
    const detail = { gubun, label: label || menu_name };
    if (add_url) detail.addUrl = add_url;
    window.dispatchEvent(new CustomEvent('mis:redirectTab', { detail }));
  };

  const openRow = (row) => {
    // row 클릭 → 해당 프로그램으로 이동하면서 idx 전달
    const pkAlias = fields[0]?.alias_name ?? 'idx';
    const pkVal = row[pkAlias] ?? row.idx;
    if (pkVal == null) return;
    window.dispatchEvent(new CustomEvent('mis:redirectTab', {
      detail: { gubun, label: label || menu_name, idx: pkVal, linkVal: pkVal, addUrl: add_url }
    }));
  };

  return (
    <div
      className={[colSpan, rowSpan, 'bg-surface border border-border-base rounded flex flex-col overflow-hidden'].join(' ')}
      style={sideStyle}
    >
      {/* 헤더 — 프로그램명 클릭 시 해당 프로그램 이동 */}
      <button
        type="button"
        className={[
          'px-3 py-2 border-b border-border-base bg-surface-2 text-primary text-left flex items-center justify-between flex-shrink-0 border-0',
          no_link ? 'cursor-default' : 'cursor-pointer hover:bg-accent-dim hover:text-accent',
        ].join(' ')}
        onClick={openProgram}
        title={no_link ? '바로가기 거부' : `${menu_name} 으로 이동`}
      >
        <span className="text-sm font-semibold truncate">{label || menu_name}</span>
        {!no_link && <span className="text-xs text-muted">↗</span>}
      </button>

      {/* 본문 — _chart= 가 있으면 차트, 없으면 미리보기 리스트 */}
      <div className="flex-1 overflow-hidden flex flex-col min-h-0">
        {loading ? (
          <div className="p-3 text-xs text-muted">로딩 중...</div>
        ) : error ? (
          <div className="p-3 text-xs text-danger">{error}</div>
        ) : rows.length === 0 ? (
          <div className="p-3 text-xs text-muted">데이터 없음</div>
        ) : chartMode ? (
          <Suspense fallback={<div className="p-3 text-xs text-muted">차트 로딩 중...</div>}>
            <ChartModal
              inline
              compact
              chartType={addParams._chart}
              initialGroup={addParams._chartGroup}
              initialValue={addParams._chartValue !== undefined ? addParams._chartValue : undefined}
              initialSort={addParams._chartSort}
              data={{ rows, fields, orderby: addParams.orderby ?? '' }}
              onClose={() => {}}
            />
          </Suspense>
        ) : (
          <div className="flex-1 overflow-auto">
          <table className="w-full text-xs">
            <thead className="bg-surface-2 sticky top-0">
              <tr>
                {visible.map(f => (
                  <th key={f.alias_name} className="px-2 py-1.5 text-left font-bold text-muted uppercase border-b border-border-base whitespace-nowrap">
                    {(f.col_title ?? '').replace(/,.*$/, '')}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.idx ?? i}
                  className="cursor-pointer hover:bg-accent-dim border-b border-border-light"
                  onClick={() => openRow(r)}
                >
                  {visible.map(f => {
                    const val = r[f.alias_name];
                    const text = val == null || val === '' ? '-' : String(val);
                    return (
                      <td key={f.alias_name} className="px-2 py-1.5 text-primary truncate max-w-[200px]" title={text}>
                        {text}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        )}
      </div>
    </div>
  );
}
