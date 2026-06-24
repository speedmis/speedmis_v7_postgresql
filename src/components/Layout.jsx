import React, { useState, useEffect, useReducer, useRef, useCallback } from 'react';
import api, { apiPath } from '../api';
import { showToast } from './Toast';
import Sidebar from './Sidebar';
import MainContent from './MainContent';
// Messenger 는 App.jsx 에서 렌더 — Layout/MobileLayout 공유

function findTopRealPid(menuTree, gubun) {
  for (const top of menuTree) {
    if (top.idx === gubun) return top.real_pid;
    if (searchChildren(top.children, gubun)) return top.real_pid;
  }
  return null;
}
function searchChildren(children, gubun) {
  for (const c of children ?? []) {
    if (c.idx === gubun) return true;
    if (searchChildren(c.children, gubun)) return true;
  }
  return false;
}
function findMenuName(tree, gubun) {
  for (const node of tree ?? []) {
    if (node.idx === gubun) return node.menu_name;
    const found = findMenuName(node.children, gubun);
    if (found) return found;
  }
  return null;
}
function findMenuAddUrl(tree, gubun) {
  for (const node of tree ?? []) {
    if (node.idx === gubun) return node.add_url ?? '';
    const found = findMenuAddUrl(node.children, gubun);
    if (found != null) return found;
  }
  return null;
}
function findMenuType(tree, gubun) {
  for (const node of tree ?? []) {
    if (node.idx === gubun) return node.menu_type ?? '';
    const found = findMenuType(node.children, gubun);
    if (found != null) return found;
  }
  return null;
}

const MAX_TABS = 10;

function tabReducer(state, action) {
  switch (action.type) {
    case 'OPEN': {
      const { gubun, label, openIdx, openLinkVal, forceNew, iframeUrl, openFull, addUrl } = action;
      if (!forceNew) {
        // 백업 탭(addUrl 에 _backup= 포함) 은 본 프로그램이 아니므로 매칭에서 제외 →
        // 같은 gubun 의 백업 탭만 열려 있을 때 좌측 메뉴 클릭 시 본 프로그램이 새 탭으로 열림.
        const existing = state.tabs.find(t =>
          t.gubun === gubun && !(t.addUrl && t.addUrl.includes('_backup='))
        );
        if (existing) return { ...state, activeTabId: existing.id };
      }
      const id = Date.now() + Math.random();
      const newTab = { id, gubun, label: label || String(gubun), locked: false, openIdx, openLinkVal, iframeUrl: iframeUrl ?? null, openFull: !!openFull, addUrl: addUrl ?? null };
      let next = [...state.tabs, newTab];
      while (next.length > MAX_TABS) {
        const ui = next.findIndex(t => !t.locked);
        if (ui === -1) break;
        next = next.filter((_, i) => i !== ui);
      }
      return { tabs: next, activeTabId: id };
    }
    case 'CLOSE': {
      const next = state.tabs.filter(t => t.id !== action.tabId);
      let newActiveId = state.activeTabId;
      if (action.tabId === state.activeTabId) {
        newActiveId = next.length > 0 ? next[next.length - 1].id : null;
      }
      return { tabs: next, activeTabId: newActiveId };
    }
    case 'CLOSE_ALL':
      return { tabs: [], activeTabId: null };
    case 'TOGGLE_LOCK':
      return { ...state, tabs: state.tabs.map(t => t.id === action.tabId ? { ...t, locked: !t.locked } : t) };
    case 'ACTIVATE':
      return { ...state, activeTabId: action.tabId };
    case 'REPLACE': {
      // 현재 활성 탭의 gubun/label을 교체 (탭 ID 유지, 내용만 변경)
      const { gubun, label, openIdx, openLinkVal } = action;
      return {
        ...state,
        tabs: state.tabs.map(t =>
          t.id === state.activeTabId
            ? { ...t, gubun, label: label || String(gubun), openIdx, openLinkVal, openFull: false }
            : t
        ),
      };
    }
    case 'UPDATE_LABELS':
      return {
        ...state,
        tabs: state.tabs.map(t => {
          // _backup 이 addUrl 에 있으면 라벨 prefix '(백업) '. 자동라벨일 때만 갱신.
          const isBackup = !!(t.addUrl && t.addUrl.includes('_backup='));
          const prefix   = isBackup ? '(백업) ' : '';
          const auto1    = String(t.gubun);
          const auto2    = '(백업) ' + String(t.gubun);
          const isAutoLabel = (t.label === auto1 || t.label === auto2);
          if (!isAutoLabel) return t;
          const name = findMenuName(action.menuTree, t.gubun);
          if (name) return { ...t, label: prefix + name };
          return t;
        }),
      };
    default: return state;
  }
}

export default function Layout({ user, menuTree, onLogout, siteTitle, homeGubun = 0, homeTopRealPid = '', toggleMode, msgMode, setMsgMode, msgUnread = 0 }) {
  const urlParams  = new URLSearchParams(window.location.search);
  const urlGubun   = parseInt(urlParams.get('gubun') || '0', 10);
  const urlRealPid = urlParams.get('realPid') || '';
  const urlIdx     = urlParams.get('idx') || null;
  const initGubun  = urlGubun || homeGubun || 0;

  // 자동알리미 상태는 App 레벨에서 prop 으로 받음

  // ── 탭 상태 ─────────────────────────────────────────────────────────────────
  const [tabState, dispatch] = useReducer(tabReducer, null, () => {
    if (!initGubun) return { tabs: [], activeTabId: null };
    const id = Date.now();
    const parsedIdx = urlIdx ? (/^\d+$/.test(urlIdx) ? Number(urlIdx) : urlIdx) : null;
    // 직접 URL 진입 시 _backup / tabid 파라미터 보존 — 탭 클릭 시에도 유지되도록 addUrl 에 저장
    const initBackup = urlParams.get('_backup');
    const initTabid  = urlParams.get('tabid');
    let initAddUrl = '';
    if (initBackup) initAddUrl += '&_backup=' + encodeURIComponent(initBackup);
    if (initTabid)  initAddUrl += '&tabid='   + encodeURIComponent(initTabid);
    const initLabel = initBackup ? ('(백업) ' + String(initGubun)) : String(initGubun);
    return { tabs: [{ id, gubun: initGubun, label: initLabel, locked: false, openIdx: parsedIdx, openLinkVal: urlIdx || null, addUrl: initAddUrl || null }], activeTabId: id };
  });
  const { tabs, activeTabId } = tabState;

  // ?realPid= 파라미터 → gubun 변환 후 탭 열기
  useEffect(() => {
    if (!urlRealPid || urlGubun) return;
    api.menuItemByRealPid(urlRealPid).then(res => {
      const gid = res.data?.idx;
      if (gid) {
        const parsedIdx2 = urlIdx ? (/^\d+$/.test(urlIdx) ? Number(urlIdx) : urlIdx) : null;
        dispatch({ type: 'ADD', gubun: Number(gid), label: res.data?.menu_name || urlRealPid, openIdx: parsedIdx2, openLinkVal: urlIdx || null });
        const p = new URLSearchParams(window.location.search);
        p.set('gubun', gid); p.delete('realPid'); p.set('isMenuIn', 'Y');
        history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
      }
    }).catch(() => {});
  }, []);

  // ── 분할 상태 ───────────────────────────────────────────────────────────────
  const [splitTabId, setSplitTabId]   = useState(null);   // 분할 시 보조탭 id
  const [splitDir,   setSplitDir]     = useState('h');    // 'h'=좌우, 'v'=상하
  const [splitRatio, setSplitRatio]   = useState(0.5);    // 0~1

  // menuTree 로드 후 초기 탭 레이블 갱신
  useEffect(() => {
    if (!menuTree.length) return;
    dispatch({ type: 'UPDATE_LABELS', menuTree });
  }, [menuTree]);

  // 분할 중인 탭이 닫히면 분할 해제
  useEffect(() => {
    if (splitTabId && !tabs.find(t => t.id === splitTabId)) {
      setSplitTabId(null);
    }
  }, [tabs, splitTabId]);

  // ── 파생값 ──────────────────────────────────────────────────────────────────
  const activeTab    = tabs.find(t => t.id === activeTabId) ?? null;
  const currentGubun = activeTab?.gubun ?? 0;

  const [activeTopIdx, setActiveTopIdx] = useState(urlGubun ? null : (homeTopRealPid || null));
  const [sidebarOpen, setSidebarOpen]   = useState(() => window.innerWidth > 767);

  // ── 탭 조작 ─────────────────────────────────────────────────────────────────
  function openTab(gubun, label, openIdx = null, openLinkVal = null, forceNew = false, iframeUrl = null, openFull = false, addUrl = null) {
    // 새 탭을 열기 전, 떠나는 활성 탭의 현재 URL 을 저장(되돌아올 때 복원용)
    if (activeTabId) {
      window.__tabUrls = window.__tabUrls || {};
      window.__tabUrls[activeTabId] = window.location.search;
    }
    dispatch({ type: 'OPEN', gubun, label: label || String(gubun), openIdx, openLinkVal, forceNew, iframeUrl, openFull, addUrl });
    // addUrl이 있으면 URL에 추가 파라미터 반영
    if (addUrl) {
      const p = new URLSearchParams();
      p.set('gubun', gubun);
      p.set('isMenuIn', 'Y');
      // addUrl 파싱하여 병합
      const extra = new URLSearchParams(addUrl.startsWith('&') ? addUrl.slice(1) : addUrl);
      for (const [k, v] of extra) p.set(k, v);
      history.pushState(null, '', '?' + decodeURIComponent(p.toString()));
    } else {
      updateUrl(gubun, openIdx);
    }
  }

  function closeTab(tabId) {
    const closing = tabs.find(t => t.id === tabId);
    dispatch({ type: 'CLOSE', tabId });
    if (tabId === activeTabId) {
      const remaining = tabs.filter(t => t.id !== tabId);
      const next = remaining.length > 0 ? remaining[remaining.length - 1] : null;
      if (next) {
        // 다음 활성 탭의 저장된 URL 복원(있으면), 없으면 기존 addUrl 기반
        const saved = window.__tabUrls && window.__tabUrls[next.id];
        if (saved) history.pushState(null, '', window.location.pathname + saved);
        else updateUrl(next.gubun, next.openIdx ?? null, next.addUrl ?? null);
      } else updateUrl(0);
    } else if (closing) {
      // 활성탭 유지
    }
  }

  function toggleLock(tabId) {
    dispatch({ type: 'TOGGLE_LOCK', tabId });
  }

  // Ctrl+클릭 → 분할 / 일반 클릭 → 분할 해제 후 활성화
  function handleTabClick(tab, e) {
    if (e?.ctrlKey) {
      if (tab.id === activeTabId) return; // 활성탭 자기 자신 클릭 무시
      if (splitTabId === tab.id) {
        // 같은 탭 다시 Ctrl+클릭 → 방향 전환
        setSplitDir(d => d === 'h' ? 'v' : 'h');
      } else {
        // 새 분할
        setSplitTabId(tab.id);
        setSplitDir('h');
        setSplitRatio(0.5);
      }
    } else {
      // 일반 클릭 → 분할 해제, 탭 활성화. 각 탭의 '현재 URL'(필터/정렬/aggregate 포함)을 보존/복원.
      setSplitTabId(null);
      const wasActive = (tab.id === activeTabId);
      // 떠나는 활성 탭의 현재 URL 저장 (다른 탭으로 이동할 때만)
      if (!wasActive && activeTabId) {
        window.__tabUrls = window.__tabUrls || {};
        window.__tabUrls[activeTabId] = window.location.search;
      }
      dispatch({ type: 'ACTIVATE', tabId: tab.id });
      if (!wasActive) {
        // 다른 탭 클릭 → 그 탭의 저장된 URL 복원(있으면), 없으면 기존 addUrl 기반
        const saved = window.__tabUrls && window.__tabUrls[tab.id];
        if (saved) history.pushState(null, '', window.location.pathname + saved);
        else updateUrl(tab.gubun, tab.openIdx ?? null, tab.addUrl ?? null);
      }
      // 활성 탭 자기 자신 재클릭이면 URL 을 손대지 않음 → 현재 URL(aggregate 등) 그대로 유지
      if (menuTree.length) {
        const pid = findTopRealPid(menuTree, tab.gubun);
        if (pid) { setActiveTopIdx(pid); setSidebarOpen(true); }
      }
    }
  }

  function updateUrl(gubun, idx = null, addUrl = null) {
    const params = new URLSearchParams();
    if (gubun) {
      params.set('gubun', gubun);
      params.set('isMenuIn', 'Y');
      if (idx != null) params.set('idx', String(idx));
      // addUrl 병합 — orderby/recently/aggregate/_chart 등 모든 추가 파라미터 반영
      if (addUrl) {
        const extra = new URLSearchParams(addUrl.startsWith('&') ? addUrl.slice(1) : addUrl);
        for (const [k, v] of extra) params.set(k, v);
      }
    }
    // params 비어있으면 '?' 단독 URL ('/v7/?') 안 만들도록 pathname 으로 정리
    const qs = params.toString();
    const target = qs ? '?' + decodeURIComponent(qs) : window.location.pathname;
    history.pushState(null, '', target);
  }

  function selectGubun(gubun, label, forceNew = false, iframeUrl = null) {
    const name   = label || findMenuName(menuTree, gubun) || String(gubun);
    const addUrl = findMenuAddUrl(menuTree, gubun);
    // 좌측 사이드바 진입은 부분합전용이라도 일반 탭으로 연다 (팝업은 mis:openTab 진입에서만).
    // menu.add_url 있으면 URL에 병합 반영 — orderby/recently/psize 등 기본값이 즉시 적용되도록
    openTab(gubun, name, null, null, forceNew, iframeUrl, false, addUrl || null);
    if (menuTree.length) {
      const pid = findTopRealPid(menuTree, gubun);
      if (pid) setActiveTopIdx(pid);
    }
    if (window.innerWidth <= 767) setSidebarOpen(false);
  }

  function openTabWithRecord(gubun, label, pk, linkVal) {
    openTab(gubun, label, pk, linkVal, true);
  }

  // 글로벌 탭 열기 이벤트 (MainContent 등에서 다른 gubun 탭을 열 때 사용)
  useEffect(() => {
    const handler = async (e) => {
      const { gubun: g, realPid, label, idx, linkVal, openFull, addUrl } = e.detail ?? {};
      if (g) {
        // label 이 없으면 메뉴명 조회해서 탭 이름으로 사용 (없으면 gubun 숫자 폴백)
        let finalLabel = label;
        if (!finalLabel) {
          try {
            const res = await api.menuItem(Number(g));
            finalLabel = res.data?.menu_name || String(g);
          } catch {
            finalLabel = String(g);
          }
        }
        openTab(Number(g), finalLabel, idx ?? null, linkVal ?? null, true, null, !!openFull, addUrl ?? null);
      } else if (realPid) {
        // realPid → gubun 변환 후 탭 열기
        try {
          const res = await api.menuItemByRealPid(realPid);
          const gid = res.data?.idx;
          if (gid) openTab(Number(gid), label || res.data?.menu_name || realPid, idx ?? null, linkVal ?? null, true, null, !!openFull);
        } catch {}
      }
    };
    window.addEventListener('mis:openTab', handler);
    return () => window.removeEventListener('mis:openTab', handler);
  }, []);

  // 글로벌 탭 리다이렉트 이벤트 (현재 탭을 다른 프로그램으로 교체)
  useEffect(() => {
    const handler = (e) => {
      const { gubun: g, label, idx, linkVal, addUrl } = e.detail ?? {};
      if (!g) return;
      dispatch({ type: 'REPLACE', gubun: Number(g), label: label || String(g), openIdx: idx ?? null, openLinkVal: linkVal ?? null });
      updateUrl(Number(g), idx ?? null, addUrl ?? null);
      if (menuTree.length) {
        const pid = findTopRealPid(menuTree, Number(g));
        if (pid) { setActiveTopIdx(pid); setSidebarOpen(true); }
      }
    };
    window.addEventListener('mis:redirectTab', handler);
    return () => window.removeEventListener('mis:redirectTab', handler);
  }, [menuTree]);

  useEffect(() => {
    function onPopState() {
      const params = new URLSearchParams(window.location.search);
      const g   = parseInt(params.get('gubun') || '0', 10);
      const idx = params.get('idx');
      if (!g) return;
      const found = tabs.find(t => t.gubun === g);
      if (found) {
        dispatch({ type: 'ACTIVATE', tabId: found.id });
      } else {
        // 히스토리에 있지만 탭 미보유 (ex: redirectTab 후 뒤로가기) → 현재 탭을 그 메뉴로 교체
        api.menuItem(g).then(d => {
          dispatch({
            type: 'REPLACE',
            gubun: g,
            label: d.data?.menu_name || String(g),
            openIdx: idx ?? null,
            openLinkVal: idx ?? null,
          });
        }).catch(() => {});
      }
      if (menuTree.length) {
        const pid = findTopRealPid(menuTree, g);
        if (pid) { setActiveTopIdx(pid); setSidebarOpen(true); }
      }
    }
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, [menuTree, tabs]);

  useEffect(() => {
    if (homeGubun && !urlGubun) {
      const params = new URLSearchParams(window.location.search);
      params.set('gubun', homeGubun);
      params.set('isMenuIn', 'Y');
      history.replaceState(null, '', '?' + decodeURIComponent(params.toString()));
    } else if (!urlGubun && window.location.search === '?') {
      // 빈 query (`/v7/?`) 면 pathname 만 남도록 정리
      history.replaceState(null, '', window.location.pathname);
    }
  }, []);

  useEffect(() => {
    if (!menuTree.length || !currentGubun || activeTopIdx) return;
    const pid = findTopRealPid(menuTree, currentGubun);
    if (pid) setActiveTopIdx(pid);
  }, [menuTree, currentGubun]);

  function handleTopMenu(node) {
    if (activeTopIdx === node.real_pid) {
      setSidebarOpen(v => !v);
    } else {
      setActiveTopIdx(node.real_pid);
      setSidebarOpen(true);
    }
    if (!node.children || node.children.length === 0) {
      selectGubun(node.idx, node.menu_name);
    }
  }

  // ── 분할 구분선 드래그 ───────────────────────────────────────────────────────
  const contentAreaRef = useRef(null);

  const handleDividerMouseDown = useCallback((e) => {
    e.preventDefault();
    const el = contentAreaRef.current;
    if (!el) return;

    const onMouseMove = (mv) => {
      const rect = el.getBoundingClientRect();
      let ratio;
      if (splitDir === 'h') {
        ratio = (mv.clientX - rect.left) / rect.width;
      } else {
        ratio = (mv.clientY - rect.top) / rect.height;
      }
      setSplitRatio(Math.max(0.15, Math.min(0.85, ratio)));
    };
    const onMouseUp = () => {
      window.removeEventListener('mousemove', onMouseMove);
      window.removeEventListener('mouseup', onMouseUp);
    };
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
  }, [splitDir]);

  // ── 콘텐츠 레이아웃 계산 ────────────────────────────────────────────────────
  const activeTop    = menuTree.find(n => n.real_pid === activeTopIdx);
  const sidebarMenus = activeTop?.children ?? [];

  // 분할 모드일 때 그리드 스타일
  const splitGridStyle = splitTabId ? (
    splitDir === 'h'
      ? { gridTemplateColumns: `${splitRatio * 100}% 4px 1fr`, gridTemplateRows: '1fr' }
      : { gridTemplateColumns: '1fr', gridTemplateRows: `${splitRatio * 100}% 4px 1fr` }
  ) : {};

  function renderTabContent(tab) {
    if (tab.iframeUrl) {
      return (
        <iframe
          src={tab.iframeUrl}
          className="w-full flex-1 border-0"
          style={{ height: '100%' }}
          title={tab.label}
          allow="fullscreen"
        />
      );
    }
    // menu_type='22' (서버로직만) — 직접링크-iFRAME 과 동일하게 탭 영역 통째로 iframe
    // (MainContent 안 거치고 곧바로 programs/{real_pid}.php 출력 표시)
    const menuType = findMenuType(menuTree, tab.gubun);
    if (menuType === '22') {
      return (
        <iframe
          src={apiPath(`/api.php?act=serverOnly&gubun=${tab.gubun}`)}
          className="w-full flex-1 border-0"
          style={{ height: '100%' }}
          title={tab.label}
          allow="fullscreen"
        />
      );
    }
    return (
      <MainContent
        gubun={tab.gubun}
        user={user}
        openIdx={tab.openIdx}
        openLinkVal={tab.openLinkVal}
        openFull={tab.openFull}
        onOpenTab={(pk, linkVal, label) => openTabWithRecord(tab.gubun, label, pk, linkVal)}
      />
    );
  }

  return (
    <div className="flex flex-col h-screen overflow-hidden bg-base">

      {/* ── Topbar ── */}
      <header className="flex items-center justify-between h-topbar bg-nav-bg border-b border-nav-border px-3 flex-shrink-0 gap-2 z-30 relative">
        {/* 좌측: 햄버거 + 로고 */}
        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            className="w-9 h-9 flex items-center justify-center rounded bg-transparent border-0 text-nav-text hover:bg-nav-hover hover:text-nav-text-hover cursor-pointer transition-colors text-xl flex-shrink-0"
            onClick={() => {
              if (!activeTopIdx && menuTree.length > 0) {
                setActiveTopIdx(menuTree[0].real_pid);
                setSidebarOpen(true);
              } else {
                setSidebarOpen(v => !v);
              }
            }}
          >
            ☰
          </button>
          <button
            className="hidden sm:inline-block text-lg font-bold text-nav-logo whitespace-nowrap pr-3 mr-1 border-r border-nav-border bg-transparent border-l-0 border-t-0 border-b-0 cursor-pointer hover:opacity-80 transition-opacity"
            style={{ fontFamily: 'inherit' }}
            onClick={() => {
              // 홈으로 이동만 — 열린 프로그램 탭은 유지(CLOSE_ALL 제거).
              if (homeGubun) {
                selectGubun(homeGubun);
              } else {
                // SSR 에서 homeGubun 못 잡힌 케이스 — PHP 가 REAL_PID_HOME 으로 다시 계산하도록 새로고침
                const bp = (typeof window !== 'undefined' && window.__APP_CONFIG__?.basePath) || '';
                window.location.href = bp + '/';
              }
            }}
          >
            {siteTitle}
          </button>
        </div>
        {/* 우측: 상단메뉴 + 사용자 영역 */}
        <div className="flex items-center gap-1 flex-1 justify-end overflow-hidden min-w-0">
          <nav className="flex items-center gap-0.5 overflow-x-auto scrollbar-hide h-topbar">
            {menuTree.map(node => (
              <button
                key={node.real_pid}
                className={[
                  'h-topbar px-4 bg-transparent border-0 border-b text-base cursor-pointer whitespace-nowrap transition-colors',
                  activeTopIdx === node.real_pid
                    ? 'text-nav-active-text border-nav-logo font-semibold'
                    : 'text-nav-text border-transparent hover:text-nav-text-hover hover:bg-nav-hover',
                ].join(' ')}
                onClick={() => handleTopMenu(node)}
              >
                {node.menu_name}
              </button>
            ))}
          </nav>
          <div className="flex items-center gap-2 flex-shrink-0 pl-2 border-l border-nav-border ml-1">
          <button
            type="button"
            className="flex items-center gap-1.5 px-2 h-[28px] rounded border-0 bg-transparent hover:bg-nav-hover cursor-pointer transition-colors whitespace-nowrap"
            title={`ID: ${user.uid}`}
            onClick={() => window.dispatchEvent(new CustomEvent('mis:openTab', {
              detail: {
                gubun: 338,
                label: '나의 개인정보 수정',
                idx: user.uid,
                linkVal: user.uid,
                addUrl: '&actionFlag=modify',
              }
            }))}
          >
            {user.station_name && (
              <span className="text-[11px] font-medium text-accent">{user.station_name}</span>
            )}
            <span className="text-sm font-semibold text-nav-text-hover">{user.name}</span>
          </button>
          {user.is_admin === 'Y' && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-danger text-white font-semibold whitespace-nowrap">관리자</span>
          )}
          {/* 자동알리미 종 아이콘 (설정 좌측) */}
          <button
            type="button"
            onClick={() => setMsgMode?.(msgMode === 'open' ? 'closed' : 'open')}
            className="relative w-8 h-8 flex items-center justify-center rounded border-0 bg-transparent text-nav-text hover:bg-nav-hover cursor-pointer transition-colors"
            title={`자동알리미${msgUnread > 0 ? ` (안읽음 ${msgUnread})` : ''}`}
            aria-label="자동알리미"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 01-3.46 0" />
            </svg>
            {msgUnread > 0 && (
              <span className="absolute top-0 right-0 min-w-[14px] h-[14px] px-0.5 rounded-full bg-danger text-white text-[9px] font-bold flex items-center justify-center leading-none">
                {msgUnread > 99 ? '99+' : msgUnread}
              </span>
            )}
          </button>
          <SettingsButton user={user} toggleMode={toggleMode} onLogout={onLogout} />
          </div>{/* 사용자 영역 닫기 */}
        </div>{/* 우측 전체 닫기 */}
      </header>

      {/* ── 바디 ── */}
      <div className="flex flex-1 overflow-hidden relative">
        {/* 모바일 오버레이: 사이드바 열린 상태에서 외부 클릭 시 닫기 */}
        {sidebarOpen && sidebarMenus.length > 0 && window.innerWidth <= 767 && (
          <div
            className="absolute inset-0 z-10 bg-overlay"
            onClick={() => setSidebarOpen(false)}
          />
        )}
        {sidebarOpen && sidebarMenus.length > 0 && (
          <div className={window.innerWidth <= 767 ? 'absolute top-0 left-0 bottom-0 z-20' : ''}>
            <Sidebar
              menuTree={sidebarMenus}
              currentGubun={currentGubun}
              onSelect={selectGubun}
            />
          </div>
        )}

        <main className="flex-1 overflow-hidden flex flex-col bg-base min-w-0">
          {/* 프로그램 탭 바 */}
          {tabs.length > 0 && (
            <TabBar
              tabs={tabs}
              activeTabId={activeTabId}
              splitTabId={splitTabId}
              splitDir={splitDir}
              onTabClick={handleTabClick}
              onClose={closeTab}
              onToggleLock={toggleLock}
            />
          )}

          {/* 콘텐츠 영역 */}
          {tabs.length > 0 ? (
            <div
              ref={contentAreaRef}
              className={splitTabId ? 'grid flex-1 min-h-0 overflow-hidden' : 'flex flex-col flex-1 min-h-0 overflow-hidden'}
              style={splitGridStyle}
            >
              {tabs.map(tab => {
                const isActive = tab.id === activeTabId;
                const isSplit  = tab.id === splitTabId;

                if (splitTabId) {
                  if (isActive) {
                    return (
                      <div key={tab.id} style={{ gridColumn: 1, gridRow: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0 }}>
                        {renderTabContent(tab)}
                      </div>
                    );
                  }
                  if (isSplit) {
                    return (
                      <div key={tab.id} style={{ gridColumn: splitDir === 'h' ? 3 : 1, gridRow: splitDir === 'h' ? 1 : 3, overflow: 'hidden', display: 'flex', flexDirection: 'column', minWidth: 0, minHeight: 0 }}>
                        {renderTabContent(tab)}
                      </div>
                    );
                  }
                  // 분할에 포함되지 않은 탭은 hidden (상태 보존)
                  return <div key={tab.id} style={{ display: 'none' }}>{renderTabContent(tab)}</div>;
                }

                // 분할 없는 일반 모드
                return (
                  <div key={tab.id} className={isActive ? 'flex-1 overflow-hidden flex flex-col min-h-0' : 'hidden'}>
                    {renderTabContent(tab)}
                  </div>
                );
              })}

              {/* 분할 구분선 */}
              {splitTabId && (
                <SplitDivider
                  dir={splitDir}
                  onMouseDown={handleDividerMouseDown}
                  style={{
                    gridColumn: splitDir === 'h' ? 2 : 1,
                    gridRow:    splitDir === 'h' ? 1 : 2,
                  }}
                />
              )}
            </div>
          ) : (
            <WelcomeScreen user={user} siteTitle={siteTitle} />
          )}
        </main>
      </div>

      {/* Messenger 는 App 레벨에서 렌더됨 */}
    </div>
  );
}

/* ── 분할 구분선 ────────────────────────────────────────────────────────────── */
function SplitDivider({ dir, onMouseDown, style }) {
  const isH = dir === 'h';
  return (
    <div
      onMouseDown={onMouseDown}
      style={style}
      className={[
        'flex-shrink-0 z-10 group',
        isH
          ? 'cursor-col-resize w-1 hover:w-1 relative'
          : 'cursor-row-resize h-1 relative',
      ].join(' ')}
    >
      {/* 실제 드래그 영역 (넓게) */}
      <div className={[
        'absolute inset-0 z-10',
        isH ? '-left-1 -right-1' : '-top-1 -bottom-1',
      ].join(' ')} />
      {/* 시각적 라인 */}
      <div className={[
        'absolute bg-border-base group-hover:bg-accent transition-colors duration-fast',
        isH ? 'inset-y-0 left-0 right-0' : 'inset-x-0 top-0 bottom-0',
      ].join(' ')} />
      {/* 중앙 핸들 아이콘 */}
      <div className={[
        'absolute z-20 flex items-center justify-center',
        'bg-surface border border-border-base rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity',
        isH
          ? 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-8'
          : 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-3 w-8',
      ].join(' ')}>
        <span className={['text-muted text-[9px] leading-none', isH ? '' : 'rotate-90'].join(' ')}>⋮⋮</span>
      </div>
    </div>
  );
}

/* ── 탭 바 ──────────────────────────────────────────────────────────────────── */
function TabBar({ tabs, activeTabId, splitTabId, splitDir, onTabClick, onClose, onToggleLock }) {
  return (
    <div className="flex items-end gap-0 px-2 pt-1 bg-surface border-b border-border-base flex-shrink-0 overflow-x-auto scrollbar-hide">
      {tabs.map(tab => {
        const isActive = tab.id === activeTabId;
        const isSplit  = tab.id === splitTabId;
        return (
          <div
            key={tab.id}
            title={isSplit
              ? `분할 표시 중 (${splitDir === 'h' ? '좌우' : '상하'}) — Ctrl+클릭: 방향 전환 | 클릭: 분할 해제`
              : isActive ? '현재 탭' : 'Ctrl+클릭: 분할 보기'
            }
            className={[
              'flex items-center gap-1 px-2.5 h-[30px] text-sm rounded-t border border-b-0 cursor-pointer select-none whitespace-nowrap flex-shrink-0 transition-colors',
              isActive
                ? 'bg-base border-border-base text-primary font-semibold -mb-px z-10 relative'
                : isSplit
                  ? 'bg-accent-dim border-accent text-link font-medium -mb-px z-10 relative'
                  : 'bg-surface-2 border-transparent text-secondary hover:bg-surface hover:text-primary',
            ].join(' ')}
            onClick={e => onTabClick(tab, e)}
          >
            {/* 분할 방향 아이콘 */}
            {isSplit && (
              <SplitIcon dir={splitDir} />
            )}
            {tab.iframeUrl && !isSplit && (
              <span className="text-muted text-[10px] flex-shrink-0" title="iframe">⧉</span>
            )}
            <span className="max-w-[120px] truncate">{tab.label}</span>
            {/* 잠금 */}
            <button
              title={tab.locked ? '잠금 해제' : '탭 고정'}
              className={[
                'flex-shrink-0 flex items-center justify-center w-4 h-4 rounded border-0 bg-transparent cursor-pointer transition-colors',
                tab.locked ? 'text-accent hover:text-accent-hover' : 'text-muted hover:text-secondary',
              ].join(' ')}
              onClick={e => { e.stopPropagation(); onToggleLock(tab.id); }}
            >
              {tab.locked ? (
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/>
                </svg>
              ) : (
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18 8h-1V6A5 5 0 0 0 7 6h2a3 3 0 0 1 6 0v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/>
                </svg>
              )}
            </button>
            {/* 닫기 */}
            <button
              title="탭 닫기"
              className="flex-shrink-0 flex items-center justify-center w-4 h-4 rounded border-0 bg-transparent cursor-pointer text-muted hover:text-danger hover:bg-danger-dim transition-colors text-xs leading-none"
              onClick={e => { e.stopPropagation(); onClose(tab.id); }}
            >×</button>
          </div>
        );
      })}
    </div>
  );
}

/* 분할 아이콘 */
function SplitIcon({ dir }) {
  return (
    <svg
      width="11" height="11"
      viewBox="0 0 12 12"
      fill="none"
      className="flex-shrink-0 text-link"
    >
      {dir === 'h' ? (
        /* 좌우 분할 */
        <>
          <rect x="0.5" y="0.5" width="4.5" height="11" rx="1" fill="currentColor" opacity="0.35"/>
          <rect x="7"   y="0.5" width="4.5" height="11" rx="1" fill="currentColor" opacity="0.35"/>
          <line x1="6" y1="0" x2="6" y2="12" stroke="currentColor" strokeWidth="1.5"/>
        </>
      ) : (
        /* 상하 분할 */
        <>
          <rect x="0.5" y="0.5" width="11" height="4.5" rx="1" fill="currentColor" opacity="0.35"/>
          <rect x="0.5" y="7"   width="11" height="4.5" rx="1" fill="currentColor" opacity="0.35"/>
          <line x1="0" y1="6" x2="12" y2="6" stroke="currentColor" strokeWidth="1.5"/>
        </>
      )}
    </svg>
  );
}

/* ── 설정 버튼 + 패널 ── */
function SettingsButton({ user, toggleMode, onLogout }) {
  const [open, setOpen] = useState(false);
  const [dark, setDark] = useState(document.documentElement.getAttribute('data-theme') === 'dark');
  const [devMode, setDevMode] = useState(localStorage.getItem('mis_dev_mode') === '1');
  const [viewPref, setViewPref] = useState(localStorage.getItem('mis_view_pref') || 'custom'); // auto|list|custom (최초 접속 시 '개별')
  const [aboutOpen, setAboutOpen] = useState(false);
  // PWA 설치 가능 여부 — beforeinstallprompt 가 layout/base.php 인라인 스크립트에서 캡처됨
  const [pwaInstallable, setPwaInstallable] = useState(!!(typeof window !== 'undefined' && window.__pwaInstallPrompt));
  const [pwaInstalled, setPwaInstalled] = useState(typeof window !== 'undefined'
    && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true));
  useEffect(() => {
    const onAvail = () => setPwaInstallable(true);
    const onInstd = () => { setPwaInstallable(false); setPwaInstalled(true); };
    window.addEventListener('mis:pwaInstallable', onAvail);
    window.addEventListener('mis:pwaInstalled',   onInstd);
    return () => {
      window.removeEventListener('mis:pwaInstallable', onAvail);
      window.removeEventListener('mis:pwaInstalled',   onInstd);
    };
  }, []);

  const installPwa = async () => {
    const p = window.__pwaInstallPrompt;
    if (!p) {
      // iOS 등 beforeinstallprompt 미지원 — 안내 대체
      alert('이 브라우저는 자동 설치를 지원하지 않습니다.\n\niOS Safari: 공유(↑) → "홈 화면에 추가"\nAndroid: 메뉴(⋮) → "앱 설치" 또는 "홈 화면에 추가"');
      return;
    }
    try {
      await p.prompt();
      const r = await p.userChoice;
      if (r?.outcome === 'accepted') {
        setPwaInstallable(false);
        setPwaInstalled(true);
      }
    } catch (e) {}
    window.__pwaInstallPrompt = null;
  };

  // ── 푸시 알림 ──
  const [pushPerm, setPushPerm] = useState(typeof Notification !== 'undefined' ? Notification.permission : 'default');
  const [pushSubscribed, setPushSubscribed] = useState(false);
  useEffect(() => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    navigator.serviceWorker.ready.then(reg => reg.pushManager.getSubscription())
      .then(sub => setPushSubscribed(!!sub))
      .catch(() => {});
  }, []);

  const urlBase64ToUint8Array = (base64) => {
    const padding = '='.repeat((4 - base64.length % 4) % 4);
    const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(b64);
    const arr = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  };

  const enablePush = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      alert('이 브라우저는 푸시 알림을 지원하지 않습니다.');
      return;
    }
    try {
      const perm = await Notification.requestPermission();
      setPushPerm(perm);
      if (perm !== 'granted') return;

      const reg = await navigator.serviceWorker.ready;
      const keyResp = await api.pushVapidKey();
      if (!keyResp?.configured || !keyResp?.publicKey) {
        alert('서버에 VAPID 키가 설정돼 있지 않습니다.');
        return;
      }
      // 이전 구독이 다른 VAPID 키로 등록됐을 수 있으므로 항상 해제 후 새로 등록
      const oldSub = await reg.pushManager.getSubscription();
      if (oldSub) {
        try { await api.pushUnsubscribe(oldSub.endpoint); } catch (e) {}
        try { await oldSub.unsubscribe(); } catch (e) {}
      }
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(keyResp.publicKey),
      });
      const ua = navigator.userAgent || '';
      const label = /iPhone|iPad|iPod/.test(ua) ? 'iOS' : /Android/.test(ua) ? 'Android' : 'PC';
      await api.pushSubscribe(sub.toJSON(), label);
      setPushSubscribed(true);
      showToast('푸시 알림이 켜졌습니다');
    } catch (e) {
      alert('푸시 등록 실패: ' + (e?.message ?? ''));
    }
  };

  const disablePush = async () => {
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        await api.pushUnsubscribe(sub.endpoint);
        await sub.unsubscribe();
      }
      setPushSubscribed(false);
      showToast('푸시 알림이 꺼졌습니다');
    } catch (e) {
      alert('푸시 해제 실패: ' + (e?.message ?? ''));
    }
  };

  const testPush = async () => {
    try {
      const r = await api.pushTest();
      if (r?.success) showToast('테스트 푸시 발송됨 (수신까지 잠시)');
      else showToast('실패: ' + (r?.error || r?.message || ''));
    } catch (e) {
      showToast('실패: ' + (e?.message ?? ''));
    }
  };
  // 도움말/홈페이지 — 현재 도메인 기준 (같은 사이트 내 정적 페이지로 연결)
  // 매뉴얼은 모든 사이트가 https://v7.speedmis.com/docs/ 로 통일 (정식 홈페이지)
  const HELP_URL = 'https://v7.speedmis.com/docs/';


  const toggleTheme = () => {
    const next = dark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next === 'dark' ? 'dark' : '');
    if (next === 'light') document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('mis_theme', next);
    setDark(!dark);
    fetch(apiPath('/api.php?act=saveTheme'), { method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ theme: next }) }).catch(() => {});
  };

  const toggleDev = () => {
    const next = !devMode;
    localStorage.setItem('mis_dev_mode', next ? '1' : '0');
    setDevMode(next);
    window.dispatchEvent(new Event('mis:settingsChanged'));
  };

  const changeViewPref = (v) => {
    localStorage.setItem('mis_view_pref', v);
    setViewPref(v);
    window.dispatchEvent(new Event('mis:settingsChanged'));
  };

  const optCls = (active) => [
    'flex-1 py-1.5 text-xs text-center rounded cursor-pointer border-0 transition-colors font-medium',
    active ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary',
  ].join(' ');

  return (
    <div className="relative">
      <button
        className="w-8 h-8 flex items-center justify-center rounded border-0 bg-transparent text-nav-text hover:bg-nav-hover cursor-pointer transition-colors"
        onClick={() => setOpen(v => !v)}
        title="설정"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
      </button>
      {open && (
        <>
        <div className="fixed inset-0 z-[199]" onClick={() => setOpen(false)} />
        <div className="fixed right-3 top-[52px] z-[200] w-[260px] rounded-lg border border-border-light bg-surface shadow-pop modal-box p-4 flex flex-col gap-3">
          <div className="text-xs font-bold text-primary border-b border-border-light pb-2">설정</div>

          {/* 테마 */}
          <div>
            <div className="text-[11px] text-secondary font-semibold mb-1.5">테마</div>
            <div className="flex gap-1.5">
              <button className={optCls(!dark)} onClick={() => { if (dark) toggleTheme(); }}>☀ 라이트</button>
              <button className={optCls(dark)} onClick={() => { if (!dark) toggleTheme(); }}>🌙 다크</button>
            </div>
          </div>

          {/* 뷰 모드 */}
          <div>
            <div className="text-[11px] text-secondary font-semibold mb-1.5">뷰 모드</div>
            <div className="flex gap-1.5">
              <button className={optCls(true)} onClick={() => {}}>🖥 PC</button>
              {toggleMode && <button className={optCls(false)} onClick={() => { setOpen(false); toggleMode(); }}>📱 모바일</button>}
            </div>
          </div>

          {/* 운영 모드 — 글로벌 admin(gadmin) 은 무조건 노출, 그 외엔 개발자 그룹(83) 멤버만 노출.
              비노출 사용자도 브라우저 콘솔에서 misDevOn() / misDevOff() 로 임시 사용 가능 */}
          {(user?.is_admin === 'Y' || user?.is_dev === 'Y') && (
            <div>
              <div className="text-[11px] text-secondary font-semibold mb-1.5">운영 모드</div>
              <div className="flex gap-1.5">
                <button className={optCls(!devMode)} onClick={() => { if (devMode) toggleDev(); }}>실사용</button>
                <button className={optCls(devMode)} onClick={() => { if (!devMode) toggleDev(); }}>개발자</button>
              </div>
            </div>
          )}

          {/* 조회 설정 */}
          <div>
            <div className="text-[11px] text-secondary font-semibold mb-1.5">조회 설정</div>
            <div className="flex gap-1.5">
              <button className={optCls(viewPref === 'list')} onClick={() => changeViewPref('list')}>목록만</button>
              <button className={optCls(viewPref === 'auto')} onClick={() => changeViewPref('auto')}>자동열림</button>
              <button className={optCls(viewPref === 'custom')} onClick={() => changeViewPref('custom')}>개별</button>
            </div>
          </div>

          {/* 캐시 비우기 — gadmin(글로벌 admin) 또는 개발자 그룹(83) 멤버만 노출 */}
          {(user?.is_admin === 'Y' || user?.is_dev === 'Y') && (
            <div>
              <div className="text-[11px] text-secondary font-semibold mb-1.5">캐시</div>
              <button
                className={optCls(false) + ' w-full'}
                onClick={async () => {
                  setOpen(false);
                  try {
                    await api.flushCache();
                    showToast('캐시를 비웠습니다');
                    window.dispatchEvent(new Event('mis:cacheFlushed'));
                  } catch (e) {
                    showToast('실패: ' + (e?.message ?? ''));
                  }
                }}
              >🗑 캐시 비우기</button>
            </div>
          )}

          {/* 앱 설치 (PWA) */}
          <div>
            <div className="text-[11px] text-secondary font-semibold mb-1.5">앱 설치</div>
            {pwaInstalled ? (
              <button className={optCls(false) + ' w-full'} disabled
                style={{ opacity: 0.6, cursor: 'default' }}
              >✅ 설치됨 (홈 화면에서 실행)</button>
            ) : (
              <button
                className={optCls(false) + ' w-full'}
                onClick={() => { installPwa(); }}
                title={pwaInstallable ? '브라우저 설치 다이얼로그' : '브라우저별 안내 표시'}
              >📲 홈 화면에 추가{pwaInstallable ? '' : ' (수동)'}</button>
            )}
          </div>

          {/* 푸시 알림 */}
          <div>
            <div className="text-[11px] text-secondary font-semibold mb-1.5">
              푸시 알림
              {pushPerm === 'denied' && <span className="ml-2 text-danger">(브라우저 차단됨)</span>}
            </div>
            <div className="flex gap-1.5">
              {!pushSubscribed ? (
                <button
                  className={optCls(false) + ' flex-1'}
                  onClick={enablePush}
                  disabled={pushPerm === 'denied'}
                  style={pushPerm === 'denied' ? { opacity: 0.5, cursor: 'not-allowed' } : null}
                >🔔 켜기</button>
              ) : (
                <button className={optCls(false) + ' flex-1'} onClick={disablePush}>🔕 끄기</button>
              )}
              <button
                className={optCls(false)}
                onClick={testPush}
                disabled={!pushSubscribed}
                style={!pushSubscribed ? { opacity: 0.5, cursor: 'not-allowed' } : null}
                title="자기 자신에게 테스트 알림"
              >🧪 테스트</button>
            </div>
          </div>

          {/* 도움말 / 제품정보 */}
          <div className="flex gap-1.5 pt-1 border-t border-border-light">
            <button
              className={optCls(false)}
              onClick={() => { setOpen(false); window.open(HELP_URL, '_blank', 'noopener'); }}
            >📘 도움말</button>
            <button
              className={optCls(false)}
              onClick={() => { setOpen(false); setAboutOpen(true); }}
            >ℹ 제품정보</button>
          </div>

          {/* 환경설정 관리 — gadmin 전용 (.env 직접 편집 페이지) */}
          {user?.uid === 'gadmin' && (
            <button
              className={optCls(false) + ' w-full'}
              onClick={() => {
                setOpen(false);
                const bp = (typeof window !== 'undefined' && window.__APP_CONFIG__?.basePath) || '';
                window.open(bp + '/envmanage.php', '_blank', 'noopener');
              }}
              title=".env 환경변수 편집 (gadmin 전용)"
            >⚙ 환경설정 관리</button>
          )}

          {/* 로그아웃 */}
          <button
            className="w-full py-2 text-sm rounded border border-danger text-danger bg-transparent cursor-pointer hover:bg-danger-dim transition-colors font-medium mt-1"
            onClick={() => { setOpen(false); onLogout(); }}
          >로그아웃</button>
        </div>
        </>
      )}

      {/* 제품정보 모달 */}
      {aboutOpen && <AboutModal onClose={() => setAboutOpen(false)} />}
    </div>
  );
}

/* ── 제품정보 팝업 ── */
// core(공유 번들/엔진)가 추가·변경될 때마다 버전을 미세 상향 + 갱신일 갱신 (사용자 지시 2026-06-22)
const APP_VERSION = '7.0.2';
const APP_VERSION_DATE = '2026-06-24';
function AboutModal({ onClose }) {
  // 홈페이지는 모든 사이트가 https://v7.speedmis.com/ 로 통일 (정식 홈페이지)
  const homepage = 'https://v7.speedmis.com/';
  return (
    <>
      <div className="fixed inset-0 z-[300] bg-black/40" onClick={onClose} />
      <div className="fixed top-1/2 left-1/2 z-[301] -translate-x-1/2 -translate-y-1/2 w-[360px] rounded-lg border border-border-light bg-surface shadow-pop modal-box p-5">
        <div className="flex items-center justify-between border-b border-border-light pb-2 mb-3">
          <div className="text-sm font-bold text-primary">제품정보</div>
          <button
            className="w-6 h-6 flex items-center justify-center rounded border-0 bg-transparent text-secondary hover:text-primary cursor-pointer"
            onClick={onClose}
            aria-label="닫기"
          >✕</button>
        </div>
        <div className="flex flex-col gap-2 text-sm">
          <div className="text-base font-bold text-primary">SpeedMIS v7</div>
          <InfoRow label="버전" value={APP_VERSION} />
          <InfoRow label="갱신일" value={APP_VERSION_DATE} />
          <InfoRow label="제작" value="Speedmis Inc." />
          <InfoRow label="기술" value="PHP 8.3 + Slim 4 + React 18 + Vite + MariaDB" />
          <InfoRow label="라이선스" value="무료" />
          <InfoRow label="홈페이지" value={<a href={homepage} target="_blank" rel="noopener noreferrer" className="text-link hover:underline break-all">{homepage}</a>} />
        </div>
        <div className="mt-4 flex justify-end">
          <button
            className="px-4 py-1.5 text-sm rounded border border-border-base bg-surface-2 text-primary cursor-pointer hover:bg-surface"
            onClick={onClose}
          >닫기</button>
        </div>
      </div>
    </>
  );
}

function InfoRow({ label, value }) {
  return (
    <div className="flex gap-2">
      <div className="w-[72px] flex-shrink-0 text-secondary text-[12px]">{label}</div>
      <div className="flex-1 text-primary text-[13px]">{value}</div>
    </div>
  );
}

/* 테마 토글 버튼 (레거시 — SettingsButton으로 통합됨) */
function ThemeToggle({ user }) {
  const [dark, setDark] = useState(
    document.documentElement.hasAttribute('data-theme') &&
    document.documentElement.getAttribute('data-theme') === 'dark'
  );

  function toggle() {
    const next = dark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next === 'dark' ? 'dark' : '');
    if (next === 'light') document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('mis_theme', next);
    setDark(!dark);
    fetch(apiPath('/api.php?act=saveTheme'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme: next }),
    }).catch(() => {});
  }

  return (
    <button
      onClick={toggle}
      title={dark ? '라이트 모드로 전환' : '다크 모드로 전환'}
      className="w-8 h-8 flex items-center justify-center rounded border-0 bg-transparent text-secondary hover:bg-surface-2 hover:text-primary cursor-pointer transition-colors"
    >
      {dark ? (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1" x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      ) : (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
      )}
    </button>
  );
}

function WelcomeScreen({ user, siteTitle }) {
  return (
    <div className="flex flex-col items-center justify-center h-full text-center py-16 px-10">
      <div className="text-5xl mb-4">⚡</div>
      <h2 className="text-xl font-semibold text-primary mb-2">{siteTitle}에 오신 것을 환영합니다</h2>
      <p className="text-secondary text-base">{user.name}님, 상단 메뉴를 선택해주세요.</p>
    </div>
  );
}
