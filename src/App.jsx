import React, { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import api from './api';
import Login from './components/Login';
import Layout from './components/Layout';
import Messenger from './components/Messenger';
import MainContent from './components/MainContent';
import Toast, { showToast } from './components/Toast';
import MenuAddDialog from './components/MenuAddDialog';
import EnvManageNotice from './components/EnvManageNotice';
import useMobileMode from './hooks/useMobileMode';

const MobileLayout = lazy(() => import('./components/mobile/MobileLayout'));

const cfg = window.__APP_CONFIG__ ?? {};

export default function App() {
  // 사이트 이탈 방지 — 폼에 미저장 변경 있을 때만 경고
  // 전역 카운터 window.__misFormDirtyCount 가 > 0 이면 활성
  useEffect(() => {
    if (typeof window.__misFormDirtyCount !== 'number') window.__misFormDirtyCount = 0;
    const handler = (e) => {
      if ((window.__misFormDirtyCount ?? 0) > 0) {
        e.preventDefault();
        e.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, []);

  // 콘솔 backdoor — 개발자가 아닌 계정도 임시로 devMode 켜고 끌 수 있음
  // 사용: 브라우저 콘솔에서 misDevOn() / misDevOff() 실행
  useEffect(() => {
    window.misDevOn = () => {
      localStorage.setItem('mis_dev_mode', '1');
      console.log('[misDev] 개발자모드 ON — 새로고침');
      location.reload();
    };
    window.misDevOff = () => {
      localStorage.setItem('mis_dev_mode', '0');
      console.log('[misDev] 개발자모드 OFF — 새로고침');
      location.reload();
    };
  }, []);

  // mis:redirectTab 전역 핸들러 — Layout 없이 단독 모드(isMenuIn≠Y)에서도 리다이렉트 동작 보장.
  // Layout 이 마운트된 경우엔 거기서도 핸들러가 붙지만, 두 핸들러가 충돌하지 않도록
  // 여기서는 "Layout 미존재" 만 처리 (full URL 변경 방식).
  useEffect(() => {
    const handler = (e) => {
      const { gubun: g, idx, addUrl } = e.detail ?? {};
      if (!g) return;
      // Layout 이 있으면 그쪽이 처리 (history.pushState 로 SPA 내부 전환). 여기서는 isMenuIn≠Y 인 경우만 보강.
      const cur = new URLSearchParams(window.location.search);
      if ((cur.get('isMenuIn') ?? '') === 'Y') return;
      // 단독 모드 — 전체 URL 교체로 새 페이지 로드
      const next = new URLSearchParams();
      next.set('gubun', String(g));
      next.set('isMenuIn', 'Y');
      if (idx != null && idx !== '') next.set('idx', String(idx));
      if (addUrl) {
        const extra = new URLSearchParams(addUrl.startsWith('&') ? addUrl.slice(1) : addUrl);
        for (const [k, v] of extra) next.set(k, v);
      }
      window.location.href = '?' + decodeURIComponent(next.toString());
    };
    window.addEventListener('mis:redirectTab', handler);
    return () => window.removeEventListener('mis:redirectTab', handler);
  }, []);

  // data-opentab 버튼 전역 클릭 위임 (cell-html 안의 버튼 지원)
  useEffect(() => {
    const handler = (e) => {
      const btn = e.target.closest('[data-opentab]');
      if (!btn) return;
      e.stopImmediatePropagation();
      e.preventDefault();
      try {
        const detail = JSON.parse(btn.dataset.opentab);
        if (e.ctrlKey || e.metaKey) {
          // Ctrl+클릭: 새 창으로 열기
          const g = detail.gubun || '';
          const rp = detail.realPid || '';
          const idx = detail.idx || '';
          const params = new URLSearchParams();
          if (g) params.set('gubun', g);
          else if (rp) params.set('realPid', rp);
          if (idx) params.set('idx', idx);
          params.set('isMenuIn', 'Y');
          window.open('?' + params.toString(), '_blank');
        } else {
          window.dispatchEvent(new CustomEvent('mis:openTab', { detail }));
        }
        // data-reload-after-ms 속성: N ms 후 현재 그리드 자동 새로고침
        // (예: 새 탭에서 작업 후 결과를 현재 탭 목록에 반영하기 위함)
        const ms = parseInt(btn.dataset.reloadAfterMs ?? '0', 10);
        if (ms > 0) setTimeout(() => window.dispatchEvent(new CustomEvent('mis:reloadGrid')), ms);
      } catch {}
    };
    document.addEventListener('click', handler, true); // capture phase
    return () => document.removeEventListener('click', handler, true);
  }, []);

  // data-mis-action 버튼 전역 클릭 위임 — list_json_load __html 등에서 treat 액션 호출
  //   속성: data-mis-action, data-mis-gubun, data-mis-idx, data-mis-params(JSON, optional),
  //        data-mis-confirm(optional), data-mis-prompt(optional → window.prompt 결과를 value 로 전달)
  useEffect(() => {
    const handler = async (e) => {
      const btn = e.target.closest('[data-mis-action]');
      if (!btn) return;
      e.stopImmediatePropagation();
      e.preventDefault();
      const action = btn.dataset.misAction;
      const gubun  = Number(btn.dataset.misGubun || 0);
      const idx    = btn.dataset.misIdx ?? '';
      const confirmMsg = btn.dataset.misConfirm || '';
      const promptMsg  = btn.dataset.misPrompt  || '';
      let extra = {};
      if (btn.dataset.misParams) {
        try { extra = JSON.parse(btn.dataset.misParams); } catch {}
      }
      if (confirmMsg && !window.confirm(confirmMsg)) return;
      if (promptMsg) {
        const v = window.prompt(promptMsg, '');
        if (v === null) return;          // 취소
        if (v.trim() === '') return;     // 빈값
        extra.value = v;
      }
      // 로딩 표시 — 커서 wait + 클릭한 버튼 비활성화 + 페이지 전체 progress 커서
      const prevBodyCursor = document.body.style.cursor;
      document.body.style.cursor = 'progress';
      const prevDisabled = btn.style.pointerEvents;
      btn.style.pointerEvents = 'none';
      btn.style.opacity = '0.5';
      try {
        const res = await api.treat(gubun, { action, idx, ...extra });
        const d = res.data ?? {};
        if (d._client_alert) alert(d._client_alert);
        if (d._client_toast) showToast(d._client_toast);
        if (d.success === false && !d._client_alert && !d._client_toast) {
          showToast('실행 실패', 'error');
        }
        if (d.reloadList) window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
        if (d.reloadView) window.dispatchEvent(new CustomEvent('mis:reloadForm'));
      } catch (err) {
        showToast(err.message || '실행 실패', 'error');
      } finally {
        document.body.style.cursor = prevBodyCursor;
        btn.style.pointerEvents = prevDisabled;
        btn.style.opacity = '';
      }
    };
    document.addEventListener('click', handler, true);
    return () => document.removeEventListener('click', handler, true);
  }, []);

  // data-mis-img — 그리드/__html 이미지 클릭 시 현재 페이지에서 라이트박스로 크게 보기
  //   속성: data-mis-img(원본/큰이미지 URL), data-mis-img-name(캡션, optional)
  useEffect(() => {
    const handler = (e) => {
      const el = e.target.closest('[data-mis-img]');
      if (!el) return;
      const url = el.dataset.misImg || '';
      if (!url) return;
      e.stopImmediatePropagation();
      e.preventDefault();
      const name = el.dataset.misImgName || (url.split('/').pop() || '');
      window.dispatchEvent(new CustomEvent('mis:imageView', { detail: { url, name } }));
    };
    document.addEventListener('click', handler, true);
    return () => document.removeEventListener('click', handler, true);
  }, []);

  // data-mis-iframe 버튼 — iframe 팝업으로 해당 URL 로딩
  const [iframePopup, setIframePopup] = useState(null); // { url, title }
  useEffect(() => {
    const handler = (e) => {
      const btn = e.target.closest('[data-mis-iframe]');
      if (!btn) return;
      e.stopImmediatePropagation();
      e.preventDefault();
      const url   = btn.dataset.misIframe || '';
      const title = btn.dataset.misIframeTitle || btn.textContent || '';
      if (!url) return;
      setIframePopup({ url, title });
    };
    document.addEventListener('click', handler, true);
    // iframe 내부에서 mis:closePopup 메시지 보낼 때도 닫음
    // reloadParent=true 면 부모 프로그램 그리드/폼 재로딩
    const msg = (e) => {
      if (e?.data?.type !== 'mis:closePopup') return;
      setIframePopup(null);
      if (e.data.reloadParent && typeof window.__misRefreshProgram === 'function') {
        window.__misRefreshProgram();
      }
    };
    window.addEventListener('message', msg);
    return () => {
      document.removeEventListener('click', handler, true);
      window.removeEventListener('message', msg);
    };
  }, []);

  const [user, setUser]       = useState(cfg.user ?? null);
  const [ready, setReady]     = useState(false);
  const [menuTree, setMenuTree] = useState([]);

  // 개발자 그룹(83) 동기화:
  //   - 사용자 변경(로그인/로그아웃·계정 전환) 시에만 localStorage 를 is_dev 로 강제
  //   - 같은 사용자의 misDevOn() → reload 케이스에서는 localStorage 유지
  //   - 같은 사용자라도 서버에서 is_dev 값이 바뀌면 즉시 동기화
  useEffect(() => {
    if (!user) return;
    const curUid = user.uid || '';
    const curDev = user.is_dev === 'Y' ? 'Y' : 'N';
    const lastUid = sessionStorage.getItem('mis_last_uid');
    const lastDev = sessionStorage.getItem('mis_last_is_dev');
    if (lastUid !== curUid || lastDev !== curDev) {
      sessionStorage.setItem('mis_last_uid', curUid);
      sessionStorage.setItem('mis_last_is_dev', curDev);
      const want = curDev === 'Y' ? '1' : '0';
      if (localStorage.getItem('mis_dev_mode') !== want) {
        localStorage.setItem('mis_dev_mode', want);
        window.dispatchEvent(new CustomEvent('mis:settingsChanged'));
      }
    }
  }, [user]);
  // data-menu-add 버튼 클릭 시 팝업 오픈용 상태
  const [menuAddSrcIdx, setMenuAddSrcIdx] = useState(null);

  // data-menu-add 전역 클릭 위임 (314 목록의 '추가' 버튼용)
  useEffect(() => {
    const handler = (e) => {
      const btn = e.target.closest('[data-menu-add]');
      if (!btn) return;
      e.stopImmediatePropagation();
      e.preventDefault();
      const idx = parseInt(btn.dataset.menuAdd, 10);
      if (idx > 0) setMenuAddSrcIdx(idx);
    };
    document.addEventListener('click', handler, true);
    return () => document.removeEventListener('click', handler, true);
  }, []);

  // mis:menuAdd 커스텀 이벤트 — 현재 프로그램의 ⋯ 메뉴 '메뉴 추가' 에서 발송
  useEffect(() => {
    const h = (e) => {
      const idx = parseInt(e.detail?.srcIdx ?? 0, 10);
      if (idx > 0) setMenuAddSrcIdx(idx);
    };
    window.addEventListener('mis:menuAdd', h);
    return () => window.removeEventListener('mis:menuAdd', h);
  }, []);

  // ── 초기화: 서버 주입 user 있으면 바로 사용, 없으면 /me 시도 ──────────────
  useEffect(() => {
    async function init() {
      if (cfg.user) {
        setUser(cfg.user);
        await loadMenu();
      } else {
        try {
          const data = await api.me();
          setUser(data.user);
          await loadMenu();
        } catch {
          // 미인증 → 로그인 화면
        }
      }
      setReady(true);
    }
    init();
  }, []);

  // 강제 로그아웃 이벤트 (401)
  useEffect(() => {
    const handler = () => { setUser(null); setMenuTree([]); };
    window.addEventListener('mis:logout', handler);
    return () => window.removeEventListener('mis:logout', handler);
  }, []);

  // 로딩 화면 숨기기
  useEffect(() => {
    if (ready) {
      document.getElementById('loading-screen')?.classList.add('hidden');
    }
  }, [ready]);

  async function loadMenu() {
    try {
      const data = await api.menu();
      setMenuTree(data.data ?? []);
    } catch {
      setMenuTree([]);
    }
  }

  const handleLogin = useCallback(async (uid, pass, logoutOthers) => {
    const data = await api.login(uid, pass, logoutOthers);
    // 로그인 직후 전체 리로드 — SSR 이 쿠키를 읽고 REAL_PID_HOME 기반 homeGubun 을 주입하도록
    // 업무시스템 경로(/v7/) 로 이동 — 루트(/) 는 홈페이지라서 로그인 후 앱으로 가려면 /v7/ 필요
    window.location.href = '/v7/';
    return data;
  }, []);

  const handleLogout = useCallback(async () => {
    try { await api.logout(); } catch {}
    setUser(null);
    setMenuTree([]);
  }, []);

  if (!ready) return null;

  if (!user) {
    return <Login onLogin={handleLogin} siteTitle={cfg.siteTitle ?? 'SpeedMIS'} />;
  }

  // isMenuIn 이 Y 가 아닌 경우 → 몸통만 표시 (상단/좌측 메뉴 숨김)
  const urlParams  = new URLSearchParams(window.location.search);
  const isMenuIn   = urlParams.get('isMenuIn') ?? '';
  const urlGubun   = parseInt(urlParams.get('gubun') || '0', 10);

  if (urlGubun > 0 && isMenuIn !== 'Y') {
    // isMenuIn=S → child iframe 모드 (완전 스트립, 메뉴삽입 버튼 없음)
    const isSubFrame = isMenuIn === 'S';
    return (
      <div className="h-screen flex flex-col overflow-hidden bg-base">
        <MainContent gubun={urlGubun} user={user} key={urlGubun} />
        {!isSubFrame && (
          <button
            id="mis-menu-insert"
            className={menuInsertBtnCls}
            onClick={() => {
              const p = new URLSearchParams(window.location.search);
              p.set('isMenuIn', 'Y');
              window.location.href = '?' + p.toString();
            }}
          >
            메뉴삽입
          </button>
        )}
      </div>
    );
  }

  return (
    <>
      <AppContent
        user={user}
        menuTree={menuTree}
        onLogout={handleLogout}
        siteTitle={cfg.siteTitle ?? 'SpeedMIS'}
        homeGubun={cfg.homeGubun ?? 0}
        homeTopRealPid={cfg.homeTopRealPid ?? ''}
      />
      <Toast />
      <WelcomeFlash siteTitle={cfg.siteTitle ?? 'SpeedMIS'} />
      {menuAddSrcIdx && (
        <MenuAddDialog
          srcIdx={menuAddSrcIdx}
          onClose={() => setMenuAddSrcIdx(null)}
          onCreated={() => {
            window.dispatchEvent(new CustomEvent('mis:reloadGrid'));
          }}
        />
      )}
      {iframePopup && (
        <div
          className="fixed inset-0 z-[300] flex items-center justify-center modal-overlay"
          onClick={() => setIframePopup(null)}
        >
          <div
            className="bg-surface rounded-lg shadow-pop flex flex-col overflow-hidden"
            style={{ width: 'min(960px, 94vw)', height: 'min(720px, 92vh)' }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between px-4 py-2 border-b border-border-base bg-surface-2 flex-shrink-0">
              <span className="text-sm font-bold text-primary truncate">{iframePopup.title || '편집'}</span>
              <button
                className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer transition-colors"
                onClick={() => { setIframePopup(null); window.dispatchEvent(new CustomEvent('mis:reloadGrid')); }}
              >✕</button>
            </div>
            <iframe src={iframePopup.url} title={iframePopup.title || ''} className="flex-1 w-full border-0" />
          </div>
        </div>
      )}
    </>
  );
}

// 최초 진입(세션당 1회) 시 1.8초간 중앙에 표시되고 사라지는 웰컴 플래시
function WelcomeFlash({ siteTitle }) {
  const [visible, setVisible] = useState(() => !sessionStorage.getItem('mis_welcome_shown'));
  useEffect(() => {
    if (!visible) return;
    sessionStorage.setItem('mis_welcome_shown', '1');
    const t1 = setTimeout(() => setVisible('out'), 1200);
    const t2 = setTimeout(() => setVisible(false),  1800);
    return () => { clearTimeout(t1); clearTimeout(t2); };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  if (!visible) return null;
  return (
    <div
      className="fixed inset-0 z-[400] flex items-center justify-center pointer-events-none"
      style={{
        transition: 'opacity 500ms ease',
        opacity: visible === 'out' ? 0 : 1,
      }}
    >
      <div
        className="px-6 py-4 rounded-lg bg-surface text-primary text-base font-bold shadow-pop border border-border-base"
        style={{ animation: 'misWelcomePop 400ms ease' }}
      >
        {siteTitle}에 오신 것을 환영합니다 👋
      </div>
      <style>{`@keyframes misWelcomePop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}`}</style>
    </div>
  );
}

function AppContent({ user, menuTree, onLogout, siteTitle, homeGubun, homeTopRealPid }) {
  const { isMobile, toggleMode } = useMobileMode();
  // 메신저 상태를 App 레벨로 lift — Layout/MobileLayout 모두에서 종 아이콘 노출
  const [msgMode, setMsgMode]     = useState('closed');
  const [msgUnread, setMsgUnread] = useState(0);

  if (isMobile) {
    return (
      <>
        <Suspense fallback={<div className="h-screen flex items-center justify-center text-muted">로딩 중...</div>}>
          <MobileLayout
            user={user}
            menuTree={menuTree}
            onLogout={onLogout}
            siteTitle={siteTitle}
            toggleMode={toggleMode}
            msgUnread={msgUnread}
            onOpenMessenger={() => setMsgMode('open')}
          />
        </Suspense>
        <Messenger mode={msgMode} setMode={setMsgMode} onUnreadChange={setMsgUnread} />
        <EnvManageNotice user={user} />
      </>
    );
  }

  return (
    <>
    <Layout
      user={user}
      menuTree={menuTree}
      onLogout={onLogout}
      siteTitle={siteTitle}
      homeGubun={homeGubun}
      homeTopRealPid={homeTopRealPid}
      toggleMode={toggleMode}
      msgMode={msgMode}
      setMsgMode={setMsgMode}
      msgUnread={msgUnread}
    />
    <Messenger mode={msgMode} setMode={setMsgMode} onUnreadChange={setMsgUnread} />
    <EnvManageNotice user={user} />
    </>
  );
}

const menuInsertBtnCls = [
  'hidden md:block',
  'fixed top-3 right-3 z-50',
  'h-btn-sm px-3 text-sm rounded border border-solid cursor-pointer',
  'bg-surface border-border-base text-secondary shadow-sm',
  'hover:bg-surface-2 hover:text-primary hover:border-accent transition-colors',
].join(' ');
