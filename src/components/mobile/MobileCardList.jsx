import React, { useState, useEffect, useCallback, useRef } from 'react';
import api from '../../api';
import { showToast } from '../Toast';

const MAX_FIELDS = 12;

function parseItems(items) {
  try {
    const p = JSON.parse(items ?? '[]');
    if (Array.isArray(p)) return p.map(o => typeof o === 'object' ? o : { value: o, text: o });
  } catch {}
  return (items ?? '').split(',').filter(Boolean).map(v => ({ value: v.trim(), text: v.trim() }));
}

// 뱃지 색상 매핑
const BADGE_COLORS = [
  { bg: '#E6F9F0', color: '#03B26C' },
  { bg: '#E8F1FD', color: '#3182F6' },
  { bg: '#FFF7E6', color: '#F59E0B' },
  { bg: '#FDE8E8', color: '#F04452' },
  { bg: '#F3E8FF', color: '#8B5CF6' },
  { bg: '#E8F5E9', color: '#2E7D32' },
  { bg: '#FFF3E0', color: '#E65100' },
  { bg: '#E0F7FA', color: '#00838F' },
];
function getBadgeColor(value, items) {
  if (!value || !items?.length) return BADGE_COLORS[0];
  const idx = items.findIndex(o => String(o.value) === String(value));
  return BADGE_COLORS[(idx >= 0 ? idx : 0) % BADGE_COLORS.length];
}

function classifyFields(fields) {
  const visible = fields.filter(f => {
    const w = parseInt(f.col_width ?? '0', 10);
    return w > 0 && f.grid_ctl_name !== 'child';
  }).slice(0, MAX_FIELDS);
  if (!visible.length) return { title: null, badge: null, main: [], meta: [] };

  const title = visible[0];
  const rest = visible.slice(1);

  // selectbox 필드 중 첫 번째를 뱃지 후보로 추출
  const badgeIdx = rest.findIndex(f => f.schema_type === 'selectbox' || f.schema_type === 'dropdownlist');
  const badge = badgeIdx >= 0 ? rest[badgeIdx] : null;
  const afterBadge = badge ? rest.filter((_, i) => i !== badgeIdx) : rest;

  const metaKeys = ['wdate','lastupdate','wdater','lastupdater'];
  const meta = afterBadge.filter(f =>
    metaKeys.some(a => f.alias_name?.includes(a)) ||
    f.col_title?.includes('작성') || f.col_title?.includes('수정') || f.col_title?.includes('일자')
  );
  const metaSet = new Set(meta.map(f => f.alias_name));
  return { title, badge, main: afterBadge.filter(f => !metaSet.has(f.alias_name)), meta };
}

function getSpan(f) {
  const w = Math.abs(parseInt(f.col_width ?? '10', 10));
  return w >= 25 ? 'full' : 'half';
}

export default function MobileCardList({ gubun, user, menu, onCardClick, onWrite, onMeta }) {
  const [rows, setRows] = useState([]);
  const [fields, setFields] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const [filterValues, setFilterValues] = useState({});
  const [dynamicItems, setDynamicItems] = useState({});
  const [recently, setRecently] = useState(() => menu?.g03 === 'Y');
  const pageSize = 20;

  const [searchText, setSearchText] = useState('');
  const filterFields = fields.filter(f => ['s','t','w'].includes(f.grid_is_handle ?? ''));
  const selectFilters = filterFields.filter(f => f.grid_is_handle === 's');
  const hasTextFilter = filterFields.some(f => f.grid_is_handle === 't');
  const { title: titleField, badge: badgeField, main: mainFields, meta: metaFields } = classifyFields(fields);

  const pk0cw = parseInt(fields[0]?.col_width ?? '0', 10);
  const pkAlias = fields[0]?.alias_name ?? 'idx';
  const usePk = pk0cw !== -1 && pk0cw !== -2;
  const listF = fields.filter(f => { const w = parseInt(f.col_width ?? '0', 10); return w > 0 && f.grid_ctl_name !== 'child'; });
  const firstA = listF[0]?.alias_name ?? '';
  const getLinkVal = useCallback(r => usePk ? (r[pkAlias] ?? r.idx) : (r[firstA] ?? r[pkAlias] ?? r.idx), [usePk, pkAlias, firstA]);

  // s 필터는 hover/focus 시 lazy-load (실제 데이터 distinct 값)
  // items 는 입력/수정용 선택목록이라 상단 필터와 무관
  const loadedFilterAliases = useRef(new Set());
  useEffect(() => { loadedFilterAliases.current = new Set(); }, [fields, gubun]);
  const loadFilterItems = useCallback((alias) => {
    if (!alias || loadedFilterAliases.current.has(alias)) return;
    loadedFilterAliases.current.add(alias);
    api.filterItems(gubun, alias).then(res => {
      const vals = (res.data ?? []).map(v => typeof v === 'object' ? v : { value: v, text: v });
      setDynamicItems(prev => ({ ...prev, [alias]: vals }));
    }).catch(() => { loadedFilterAliases.current.delete(alias); });
  }, [gubun]);

  const fvRef = useRef(filterValues);
  fvRef.current = filterValues;
  const ffRef = useRef(filterFields);
  ffRef.current = filterFields;
  const recentlyRef = useRef(recently);
  recentlyRef.current = recently;

  const buildAf = useCallback(() => {
    const out = [];
    for (const [field, value] of Object.entries(fvRef.current)) {
      const f = ffRef.current.find(x => x.alias_name === field);
      const handle = f?.grid_is_handle ?? '';
      if (handle === 'w') {
        // 범위(date/number) — 객체 {from,to} 를 [from,to] 배열로
        const from = value && typeof value === 'object' ? String(value.from ?? '') : '';
        const to   = value && typeof value === 'object' ? String(value.to   ?? '') : '';
        if (from === '' && to === '') continue;
        out.push({ field, operator: 'between', value: [from, to] });
      } else {
        if (value === '' || value == null) continue;
        out.push({ field, operator: handle === 's' ? 'eq' : 'contains', value });
      }
    }
    return out.length ? JSON.stringify(out) : '[]';
  }, []);

  const loadRef = useRef(null);
  // 1회용 강제 allFilter (URL init 직후) — 이후엔 null
  const forceAfRef = useRef(null);
  const load = useCallback(async (pg = 1) => {
    if (!gubun) return;
    setLoading(true);
    try {
      const af = forceAfRef.current ?? buildAf();
      forceAfRef.current = null; // 1회만
      const listParams = { page: pg, pageSize, allFilter: af, recently: recentlyRef.current ? 'Y' : 'N' };
      if (window.__mis_custom_action) {
        listParams.customAction = window.__mis_custom_action;
        window.__mis_custom_action = '';
      }
      const data = await api.list(gubun, listParams);
      const nr = data.data ?? [], nf = data.fields ?? [];
      pg === 1 ? (setRows(nr), setFields(nf)) : setRows(prev => [...prev, ...nr]);
      setTotal(data.total ?? 0);
      setPage(pg);
      setHasMore(nr.length >= pageSize);
      if (pg === 1 && onMeta) {
        onMeta({ buttons: data._client_buttons || null, onlyList: !!data._onlyList, buttonText: data._client_buttonText || null });
      }
      if (data._client_alert) alert(data._client_alert);
      if (data._client_toast) showToast(data._client_toast);
    } catch (e) { showToast(e.message || '로드 실패'); }
    finally { setLoading(false); }
  }, [gubun, buildAf]);
  loadRef.current = load;

  useEffect(() => { load(1); }, [gubun]);

  // URL 의 allFilter / orderby / recently 를 모바일 state 로 동기화 — fields 로딩 후 1회.
  // toolbar_ 접두어 제거 + 텍스트 필터(qq_unified_search 등) 는 searchText 까지 채워서 input 에 보이게.
  const urlInitDoneRef = useRef(false);
  useEffect(() => {
    if (urlInitDoneRef.current) return;
    if (!fields || fields.length === 0) return;
    urlInitDoneRef.current = true;
    const p = new URLSearchParams(window.location.search);
    const af = p.get('allFilter');
    let triggered = false;
    if (af) {
      try {
        const arr = JSON.parse(af);
        if (Array.isArray(arr)) {
          const uiVals = {}; // s/t/w 필터 — UI 컨트롤 가짐 → filterValues 영구 저장
          let textVal = '';
          const finalArr = []; // 1회용 forceAf — UI 외 필터 (qq_malls_yn 등) 도 첫 로딩만 적용
          for (const c of arr) {
            const rawField = String(c?.field ?? '');
            let field = rawField;
            if (field.startsWith('toolbar_')) field = field.slice(8);
            if (!field) continue;
            const v = c?.value ?? '';
            const f = fields.find(x => x.alias_name === field);
            const handle = f?.grid_is_handle ?? '';
            const isUi = ['s', 't', 'w'].includes(handle);
            if (isUi) {
              if (handle === 'w') {
                // between 필터 — 배열 ["from","to"] 또는 객체 {from,to} 둘 다 지원 → state 형식 (객체) 으로 통일
                if (Array.isArray(v)) {
                  uiVals[field] = { from: String(v[0] ?? ''), to: String(v[1] ?? '') };
                } else if (v && typeof v === 'object') {
                  uiVals[field] = { from: String(v.from ?? ''), to: String(v.to ?? '') };
                } else {
                  uiVals[field] = { from: String(v ?? ''), to: '' };
                }
              } else {
                uiVals[field] = v;
                if (handle === 't') textVal = String(v ?? '');
              }
            }
            // forceAf 에는 모든 URL 필터 포함 (1회만 적용 후 사라짐). between operator 그대로 보존.
            finalArr.push({
              field,
              operator: c?.operator ?? (handle === 's' ? 'eq' : (handle === 'w' ? 'between' : 'contains')),
              value: v,
            });
          }
          if (Object.keys(uiVals).length > 0) setFilterValues(prev => ({ ...prev, ...uiVals }));
          if (textVal !== '') setSearchText(textVal);
          if (finalArr.length > 0) {
            forceAfRef.current = JSON.stringify(finalArr);
            triggered = true;
          }
        }
      } catch {}
    }
    const rec = p.get('recently');
    if (rec === 'Y' || rec === 'N') {
      const next = rec === 'Y';
      setRecently(next);
      recentlyRef.current = next;
      triggered = true;
    }
    if (triggered) {
      setRows([]); setPage(1);
      loadRef.current?.(1);
    }
  }, [fields]);

  // 사용자가 필터 액션을 하면 URL 의 deep-link 파라미터(allFilter/orderby/recently) 를 제거 — 단순화.
  // (URL 에 남아있으면 새로고침 시 다시 적용돼 혼란.)
  const stripDeepLinkUrl = useCallback(() => {
    const p = new URLSearchParams(window.location.search);
    let changed = false;
    for (const k of ['allFilter', 'orderby', 'recently']) {
      if (p.has(k)) { p.delete(k); changed = true; }
    }
    if (changed) history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
  }, []);

  const doSearch = useCallback(() => { stripDeepLinkUrl(); setRows([]); setPage(1); loadRef.current?.(1); }, [stripDeepLinkUrl]);

  // 통합 검색 (텍스트 필터 첫 번째 필드에 바인딩)
  const textFilterField = filterFields.find(f => f.grid_is_handle === 't');
  const handleSearchSubmit = useCallback(() => {
    if (textFilterField) {
      setFilterValues(prev => ({ ...prev, [textFilterField.alias_name]: searchText }));
    }
    stripDeepLinkUrl();
    requestAnimationFrame(() => { setRows([]); setPage(1); loadRef.current?.(1); });
  }, [searchText, textFilterField, stripDeepLinkUrl]);

  const hasActiveFilter = Object.values(filterValues).some(v => v !== '' && v != null) || searchText !== '';

  return (
    <div className="m-scroll" style={{ height: '100%' }}>
      {/* 통합 검색바 — 텍스트 필터가 있을 때만 표시 */}
      {textFilterField && (
        <div className="m-search-bar">
          <div className="m-search-input-wrap">
            <svg className="m-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input
              className="m-search-input"
              type="text"
              placeholder={`${(() => { const s = textFilterField.col_title ?? ''; const ci = s.indexOf(','); return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci); })()} 검색`}
              value={searchText}
              onChange={e => setSearchText(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleSearchSubmit()}
            />
            {searchText && (
              <button className="m-search-clear" onClick={() => { setSearchText(''); setFilterValues(prev => ({ ...prev, [textFilterField.alias_name]: '' })); requestAnimationFrame(doSearch); }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            )}
            {/* 검색 실행 버튼 — 모바일 키보드 Enter 보조 + 명시적 검색 트리거 */}
            <button
              type="button"
              className="m-search-submit"
              aria-label="검색"
              onClick={handleSearchSubmit}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* 셀렉트 필터 (칩 스타일) */}
      {selectFilters.length > 0 && (
        <div className="m-filter-chips">
          {selectFilters.map(f => {
            const alias = f.alias_name ?? '';
            const label = (() => { const s = f.col_title ?? alias; const ci = s.indexOf(','); return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || alias; })();
            // SQL 쿼리 형태 items 는 필터 폴백에서 제외
            const rawItemsF  = f.items ?? '';
            const isSqlItemsF = /^\s*select\s+/i.test(rawItemsF);
            const staticOptsF = (rawItemsF && !isSqlItemsF) ? parseItems(rawItemsF) : [];
            const baseOpts = dynamicItems[alias] ?? staticOptsF;
            // preset 값이 옵션에 없으면 임시 옵션으로 끼워넣어 표시 유지 + 즉시 preload
            const currentVal = filterValues[alias] ?? '';
            const hasCurrent = currentVal === '' || baseOpts.some(o => String(o.value) === String(currentVal));
            const opts = hasCurrent ? baseOpts : [{ value: currentVal, text: currentVal }, ...baseOpts];
            if (currentVal !== '' && !loadedFilterAliases.current.has(alias)) {
              loadFilterItems(alias);
            }
            return (
              <select key={alias} className="m-filter-chip" value={currentVal}
                onMouseDown={() => loadFilterItems(alias)}
                onFocus={() => loadFilterItems(alias)}
                onChange={e => { setFilterValues(prev => ({ ...prev, [alias]: e.target.value })); stripDeepLinkUrl(); requestAnimationFrame(() => { setRows([]); setPage(1); loadRef.current?.(1); }); }}>
                <option value="">{label}</option>
                {opts.map(o => <option key={o.value} value={o.value}>{o.text ?? o.value}</option>)}
              </select>
            );
          })}
          {/* 범위 필터 (grid_is_handle='w'): 날짜 또는 숫자 범위 — schema_type 으로 자동 분기 */}
          {filterFields.filter(f => f.grid_is_handle === 'w').map(f => {
            const alias = f.alias_name ?? '';
            const rv = filterValues[alias] ?? { from: '', to: '' };
            const label = (() => { const s = f.col_title ?? alias; const ci = s.indexOf(','); return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || alias; })();
            const st = (f.schema_type ?? '').toLowerCase();
            const isNumberRange = st === 'number' || st.startsWith('number');
            const inputType = isNumberRange ? 'text' : 'date';
            const inputModeAttr = isNumberRange ? 'numeric' : undefined;
            const ph = isNumberRange ? '' : '';
            return (
              <div key={alias} className="m-filter-range">
                <span className="m-filter-range-label">{label}</span>
                <input
                  type={inputType}
                  inputMode={inputModeAttr}
                  className="m-filter-chip m-filter-chip--date"
                  placeholder={ph}
                  value={typeof rv === 'object' ? rv.from : ''}
                  onChange={e => setFilterValues(prev => ({ ...prev, [alias]: { ...(typeof prev[alias] === 'object' ? prev[alias] : {}), from: e.target.value } }))}
                  onBlur={doSearch}
                />
                <span className="m-filter-range-tilde">~</span>
                <input
                  type={inputType}
                  inputMode={inputModeAttr}
                  className="m-filter-chip m-filter-chip--date"
                  placeholder={ph}
                  value={typeof rv === 'object' ? rv.to : ''}
                  onChange={e => setFilterValues(prev => ({ ...prev, [alias]: { ...(typeof prev[alias] === 'object' ? prev[alias] : {}), to: e.target.value } }))}
                  onBlur={doSearch}
                />
              </div>
            );
          })}
        </div>
      )}

      {/* 건수 + 최근순 */}
      <div className="m-list-header">
        <span className="m-list-count">{loading && page === 1 ? '로딩 중...' : `총 ${total.toLocaleString()}건`}</span>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
          <button className={`m-list-recently ${recently ? 'm-list-recently--on' : ''}`}
            onClick={() => { const next = !recently; setRecently(next); recentlyRef.current = next; stripDeepLinkUrl(); setRows([]); setPage(1); requestAnimationFrame(() => loadRef.current?.(1)); }}>
            최근순
          </button>
          {hasActiveFilter && (
            <button className="m-list-reset" onClick={() => {
              // URL 에 남아있던 deep-link 필터 (allFilter / orderby / recently) 도 함께 청소
              const p = new URLSearchParams(window.location.search);
              p.delete('allFilter'); p.delete('orderby'); p.delete('recently');
              history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
              setFilterValues({});
              setSearchText('');
              forceAfRef.current = null;
              requestAnimationFrame(doSearch);
            }}>초기화</button>
          )}
        </div>
      </div>

      {/* 카드 리스트 */}
      {loading && page === 1 ? (
        <div style={{ padding: '0 16px' }}>
          {Array.from({ length: 5 }, (_, i) => (
            <div key={i} className="m-card" style={{ cursor: 'default' }}>
              <div className="m-skeleton" style={{ height: 18, width: '65%', marginBottom: 10 }} />
              <div style={{ display: 'flex', gap: 12 }}>
                <div className="m-skeleton" style={{ height: 14, width: '40%' }} />
                <div className="m-skeleton" style={{ height: 14, width: '30%' }} />
              </div>
              <div className="m-skeleton" style={{ height: 12, width: '50%', marginTop: 10 }} />
            </div>
          ))}
        </div>
      ) : rows.length === 0 ? (
        <div className="m-empty">
          <svg className="m-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.2">
            <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
          </svg>
          <span className="m-empty-text">데이터가 없습니다</span>
        </div>
      ) : (
        <>
          {rows.map((row, ri) => {
            const pk = usePk ? (row[pkAlias] ?? row.idx) : getLinkVal(row);
            const lv = getLinkVal(row);
            const tv = titleField ? (row[titleField.alias_name] ?? '') : (row.idx ?? ri);
            const ht = row.__html?.[titleField?.alias_name];

            // 뱃지 값
            const badgeVal = badgeField ? (row[badgeField.alias_name] ?? '') : '';
            const badgeItems = badgeField ? ((badgeField.items ? parseItems(badgeField.items) : null) ?? []) : [];
            const badgeText = badgeItems.find(o => String(o.value) === String(badgeVal))?.text ?? badgeVal;
            const badgeStyle = badgeVal ? getBadgeColor(badgeVal, badgeItems) : null;

            return (
              <div key={row.idx ?? ri} className="m-card" onClick={() => onCardClick(pk, lv)}>
                {/* 제목 + 뱃지 */}
                <div className="m-card-head">
                  <div style={{ flex: 1, minWidth: 0 }}>
                    {ht ? <span className="m-card-title" dangerouslySetInnerHTML={{ __html: ht }} />
                        : <div className="m-card-title">{tv || '-'}</div>}
                  </div>
                  {badgeStyle && badgeText && (
                    <span className="m-card-status-badge" style={{ background: badgeStyle.bg, color: badgeStyle.color }}>{badgeText}</span>
                  )}
                </div>

                {/* 필드 — 간결한 텍스트 */}
                {mainFields.length > 0 && (
                  <div className="m-card-body">
                    {mainFields.slice(0, 4).map(f => {
                      const val = row[f.alias_name] ?? '';
                      const html = row.__html?.[f.alias_name];
                      const label = (() => { const s = f.col_title ?? ''; const ci = s.indexOf(','); return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci); })();
                      return (
                        <div key={f.alias_name} className={`m-card-field ${getSpan(f) === 'full' ? 'm-card-field--full' : ''}`}>
                          <span className="m-card-field-label">{label}</span>
                          {html ? <span className="m-card-field-value cell-html" dangerouslySetInnerHTML={{ __html: html }} />
                                : <span className="m-card-field-value">{val || '-'}</span>}
                        </div>
                      );
                    })}
                  </div>
                )}

                {/* 메타 */}
                {metaFields.length > 0 && (
                  <div className="m-card-meta">
                    {metaFields.map(f => {
                      let val = row[f.alias_name] ?? '';
                      if (val.length > 10 && (f.schema_type === 'datetime' || f.alias_name?.includes('date'))) val = val.slice(0, 10);
                      return <span key={f.alias_name}>{f.col_title} {val || '-'}</span>;
                    })}
                  </div>
                )}
              </div>
            );
          })}

          {hasMore && !loading && (
            <button className="m-load-more" onClick={() => !loading && loadRef.current?.(page + 1)}>
              더보기 ({rows.length} / {total.toLocaleString()})
            </button>
          )}
          {loading && page > 1 && <div style={{ textAlign: 'center', padding: 16, color: 'var(--m-text-3)', fontSize: 14 }}>로딩 중...</div>}
        </>
      )}
    </div>
  );
}
