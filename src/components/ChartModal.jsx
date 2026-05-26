import React, { useMemo, useState, useEffect } from 'react';
import { createPortal } from 'react-dom';

/**
 * 차트 모달 — aggregate 모드의 부분합 데이터를 4종 차트로 렌더 (외부 라이브러리 없이 SVG)
 *
 * @param chartType 'bar' (세로) | 'hbar' (가로) | 'line' | 'pie'
 * @param data { rows, fields, orderby }
 */
const CHART_LABELS = {
  bar:  '세로막대차트',
  hbar: '가로막대차트',
  line: '선형차트',
  pie:  '원형차트',
};

const PIE_COLORS = ['#4F6EF7', '#00BFA6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#10B981', '#3B82F6', '#F97316', '#A855F7', '#06B6D4', '#84CC16'];

const SORT_LABELS = {
  'label-asc':  '가나다순',
  'label-desc': '가나다역순',
  'value-desc': '높은순',
  'value-asc':  '낮은순',
};

export default function ChartModal({ chartType: initialType, initialGroup, initialValue, initialSort, data, onClose, inline = false, compact = false }) {
  const [chartType, setChartType] = useState(initialType ?? 'bar');
  const [sortBy, setSortBy]       = useState(initialSort ?? 'label-asc');
  const [copied, setCopied]       = useState('');
  const { rows = [], fields = [], orderby = '' } = data || {};

  // X축: orderby 첫 alias (없으면 첫 visible field)
  const sortedFields = useMemo(() => [...fields].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)), [fields]);
  const visibleFields = useMemo(() => sortedFields.filter(f => {
    const w = parseInt(f.col_width ?? '0', 10);
    return w !== 0 && w !== -1 && w !== -2;
  }), [sortedFields]);

  const orderbyTokens = orderby.split(',').filter(Boolean).map(t => t.startsWith('-') ? t.slice(1) : t);
  const defaultGroup  = orderbyTokens[0] || visibleFields[0]?.alias_name || '';
  const numericFields = useMemo(() => visibleFields.filter(f => {
    const t = String(f.schema_type ?? '').toLowerCase();
    return t.startsWith('number') || t === 'int' || t === 'float' || t === 'numeric' || t === 'decimal' || t === 'currency';
  }), [visibleFields]);
  const defaultValue  = numericFields[0]?.alias_name || '';

  const [groupAlias, setGroupAlias] = useState(initialGroup ?? defaultGroup);
  const [valueAlias, setValueAlias] = useState(initialValue !== undefined ? initialValue : defaultValue);
  useEffect(() => { if (!groupAlias && defaultGroup) setGroupAlias(defaultGroup); }, [defaultGroup]);
  useEffect(() => { if (!valueAlias && defaultValue && initialValue === undefined) setValueAlias(defaultValue); }, [defaultValue]);

  // 부분합 행만 추출 (서버 buildAggregateRows 가 __agg_type='subtotal'|'total' 마커 부여)
  // 마커 없으면 모든 행을 group 별 합산 (fallback)
  const chartData = useMemo(() => {
    if (!groupAlias) return [];
    const subtotals = rows.filter(r => r.__agg_type === 'subtotal');
    let source = subtotals.length > 0
      ? subtotals
      : rows.filter(r => r.__agg_type == null); // raw rows (마커 없음)
    // total/grand 행은 차트에서 제외
    // group 별 합계 (aggregate 안 된 raw 데이터 대응)
    const map = new Map();
    for (const r of source) {
      const key = r[groupAlias] ?? '';
      const labelStr = String(key === '' || key == null ? '(없음)' : key);
      // Y=빈값(건수): subtotal 행이면 서버가 채워준 __count 사용, raw 행이면 +1
      let numRaw;
      if (valueAlias) {
        numRaw = r[valueAlias];
      } else if (r.__agg_type === 'subtotal') {
        numRaw = r.__count;
      } else {
        numRaw = 1;
      }
      const num = Number(String(numRaw ?? '').toString().replace(/[,\s]/g, '')) || 0;
      const cur = map.get(labelStr) ?? 0;
      map.set(labelStr, cur + num);
    }
    let arr = Array.from(map.entries()).map(([label, value]) => ({ label, value }));
    // 정렬
    const cmp = {
      'label-asc':  (a, b) => a.label.localeCompare(b.label, 'ko'),
      'label-desc': (a, b) => b.label.localeCompare(a.label, 'ko'),
      'value-desc': (a, b) => b.value - a.value,
      'value-asc':  (a, b) => a.value - b.value,
    }[sortBy] || ((a, b) => 0);
    arr.sort(cmp);
    return arr;
  }, [rows, groupAlias, valueAlias, sortBy]);

  // URL 복사 — 둘 다 isMenuIn=Y 포함
  //   popup=true  : isPopup=Y (브라우저 팝업으로 차트 모달)
  //   popup=false : _chartFull=Y (메인창에서 그리드 자리에 차트만 인라인 표시)
  const buildUrl = (popup) => {
    const cur = new URL(window.location.href);
    cur.searchParams.set('isMenuIn', 'Y');
    cur.searchParams.set('_chart', chartType);
    if (groupAlias) cur.searchParams.set('_chartGroup', groupAlias);
    if (valueAlias) cur.searchParams.set('_chartValue', valueAlias);
    else cur.searchParams.delete('_chartValue');
    cur.searchParams.set('_chartSort', sortBy);
    if (popup) {
      cur.searchParams.set('isPopup', 'Y');
      cur.searchParams.delete('_chartFull');
    } else {
      cur.searchParams.set('_chartFull', 'Y');
      cur.searchParams.delete('isPopup');
    }
    return cur.pathname + '?' + decodeURIComponent(cur.searchParams.toString());
  };
  const copyUrl = (popup) => {
    const url = window.location.origin + buildUrl(popup);
    navigator.clipboard?.writeText(url).then(
      () => { setCopied(popup ? 'popup' : 'main'); setTimeout(() => setCopied(''), 1500); },
      () => {}
    );
  };

  const totalSum = chartData.reduce((s, d) => s + d.value, 0);
  const maxVal   = Math.max(0, ...chartData.map(d => d.value));

  const body = (
    <div
      className={inline
        ? 'h-full w-full flex flex-col bg-surface'
        : 'bg-surface rounded-lg shadow-pop w-[900px] max-w-[95vw] max-h-[90vh] flex flex-col'}
      onClick={inline ? undefined : (e => e.stopPropagation())}
    >
      {!compact && (
        <div className="px-4 py-3 border-b border-border-base flex items-center justify-between flex-shrink-0">
          <h3 className="text-sm font-bold text-primary">📊 차트 — {CHART_LABELS[chartType]}</h3>
          {!inline && (
            <button className="text-secondary hover:text-primary text-xl leading-none cursor-pointer bg-transparent border-0" onClick={onClose}>×</button>
          )}
        </div>
      )}

        {/* 컨트롤 — compact 모드(대시보드 위젯)에서는 숨김 */}
        {!compact && (
        <div className="px-4 py-2 border-b border-border-base flex items-center gap-3 flex-wrap text-xs flex-shrink-0">
          <label className="flex items-center gap-1">
            <span className="text-secondary">차트:</span>
            <select className="h-btn-sm px-2 rounded border border-border-base bg-surface text-primary" value={chartType} onChange={e => setChartType(e.target.value)}>
              {Object.entries(CHART_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </label>
          <span className="flex items-center gap-1">
            <span className="text-secondary">분류축(X):</span>
            <span className="text-primary font-medium">
              {(visibleFields.find(f => f.alias_name === groupAlias)?.col_title ?? groupAlias).replace(/,.*$/, '')}
            </span>
          </span>
          <label className="flex items-center gap-1">
            <span className="text-secondary">수치(Y):</span>
            <select className="h-btn-sm px-2 rounded border border-border-base bg-surface text-primary" value={valueAlias} onChange={e => setValueAlias(e.target.value)}>
              <option value="">(건수)</option>
              {numericFields.map(f => <option key={f.alias_name} value={f.alias_name}>{(f.col_title ?? f.alias_name).replace(/,.*$/, '')}</option>)}
            </select>
          </label>
          <label className="flex items-center gap-1">
            <span className="text-secondary">정렬:</span>
            <select className="h-btn-sm px-2 rounded border border-border-base bg-surface text-primary" value={sortBy} onChange={e => setSortBy(e.target.value)}>
              {Object.entries(SORT_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </label>
          <div className="flex items-center gap-1 ml-2">
            <span className="text-secondary">URL복사:</span>
            <button
              type="button"
              className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2 hover:text-primary"
              onClick={() => copyUrl(true)}
              title="팝업(isPopup=Y) URL 복사"
            >{copied === 'popup' ? '✓ 복사됨' : '팝업'}</button>
            <button
              type="button"
              className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2 hover:text-primary"
              onClick={() => copyUrl(false)}
              title="메인창 URL 복사"
            >{copied === 'main' ? '✓ 복사됨' : '메인창'}</button>
          </div>
          <span className="text-muted ml-auto whitespace-nowrap">데이터 {chartData.length}건 / 합계 {totalSum.toLocaleString()}</span>
        </div>
        )}

      {/* 차트 영역 — compact 모드는 패딩/최소높이 축소 */}
      <div className={compact ? 'flex-1 overflow-hidden p-1' : 'flex-1 overflow-auto p-4'}>
        {chartData.length === 0 ? (
          <div className="text-muted text-xs p-2 text-center">데이터 없음</div>
        ) : (
          <div className={compact ? 'w-full h-full flex items-center justify-center' : 'w-full h-full min-h-[400px] flex items-center justify-center'}>
            {chartType === 'bar'  && <BarChart  data={chartData} maxVal={maxVal} />}
            {chartType === 'hbar' && <HBarChart data={chartData} maxVal={maxVal} />}
            {chartType === 'line' && <LineChart data={chartData} maxVal={maxVal} />}
            {chartType === 'pie'  && <PieChart  data={chartData} totalSum={totalSum} />}
          </div>
        )}
      </div>
    </div>
  );

  if (inline) return body;
  return createPortal(
    <div className="fixed inset-0 z-[200] flex items-center justify-center bg-overlay p-4" onClick={onClose}>
      {body}
    </div>,
    document.body
  );
}

// ── 세로 막대 차트 ──
function BarChart({ data, maxVal }) {
  const [hover, setHover] = useState(null);
  const W = 800, H = 400, P = 40;
  const innerW = W - P * 2;
  const innerH = H - P * 2;
  const barW = innerW / data.length * 0.7;
  const gap  = innerW / data.length * 0.3;
  return (
    <svg viewBox={`0 0 ${W} ${H}`} width="100%" height="100%" onMouseLeave={() => setHover(null)}>
      <line x1={P} y1={H-P} x2={W-P} y2={H-P} stroke="currentColor" className="text-border-base" />
      <line x1={P} y1={P}   x2={P}   y2={H-P} stroke="currentColor" className="text-border-base" />
      {data.map((d, i) => {
        const h = maxVal > 0 ? (d.value / maxVal) * innerH : 0;
        const x = P + (innerW / data.length) * i + gap / 2;
        const y = H - P - h;
        const isHover = hover === i;
        const isDim   = hover !== null && !isHover;
        return (
          <g
            key={i}
            onMouseEnter={() => setHover(i)}
            onClick={() => setHover(prev => prev === i ? null : i)}
            style={{ opacity: isDim ? 0.35 : 1, transition: 'opacity 0.15s', cursor: 'pointer' }}
          >
            <rect
              x={x} y={y} width={barW} height={h}
              fill={PIE_COLORS[i % PIE_COLORS.length]}
              stroke={isHover ? '#1A1D27' : 'none'}
              strokeWidth={isHover ? 2 : 0}
              style={{ filter: isHover ? 'brightness(1.15) drop-shadow(0 0 6px rgba(0,0,0,0.4))' : undefined, transition: 'filter 0.15s' }}
            >
              <title>{d.label}: {d.value.toLocaleString()}</title>
            </rect>
            <text
              x={x + barW/2} y={isHover ? y - 8 : y - 4}
              textAnchor="middle"
              fontSize={isHover ? 13 : 10}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
              style={{ transition: 'font-size 0.15s' }}
            >{d.value.toLocaleString()}</text>
            <text
              x={x + barW/2} y={H - P + 14}
              textAnchor="middle"
              fontSize={isHover ? 11 : 10}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
              style={{ writingMode: data.length > 8 ? 'tb' : undefined }}
            >{d.label.length > 12 ? d.label.slice(0,12)+'…' : d.label}</text>
          </g>
        );
      })}
    </svg>
  );
}

// ── 가로 막대 차트 ──
function HBarChart({ data, maxVal }) {
  const [hover, setHover] = useState(null);
  const W = 800, P = 40;
  const rowH = 30;
  const H = Math.max(300, P * 2 + data.length * rowH);
  const innerW = W - P * 2 - 80;
  return (
    <svg viewBox={`0 0 ${W} ${H}`} width="100%" height="100%" onMouseLeave={() => setHover(null)}>
      {data.map((d, i) => {
        const w = maxVal > 0 ? (d.value / maxVal) * innerW : 0;
        const y = P + i * rowH;
        const x = P + 80;
        const isHover = hover === i;
        const isDim   = hover !== null && !isHover;
        return (
          <g
            key={i}
            onMouseEnter={() => setHover(i)}
            onClick={() => setHover(prev => prev === i ? null : i)}
            style={{ opacity: isDim ? 0.35 : 1, transition: 'opacity 0.15s', cursor: 'pointer' }}
          >
            <text
              x={P + 76} y={y + rowH/2 + 4}
              textAnchor="end"
              fontSize={isHover ? 13 : 11}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
            >{d.label.length > 10 ? d.label.slice(0,10)+'…' : d.label}</text>
            <rect
              x={x} y={y + 4} width={w} height={rowH - 8}
              fill={PIE_COLORS[i % PIE_COLORS.length]}
              stroke={isHover ? '#1A1D27' : 'none'}
              strokeWidth={isHover ? 2 : 0}
              style={{ filter: isHover ? 'brightness(1.15) drop-shadow(0 0 6px rgba(0,0,0,0.4))' : undefined, transition: 'filter 0.15s' }}
            >
              <title>{d.label}: {d.value.toLocaleString()}</title>
            </rect>
            <text
              x={x + w + 6} y={y + rowH/2 + 4}
              fontSize={isHover ? 13 : 10}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
            >{d.value.toLocaleString()}</text>
          </g>
        );
      })}
    </svg>
  );
}

// ── 선형 차트 ──
function LineChart({ data, maxVal }) {
  const [hover, setHover] = useState(null);
  const W = 800, H = 400, P = 40;
  const innerW = W - P * 2;
  const innerH = H - P * 2;
  const stepX = data.length > 1 ? innerW / (data.length - 1) : 0;
  const points = data.map((d, i) => {
    const x = P + stepX * i;
    const h = maxVal > 0 ? (d.value / maxVal) * innerH : 0;
    const y = H - P - h;
    return { x, y, ...d };
  });
  const pathD = points.map((p, i) => (i === 0 ? 'M' : 'L') + p.x + ',' + p.y).join(' ');
  return (
    <svg viewBox={`0 0 ${W} ${H}`} width="100%" height="100%" onMouseLeave={() => setHover(null)}>
      <line x1={P} y1={H-P} x2={W-P} y2={H-P} stroke="currentColor" className="text-border-base" />
      <line x1={P} y1={P}   x2={P}   y2={H-P} stroke="currentColor" className="text-border-base" />
      <path d={pathD} fill="none" stroke="#4F6EF7" strokeWidth="2" />
      {/* hover 시 세로 가이드 라인 */}
      {hover !== null && points[hover] && (
        <line x1={points[hover].x} y1={P} x2={points[hover].x} y2={H-P} stroke="#4F6EF7" strokeWidth="1" strokeDasharray="4 3" opacity="0.5" />
      )}
      {points.map((p, i) => {
        const isHover = hover === i;
        const isDim   = hover !== null && !isHover;
        return (
          <g
            key={i}
            onMouseEnter={() => setHover(i)}
            onClick={() => setHover(prev => prev === i ? null : i)}
            style={{ opacity: isDim ? 0.4 : 1, transition: 'opacity 0.15s', cursor: 'pointer' }}
          >
            {/* 클릭 영역 확장용 투명 원 */}
            <circle cx={p.x} cy={p.y} r="14" fill="transparent" />
            <circle
              cx={p.x} cy={p.y}
              r={isHover ? 8 : 4}
              fill="#4F6EF7"
              stroke={isHover ? '#1A1D27' : 'none'}
              strokeWidth={isHover ? 2 : 0}
              style={{ filter: isHover ? 'drop-shadow(0 0 8px #4F6EF7)' : undefined, transition: 'r 0.15s, filter 0.15s' }}
            >
              <title>{p.label}: {p.value.toLocaleString()}</title>
            </circle>
            <text
              x={p.x} y={isHover ? p.y - 14 : p.y - 8}
              textAnchor="middle"
              fontSize={isHover ? 13 : 10}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
            >{p.value.toLocaleString()}</text>
            <text
              x={p.x} y={H - P + 14}
              textAnchor="middle"
              fontSize={isHover ? 11 : 10}
              fontWeight={isHover ? 'bold' : 'normal'}
              fill="currentColor"
              className={isHover ? 'text-primary' : 'text-secondary'}
            >{p.label.length > 10 ? p.label.slice(0,10)+'…' : p.label}</text>
          </g>
        );
      })}
    </svg>
  );
}

// ── 원형(파이) 차트 ──
function PieChart({ data, totalSum }) {
  const [hover, setHover] = useState(null);
  const cx = 200, cy = 200, r = 180;
  let angle = -Math.PI / 2;
  const slices = data.map((d, i) => {
    const portion = totalSum > 0 ? d.value / totalSum : 0;
    const sweep = portion * Math.PI * 2;
    const x1 = cx + r * Math.cos(angle);
    const y1 = cy + r * Math.sin(angle);
    angle += sweep;
    const x2 = cx + r * Math.cos(angle);
    const y2 = cy + r * Math.sin(angle);
    const largeArc = sweep > Math.PI ? 1 : 0;
    const path = `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${largeArc} 1 ${x2},${y2} Z`;
    const midAngle = angle - sweep / 2;
    const lx = cx + (r * 0.65) * Math.cos(midAngle);
    const ly = cy + (r * 0.65) * Math.sin(midAngle);
    // hover 시 외부로 살짝 튀어나오는 변환 (mid angle 방향으로 12px)
    const popX = Math.cos(midAngle) * 12;
    const popY = Math.sin(midAngle) * 12;
    return { path, color: PIE_COLORS[i % PIE_COLORS.length], lx, ly, popX, popY, label: d.label, value: d.value, pct: portion * 100 };
  });
  return (
    <div className="flex items-center gap-6">
      <svg viewBox="0 0 400 400" width="400" height="400" style={{maxWidth: '50%'}} onMouseLeave={() => setHover(null)}>
        {slices.map((s, i) => {
          const isHover = hover === i;
          const isDim   = hover !== null && !isHover;
          return (
            <g
              key={i}
              onMouseEnter={() => setHover(i)}
              onClick={() => setHover(prev => prev === i ? null : i)}
              style={{
                opacity: isDim ? 0.4 : 1,
                transform: isHover ? `translate(${s.popX}px, ${s.popY}px)` : 'none',
                transformOrigin: '200px 200px',
                transition: 'opacity 0.15s, transform 0.15s',
                cursor: 'pointer',
              }}
            >
              <path
                d={s.path}
                fill={s.color}
                stroke={isHover ? '#1A1D27' : '#fff'}
                strokeWidth={isHover ? 3 : 1}
                style={{ filter: isHover ? 'drop-shadow(0 0 10px rgba(0,0,0,0.4))' : undefined, transition: 'filter 0.15s' }}
              >
                <title>{s.label}: {s.value.toLocaleString()} ({s.pct.toFixed(1)}%)</title>
              </path>
              {s.pct >= 5 && (
                <text x={s.lx} y={s.ly} textAnchor="middle" fontSize={isHover ? 14 : 11} fill="#fff" fontWeight="bold">
                  {s.pct.toFixed(0)}%
                </text>
              )}
            </g>
          );
        })}
      </svg>
      <div className="flex-1 min-w-0 max-h-[400px] overflow-auto">
        {slices.map((s, i) => {
          const isHover = hover === i;
          return (
            <div
              key={i}
              onMouseEnter={() => setHover(i)}
              onMouseLeave={() => setHover(null)}
              onClick={() => setHover(prev => prev === i ? null : i)}
              className={[
                'flex items-center gap-2 py-1 px-1 text-xs cursor-pointer rounded transition-colors',
                isHover ? 'bg-accent-dim' : 'hover:bg-surface-2',
              ].join(' ')}
            >
              <span className="w-3 h-3 rounded flex-shrink-0" style={{ background: s.color, boxShadow: isHover ? '0 0 6px '+s.color : 'none' }}></span>
              <span className={['truncate flex-1', isHover ? 'text-primary font-bold' : 'text-primary'].join(' ')} title={s.label}>{s.label}</span>
              <span className={['tabular-nums', isHover ? 'text-primary font-bold' : 'text-secondary'].join(' ')}>{s.value.toLocaleString()}</span>
              <span className="text-muted tabular-nums w-12 text-right">{s.pct.toFixed(1)}%</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
