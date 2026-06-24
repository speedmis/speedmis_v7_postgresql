import React, { useState, useEffect, useCallback, useRef } from 'react';
import api, { progModeFlags } from '../api';
import { showToast } from './Toast';
import DataGrid from './DataGrid';
import DataForm from './DataForm';

function copyText(text) {
  if (navigator.clipboard?.writeText) navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
  else legacyCopy(text);
}
function legacyCopy(text) {
  const el = document.createElement('textarea');
  el.value = text; el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
  document.body.appendChild(el); el.select();
  try { document.execCommand('copy'); } catch {}
  document.body.removeChild(el);
}
function formatSaveSQL(sql) {
  if (!sql) return sql;
  if (sql.includes('\n')) return sql.trim();
  let s = sql.replace(/\s+/g, ' ').trim();
  return s.replace(/\bSET\b/gi, '\nSET ').replace(/\bVALUES\b/gi, '\nVALUES').replace(/\bWHERE\b/gi, '\nWHERE').replace(/,\s*/g, ',\n    ').trim();
}
function buildCompleteSaveSQL(sql, bindings) {
  if (!bindings?.length) return sql;
  let i = 0;
  return sql.replace(/\?/g, () => {
    const v = bindings[i++];
    if (v === null || v === undefined) return 'NULL';
    if (typeof v === 'number') return String(v);
    return `'${String(v).replace(/'/g, "''")}'`;
  });
}

/**
 * 마스터-디테일 자식 프로그램 패널
 * - iframe 없이 React 컴포넌트로 직접 렌더
 * - parentIdx prop 변경 시 DataGrid만 재조회 (전체 재로드 없음)
 * - 내용보기는 항상 전체화면(100%), 자동 첫행 열기 없음
 */
export default function ChildProgram({ childGubun, parentIdx, parentGubun, user, devMode = false }) {
  const [menu,    setMenu]    = useState(null);
  const [loading, setLoading] = useState(true);

  const [sqlVisible,  setSqlVisible]  = useState(false);
  const [sqlHasError, setSqlHasError] = useState(false);
  const sqlOpenRef = React.useRef(null);
  const handleSqlBtn = useCallback((visible, openFn, hasError) => {
    setSqlVisible(visible);
    setSqlHasError(!!hasError);
    if (openFn) sqlOpenRef.current = openFn;
  }, []);

  const [saveSqlData, setSaveSqlData] = useState(null);
  const [saveSqlOpen, setSaveSqlOpen] = useState(false);
  const handleSaveSql = useCallback((sqlData) => { setSaveSqlData(sqlData); }, []);

  // 자식 패널 상태 (panelSize는 항상 4 = 전체화면)
  const [panelOpen,      setPanelOpen]      = useState(false);
  const [currentIdx,     setCurrentIdx]     = useState(0);
  const [currentLinkVal, setCurrentLinkVal] = useState(null);
  const [panelMode,      setPanelMode]      = useState('view');
  const [gridReloadKey,  setGridReloadKey]  = useState(0);
  const [briefPopup,     setBriefPopup]     = useState(null); // { minIdx, count }
  // 자식 프로그램이 pageLoad 에서 $GLOBALS['_onlyList']=true 설정 시: +등록/간편추가 숨김 + 수정폼 진입 차단
  const [onlyList,       setOnlyList]       = useState(false);
  const gridRef = useRef(null);
  const briefGridRef = useRef(null);

  // 폼 탭 상태
  const [formTabs,      setFormTabs]      = useState(['기본폼']);
  const [formActiveTab, setFormActiveTab] = useState('기본폼');

  // 서버 훅에서 주입되는 사용자 정의 버튼 ($GLOBALS['_client_buttons'])
  const [customButtons, setCustomButtons] = useState([]);
  const handleClientMeta = useCallback((meta) => {
    if (meta?.buttons) setCustomButtons(meta.buttons);
  }, []);

  useEffect(() => {
    if (!childGubun) return;
    setLoading(true);
    api.menuItem(childGubun)
      .then(d => { setMenu(d.data); setLoading(false); })
      .catch(() => setLoading(false));
  }, [childGubun]);

  // 탭 초기화: 모드 변경 시
  useEffect(() => {
    setFormTabs(['기본폼']);
    setFormActiveTab('기본폼');
  }, [panelMode]);

  const handleToggleView = useCallback((pk, linkVal) => {
    if (panelOpen && currentIdx === pk) {
      setPanelOpen(false);
    } else {
      setCurrentIdx(pk);
      setCurrentLinkVal(linkVal ?? pk);
      setPanelMode('view');
      setPanelOpen(true);
    }
  }, [panelOpen, currentIdx]);

  const openModify = useCallback(idx => {
    setCurrentIdx(idx);
    setPanelMode('modify');
    setPanelOpen(true);
  }, []);

  const openWrite = useCallback(() => {
    setCurrentIdx(0);
    setPanelMode('write');
    setPanelOpen(true);
  }, []);

  const handleSaved = useCallback(() => {
    setPanelOpen(false);
    setGridReloadKey(k => k + 1);
  }, []);

  const handleCancel = useCallback(() => setPanelOpen(false), []);

  const handleFormModify = useCallback(() => setPanelMode('modify'), []);

  const handleDeleted = useCallback(() => {
    setPanelOpen(false);
    setGridReloadKey(k => k + 1);
  }, []);

  if (loading) return (
    <div className="flex-1 flex items-center justify-center text-muted text-sm">로딩 중...</div>
  );
  if (!menu) return (
    <div className="flex-1 flex items-center justify-center text-muted text-sm">메뉴 정보를 찾을 수 없습니다.</div>
  );

  // 내용보기가 열리면 그리드는 숨김 (항상 전체화면)
  const showGrid   = !panelOpen;
  const showDetail = panelOpen;
  // g01 프로그램 모드 플래그 (자식 그리드도 동일 정책 적용)
  const pm = progModeFlags(menu?.g01, true);

  return (
    <div className="flex flex-col h-full overflow-hidden bg-surface">
      {/* 헤더 */}
      <div className="flex items-center justify-between px-3 py-1.5 border-b border-solid border-border-base flex-shrink-0">
        <span className="text-sm font-bold text-primary">{menu.menu_name}</span>
        <div className="flex items-center gap-1.5">
          {(devMode || sqlHasError) && sqlVisible && (
            <button
              className={`h-btn-sm px-2 rounded border text-xs cursor-pointer transition-colors ${sqlHasError ? 'border-danger bg-danger-dim text-danger hover:opacity-80' : 'border-border-base bg-surface text-link hover:bg-surface-2'}`}
              onClick={() => sqlOpenRef.current?.()}
            >SQL</button>
          )}
          {saveSqlData && (
            <button
              className="h-btn-sm px-2 rounded border border-border-base bg-surface text-link text-xs cursor-pointer hover:bg-surface-2 transition-colors"
              onClick={() => setSaveSqlOpen(true)}
            >저장쿼리</button>
          )}
          {devMode && menu?.menu_type === '01' && menu?.real_pid && (
            <button
              className="h-btn-sm px-2 rounded border border-border-base bg-surface text-link text-xs cursor-pointer hover:bg-surface-2 transition-colors"
              onClick={() => {
                window.dispatchEvent(new CustomEvent('mis:openTab', {
                  detail: { gubun: 266, label: `웹소스 (${menu.real_pid})`, idx: menu.real_pid, linkVal: menu.real_pid, openFull: true }
                }));
              }}
            >웹소스</button>
          )}
          {/* 서버 주입 사용자 정의 버튼 — reload() 로 customAction 전달 */}
          {customButtons.map((btn, i) => (
            <button
              key={i}
              id={`mis-btn-custom-${i}`}
              className="h-btn-sm px-3 rounded border border-accent bg-surface-2 text-primary text-xs font-semibold cursor-pointer hover:bg-accent hover:text-white transition-colors"
              onClick={() => {
                window.__mis_custom_action = btn.action ?? btn.label;
                gridRef.current?.reload?.();
              }}
            >{btn.label}</button>
          ))}
          {/* 목록인쇄 — 아이콘 버튼(부모 MainContent 의 '목록인쇄' 와 동일 gridRef.print) */}
          <button
            className="w-8 h-btn-sm rounded border border-border-base bg-surface text-secondary cursor-pointer hover:bg-surface-2 hover:text-primary transition-colors inline-flex items-center justify-center"
            onClick={() => gridRef.current?.print?.()}
            title="목록인쇄"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          </button>
          <button
            className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2 hover:text-primary transition-colors"
            onClick={() => { gridRef.current?.reset?.(); setPanelOpen(false); }}
          >초기화</button>
          {/* 나의백업에 추가 — 자식 프로그램은 부모정보(gubun + idx) 함께 전송 → 982 [열기] 시 부모 탭으로 복귀 가능 */}
          {(user?.is_admin === 'Y' || user?.is_dev === 'Y') && (
            <button
              className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2 hover:text-primary transition-colors"
              onClick={() => gridRef.current?.backupToMyList?.({ parent_gubun: parentGubun, parent_idx: parentIdx })}
              title="현재 자식 리스트를 JSON 으로 저장 + 부모메뉴/부모idx 함께 기록"
            >백업</button>
          )}
          {pm.allowDelete && (
            <button
              className="h-btn-sm px-2 rounded border border-danger bg-surface text-danger text-xs font-semibold cursor-pointer hover:bg-danger hover:text-white transition-colors"
              onClick={() => {
                const cnt = gridRef.current?.getCheckedCount?.() ?? 0;
                if (cnt === 0) { showToast('삭제할 항목을 선택하세요.'); return; }
                gridRef.current?.bulkDelete?.();
              }}
            >선택삭제</button>
          )}
          {menu?.brief_insert_sql && !pm.noInput && !onlyList && (
            <select
              className="h-btn-sm px-1 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer"
              value=""
              onChange={async (e) => {
                const count = parseInt(e.target.value);
                e.target.value = '';
                if (!count) return;
                try {
                  const res = await api.briefInsert(childGubun, count, parentIdx);
                  if (res.success && res.ids?.length > 0) {
                    showToast(res.message);
                    const minIdx = Math.min(...res.ids);
                    setBriefPopup({ minIdx, count: res.ids.length });
                  } else { showToast(res.message || '실패'); }
                } catch (ex) { showToast(ex.message || '간편추가 실패'); }
              }}
            >
              <option value="">간편추가</option>
              <option value="1">1줄</option>
              <option value="2">2줄</option>
              <option value="3">3줄</option>
              <option value="5">5줄</option>
              <option value="10">10줄</option>
              <option value="50">50줄</option>
            </select>
          )}
          {!pm.noInput && !onlyList && (
            <button
              className="h-btn-sm px-3 rounded bg-accent text-white text-sm border-0 cursor-pointer hover:bg-accent-hover transition-colors"
              onClick={openWrite}
            >+ 등록</button>
          )}
        </div>
      </div>

      {/* 콘텐츠 */}
      <div className="flex flex-1 overflow-hidden min-h-0">
        {/* 그리드 */}
        {showGrid && (
          <div className="flex flex-col overflow-hidden min-w-0 w-full">
            <DataGrid
              ref={gridRef}
              key={gridReloadKey}
              gubun={childGubun}
              user={user}
              menu={menu}
              onToggleView={(onlyList || pm.noFormOpen) ? undefined : handleToggleView}
              onModify={(onlyList || pm.noFormOpen) ? null : openModify}
              panelOpen={panelOpen}
              panelSize={4}
              onPanelSizeClick={null}
              currentIdx={currentIdx}
              onOpenTab={null}
              parentIdx={parentIdx}
              parentGubun={parentGubun}
              noAutoOpen={true}
              noPanelBtn={true}
              devMode={devMode}
              onSqlBtn={handleSqlBtn}
              onClientMeta={handleClientMeta}
              onOnlyList={setOnlyList}
              isWidget={true}
            />
          </div>
        )}

        {/* 상세 패널 (전체화면) */}
        {showDetail && (
          <div className="flex flex-col overflow-hidden w-full">
            {/* 패널 헤더 */}
            <div className="flex items-stretch border-b border-solid border-border-base flex-shrink-0 bg-surface">
              <div className="flex items-stretch flex-1 min-w-0 overflow-x-auto scrollbar-hide">
                {(panelMode === 'write' || panelMode === 'modify') && (
                  <span className="px-3 flex items-center text-xs font-semibold text-secondary border-r border-solid border-border-base whitespace-nowrap flex-shrink-0">
                    {panelMode === 'write' ? '등록' : '수정'}
                  </span>
                )}
                {formTabs.map(g => (
                  <button
                    key={g}
                    type="button"
                    className={[
                      'px-3 flex items-center text-sm font-semibold border-r border-solid border-border-base transition-colors cursor-pointer whitespace-nowrap flex-shrink-0',
                      formActiveTab === g ? 'bg-surface-2 text-link' : 'bg-transparent text-tab-inactive hover:text-secondary',
                    ].join(' ')}
                    onClick={() => setFormActiveTab(g)}
                  >{g}</button>
                ))}
              </div>
              <div className="flex items-center px-2 flex-shrink-0">
                <button
                  type="button"
                  className="h-btn-sm px-3 rounded border border-border-base bg-surface text-secondary text-sm cursor-pointer hover:text-primary hover:bg-surface-2 transition-colors"
                  onClick={() => setPanelOpen(false)}
                >닫기</button>
              </div>
            </div>

            {/* 폼 영역 */}
            <div className="flex-1 overflow-auto p-3">
              <DataForm
                key={`child-form-${childGubun}-${currentIdx}-${panelMode}`}
                gubun={childGubun}
                idx={currentIdx}
                mode={panelMode}
                user={user}
                menuG01={menu?.g01}
                onSaved={handleSaved}
                onCancel={handleCancel}
                onSaveSql={handleSaveSql}
                onModify={handleFormModify}
                onDelete={pm.allowDelete ? handleDeleted : null}
                activeTab={formActiveTab}
                onTabChange={setFormActiveTab}
                onTabsChange={tabs => {
                  // tabs는 {type, label} 객체 배열 — form 탭의 label만 추출
                  const labels = tabs
                    .filter(t => t.type === 'form')
                    .map(t => t.label);
                  const keys = labels.length > 0 ? labels : ['기본폼'];
                  setFormTabs(keys);
                  setFormActiveTab(prev => {
                    if (keys.includes(prev)) return prev;
                    if (keys.length === 1 && keys[0] === '기본폼') return prev;
                    return keys[0] ?? '기본폼';
                  });
                }}
              />
            </div>
          </div>
        )}
      </div>

      {/* 저장쿼리 모달 */}
      {saveSqlOpen && saveSqlData && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center modal-overlay" onClick={() => setSaveSqlOpen(false)}>
          <div className="bg-surface rounded-lg border border-border-base shadow-pop flex flex-col overflow-hidden modal-box" style={{ width: 'min(860px, 92vw)', maxHeight: '80vh' }} onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-border-base bg-surface-2 flex-shrink-0">
              <span className="text-sm font-bold text-primary">실행 쿼리 — SAVE (개발자모드)</span>
              <div className="flex items-center gap-2">
                <button className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors" onClick={() => { copyText(formatSaveSQL(buildCompleteSaveSQL(saveSqlData.sql, saveSqlData.bindings)) + ';'); showToast('복사되었습니다'); }}>복사</button>
                <button className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors" onClick={() => setSaveSqlOpen(false)}>✕ 닫기</button>
              </div>
            </div>
            <div className="flex-1 overflow-auto p-4 flex flex-col gap-4">
              <div>
                <div className="text-xs font-bold text-secondary mb-1 uppercase tracking-wide">{saveSqlData.sql?.trimStart().startsWith('INSERT') ? 'INSERT' : 'UPDATE'}</div>
                <pre className="text-xs text-primary bg-surface-2 rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6">{formatSaveSQL(saveSqlData.sql)}</pre>
              </div>
              {saveSqlData.bindings?.length > 0 && (
                <div>
                  <div className="text-xs font-bold text-secondary mb-1 uppercase tracking-wide">바인딩 값</div>
                  <pre className="text-xs text-primary bg-surface-2 rounded p-3 font-mono leading-6">{saveSqlData.bindings.map((v, i) => `[${i + 1}] ${JSON.stringify(v)}`).join('\n')}</pre>
                </div>
              )}
              {saveSqlData.execSql?.length > 0 && (
                <div>
                  <div className="text-xs font-bold text-link mb-1 uppercase tracking-wide">실행쿼리 (execSql)</div>
                  {saveSqlData.execSql.map((log, i) => (
                    <div key={i} className="mb-2">
                      <pre className={`text-xs rounded p-3 overflow-auto whitespace-pre-wrap font-mono leading-6 ${log.result === 'fail' ? 'bg-danger-dim text-danger' : 'bg-surface-2 text-primary'}`}>
                        {formatSaveSQL(log.sql)}{log.bindings?.length > 0 ? '\n-- bindings: ' + JSON.stringify(log.bindings) : ''}{'\n'}-- {log.result === 'success' ? `OK (${log.rowCount ?? 0} rows)` : `FAIL: ${log.error}`}
                      </pre>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 간편추가 팝업 */}
      {briefPopup && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center modal-overlay"
          onClick={() => { setBriefPopup(null); setGridReloadKey(k => k + 1); }}>
          <div className="bg-surface rounded-lg border border-border-base shadow-pop flex flex-col overflow-hidden modal-box"
            style={{ width: 'min(1100px, 95vw)', height: '80vh' }}
            onClick={e => e.stopPropagation()}>
            <ChildBriefPopup
              gubun={childGubun}
              user={user}
              menu={menu}
              parentIdx={parentIdx}
              minIdx={briefPopup.minIdx}
              count={briefPopup.count}
              onClose={() => { setBriefPopup(null); setGridReloadKey(k => k + 1); }}
            />
          </div>
        </div>
      )}
    </div>
  );
}

function ChildBriefPopup({ gubun, user, menu, parentIdx, minIdx, count: initialCount, onClose }) {
  const [panelOpen, setPanelOpen]     = useState(false);
  const [currentIdx, setCurrentIdx]   = useState(0);
  const [panelMode, setPanelMode]     = useState('modify');
  const [totalCount, setTotalCount]   = useState(initialCount);
  const gridRef = useRef(null);

  const filter = JSON.stringify([{ field: 'idx', operator: 'gte', value: String(minIdx) }]);

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between px-4 py-2 border-b border-border-base bg-surface-2 flex-shrink-0">
        <div className="flex items-center gap-2">
          <span className="text-sm font-bold text-primary">{menu?.menu_name} — 간편추가 {totalCount}건</span>
          <button
            className="h-btn-sm px-2 rounded border border-danger bg-surface text-danger text-xs font-semibold cursor-pointer hover:bg-danger hover:text-white transition-colors"
            onClick={() => {
              const cnt = gridRef.current?.getCheckedCount?.() ?? 0;
              if (cnt === 0) { showToast('삭제할 항목을 선택하세요.'); return; }
              gridRef.current?.bulkDelete?.();
            }}
          >선택삭제</button>
          {menu?.brief_insert_sql && (
            <select
              className="h-btn-sm px-1 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer"
              value=""
              onChange={async (e) => {
                const cnt = parseInt(e.target.value);
                e.target.value = '';
                if (!cnt) return;
                try {
                  const res = await api.briefInsert(gubun, cnt, parentIdx);
                  if (res.success) {
                    showToast(res.message);
                    setTotalCount(prev => prev + (res.ids?.length ?? cnt));
                    gridRef.current?.reload?.();
                  } else { showToast(res.message || '실패'); }
                } catch (ex) { showToast(ex.message || '간편추가 실패'); }
              }}
            >
              <option value="">추가</option>
              <option value="1">1줄</option>
              <option value="2">2줄</option>
              <option value="3">3줄</option>
              <option value="5">5줄</option>
              <option value="10">10줄</option>
              <option value="50">50줄</option>
            </select>
          )}
        </div>
        <button className="h-btn-sm px-4 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2"
                onClick={onClose}>닫기</button>
      </div>
      <div className="flex flex-1 overflow-hidden">
        <div className={panelOpen ? 'w-1/2 flex flex-col overflow-hidden border-r border-border-base' : 'flex-1 flex flex-col overflow-hidden'}>
          <DataGrid
            ref={gridRef}
            gubun={gubun}
            user={user}
            menu={{ ...menu, add_url: `&psize=200&orderby=idx&allFilter=${encodeURIComponent(filter)}` }}
            onToggleView={(pk) => { setCurrentIdx(pk); setPanelMode('modify'); setPanelOpen(true); }}
            onModify={(idx) => { setCurrentIdx(idx); setPanelMode('modify'); setPanelOpen(true); }}
            panelOpen={panelOpen}
            panelSize={panelOpen ? 2 : 1}
            currentIdx={currentIdx}
            parentIdx={parentIdx}
            noAutoOpen
            noPanelBtn
            isWidget={true}
          />
        </div>
        {panelOpen && (
          <div className="w-1/2 flex flex-col overflow-hidden panel-animate">
            <div className="flex items-center justify-between px-3 py-1 border-b border-border-base bg-surface flex-shrink-0">
              <span className="text-xs font-semibold text-secondary">{panelMode === 'modify' ? '수정' : '조회'}</span>
              <button className="text-xs text-muted hover:text-primary cursor-pointer border-0 bg-transparent" onClick={() => setPanelOpen(false)}>✕</button>
            </div>
            <div className="flex-1 overflow-auto p-3">
              <DataForm
                key={`cbrief-${gubun}-${currentIdx}-${panelMode}`}
                gubun={gubun} idx={currentIdx} mode={panelMode} user={user} menuG01={menu?.g01}
                onSaved={() => { setPanelMode('modify'); gridRef.current?.reload?.(); }}
                onCancel={() => setPanelOpen(false)}
                onModify={() => setPanelMode('modify')}
                onDelete={pm.allowDelete ? () => { setPanelOpen(false); gridRef.current?.reload?.(); } : null}
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
