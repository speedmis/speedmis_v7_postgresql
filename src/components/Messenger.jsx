import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import api from '../api';
import { showToast } from './Toast';

/**
 * 자동알리미 (구 웹메신저)
 *  - mode: 'closed' | 'open' | 'minimized'
 *  - 자동알림(system) 탭 활성 시 우측에 조직도(컴팩트 트리) 노출 → 1:1/그룹 채팅 시작
 *  - 일반 룸은 메시지 + 입력 영역만
 *  - 탭 라벨: dm = 상대방 이름, group = "{first}외{N-1}인", 모두 우측에 최근시각 표기
 *  - 동일 멤버셋 그룹 채팅이 이미 있으면 새로 만들지 않고 기존 룸으로 이동
 */
export default function Messenger({ mode, setMode, onUnreadChange }) {
  const [rooms, setRooms]         = useState([]);
  const [activeIdx, setActiveIdx] = useState(null);
  const [messages, setMessages]   = useState([]);
  const [input, setInput]         = useState('');
  const [loading, setLoading]     = useState(false);
  const scrollRef                 = useRef(null);

  // 조직도
  const [orgData, setOrgData]       = useState({ stations: [], users: [] });
  const [orgLoaded, setOrgLoaded]   = useState(false);
  const [picked, setPicked]         = useState(() => new Set()); // 그룹채팅 후보 user_id Set
  const [collapsedSt, setCollapsedSt] = useState(() => new Set()); // 접힘 station idx Set
  // 카톡식 '초대' 모드 — 값이 있으면 조직도 picker 의 버튼이 '초대(N)' 로 바뀌고
  // 그룹생성 대신 inviteToRoom(activeIdx) 호출. dm=새 그룹생성 / group=기존방에 멤버 ADD.
  const [inviteRoomIdx, setInviteRoomIdx] = useState(null);
  const [lightbox, setLightbox]     = useState(null);  // {url, name} — 이미지 모달
  const [pos, setPos]               = useState(null);  // {top,left} 드래그 후 절대 위치
  const [scale, setScale]           = useState(1);     // 1 또는 0.65 (축소 토글)
  const [isMobile, setIsMobile]     = useState(() => typeof window !== 'undefined' && window.innerWidth < 640);
  const panelRef                    = useRef(null);

  // 모바일 감지 (640px 미만 = 폰) — 풀스크린 처리
  useEffect(() => {
    const onResize = () => setIsMobile(window.innerWidth < 640);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);
  const activeRoom                  = useMemo(() => rooms.find(r => r.idx === activeIdx), [rooms, activeIdx]);
  const inputRef                    = useRef(null);
  const fileInputRef                = useRef(null);
  const tabsRef                     = useRef(null);
  const [hiddenIdxs, setHiddenIdxs] = useState(() => new Set());
  const [showMore,   setShowMore]   = useState(false);

  // 이미지 클릭 → 라이트박스 (전역 이벤트로 연결, PWA 친화적)
  useEffect(() => {
    const h = (e) => setLightbox(e.detail || null);
    window.addEventListener('mis:imageView', h);
    return () => window.removeEventListener('mis:imageView', h);
  }, []);

  // 가려진 탭(좌/우 스크롤 영역 밖) 탐지 — ResizeObserver + scroll 이벤트
  useEffect(() => {
    const el = tabsRef.current;
    if (!el) return;
    const compute = () => {
      const cRect = el.getBoundingClientRect();
      const next = new Set();
      el.querySelectorAll('[data-tab-idx]').forEach(t => {
        const r = t.getBoundingClientRect();
        if (r.right > cRect.right + 2 || r.left < cRect.left - 2) {
          next.add(parseInt(t.dataset.tabIdx, 10));
        }
      });
      setHiddenIdxs(prev => {
        if (prev.size === next.size && [...prev].every(x => next.has(x))) return prev;
        return next;
      });
    };
    compute();
    const ro = new ResizeObserver(compute);
    ro.observe(el);
    el.addEventListener('scroll', compute);
    return () => { ro.disconnect(); el.removeEventListener('scroll', compute); };
  }, [rooms.length, scale, mode]);

  // 외부 클릭 시 ... 드롭다운 닫기
  useEffect(() => {
    if (!showMore) return;
    const onClick = (e) => {
      if (!e.target.closest('[data-tab-more]')) setShowMore(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [showMore]);

  // 탭 선택 + 가려져 있으면 가시영역으로 스크롤
  const selectTab = (idx) => {
    setActiveIdx(idx);
    setShowMore(false);
    requestAnimationFrame(() => {
      const t = tabsRef.current?.querySelector(`[data-tab-idx="${idx}"]`);
      t?.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
    });
  };

  // 드래그바 mousedown/touchstart → 패널 이동
  const startDrag = (e) => {
    // 버튼 위에서 시작한 클릭은 드래그 X
    if (e.target.closest('button, a, input')) return;
    const isTouch = e.type === 'touchstart';
    const point   = isTouch ? e.touches[0] : e;
    e.preventDefault();
    const sx = point.clientX, sy = point.clientY;
    const rect = panelRef.current?.getBoundingClientRect();
    if (!rect) return;
    const startTop = rect.top, startLeft = rect.left;
    const onMove = (ev) => {
      const p = isTouch ? ev.touches[0] : ev;
      setPos({
        top : Math.max(0, startTop  + (p.clientY - sy)),
        left: Math.max(0, startLeft + (p.clientX - sx)),
      });
    };
    const onUp = () => {
      document.removeEventListener(isTouch ? 'touchmove' : 'mousemove', onMove);
      document.removeEventListener(isTouch ? 'touchend'  : 'mouseup',   onUp);
    };
    document.addEventListener(isTouch ? 'touchmove' : 'mousemove', onMove, { passive: false });
    document.addEventListener(isTouch ? 'touchend'  : 'mouseup',   onUp);
  };

  // ── 룸 목록 폴링 — 기본 10초, .env CHAT_REALTIME_POLLING=N 이면 진입 시 1회만 ─────
  const fetchRooms = useCallback(async () => {
    try {
      const r = await api.chatRooms();
      if (!r?.success) return null;
      const list = r.rooms || [];
      setRooms(list);
      onUnreadChange?.(list.reduce((s, x) => s + (x.unread_count || 0), 0));
      return list;
    } catch { return null; }
  }, [onUnreadChange]);

  useEffect(() => {
    let mounted = true;
    fetchRooms();
    // 실시간 폴링 비활성 시 setInterval 없이 1회만 — 자동알리미 메시지는 사이트 진입 시점에 한 번 수신
    const realtime = window.__APP_CONFIG__?.chatRealtimePolling !== false;
    if (!realtime) return () => { mounted = false; };
    const t = setInterval(() => { if (mounted) fetchRooms(); }, 10000);
    return () => { mounted = false; clearInterval(t); };
  }, [fetchRooms]);

  // ── 첫 진입 자동 룸 선택 (system 우선) ─────────────────────────────
  useEffect(() => {
    if (!activeIdx && rooms.length > 0) {
      const sys = rooms.find(r => r.room_type === 'system');
      setActiveIdx(sys ? sys.idx : rooms[0].idx);
    }
  }, [rooms.length, activeIdx]);

  // ── 활성 룸 메시지 로드 + 5초 폴링 (org 가상탭은 스킵) ──────────────
  useEffect(() => {
    if (!activeIdx || activeIdx === 'org') { setMessages([]); return; }
    let mounted = true;
    const fetch = async () => {
      try {
        const r = await api.chatHistory(activeIdx, 0, 100);
        if (!mounted || !r?.success) return;
        setMessages(r.messages || []);
        const last = (r.messages || [])[(r.messages || []).length - 1];
        if (last) api.chatRead(activeIdx, last.idx).catch(() => {});
      } catch {}
    };
    setLoading(true);
    fetch().finally(() => mounted && setLoading(false));
    const t = mode === 'open' ? setInterval(fetch, 5000) : null;
    return () => { mounted = false; t && clearInterval(t); };
  }, [activeIdx, mode]);

  // ── 자동 스크롤 ───────────────────────────────────────────────────
  useEffect(() => {
    if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [messages.length]);

  // ── 조직도: 별도 'org' 가상 탭이 활성일 때 로딩 ──────────────────
  const isOrg     = activeIdx === 'org';
  const isSystem  = !isOrg && activeRoom?.room_type === 'system';
  useEffect(() => {
    if (mode === 'open' && isOrg && !orgLoaded) {
      api.chatOrgTree().then(r => {
        if (r?.success) {
          setOrgData({ stations: r.stations || [], users: r.users || [] });
          setOrgLoaded(true);
        }
      }).catch(() => {});
    }
  }, [mode, isOrg, orgLoaded]);

  // ── 조직도 트리 빌드 ──────────────────────────────────────────────
  const orgTree = useMemo(() => {
    const usersByStation = {};
    for (const u of orgData.users) {
      const k = u.station_idx ?? 0;
      (usersByStation[k] ||= []).push(u);
    }
    const byIdx = {};
    for (const s of orgData.stations) byIdx[s.idx] = { ...s, children: [], users: usersByStation[s.idx] || [] };
    const roots = [];
    for (const s of orgData.stations) {
      const node = byIdx[s.idx];
      if (s.upidx && byIdx[s.upidx] && s.upidx !== s.idx) byIdx[s.upidx].children.push(node);
      else roots.push(node);
    }
    // 사원이 없는 빈 부서는 (자식 부서도 비었으면) 숨기기
    const hasAnyUser = (n) => n.users.length > 0 || n.children.some(hasAnyUser);
    const prune = (list) => list.filter(hasAnyUser).map(n => ({ ...n, children: prune(n.children) }));
    return prune(roots);
  }, [orgData]);

  // ── 메시지 송신 (textarea 의 plain text + 첨부 URL) ─────────────────
  const send = async () => {
    const text = (input ?? '').trim();
    if (!text || !activeRoom) return;
    setInput('');
    try {
      const r = await api.chatSend(activeIdx, text);
      if (r?.success && r.message) setMessages(m => [...m, r.message]);
      else showToast('전송 실패: ' + (r?.message || ''));
    } catch (e) { showToast('전송 실패: ' + (e?.message || '')); }
  };

  // ── 첨부 업로드 → 입력창에 URL 텍스트 삽입 ──────────────────────────
  const insertAtCursor = (text) => {
    const el = inputRef.current;
    if (!el) { setInput(v => (v ? v + ' ' : '') + text); return; }
    const start = el.selectionStart ?? el.value.length;
    const end   = el.selectionEnd ?? el.value.length;
    const v     = el.value;
    const next  = v.slice(0, start) + text + v.slice(end);
    setInput(next);
    requestAnimationFrame(() => {
      el.focus();
      const pos = start + text.length;
      el.setSelectionRange(pos, pos);
    });
  };

  const uploadAndInsert = async (file) => {
    if (!file) return;
    try {
      const r = await api.chatUpload(file);
      if (!r?.success) { showToast('업로드 실패: ' + (r?.message || '')); return; }
      // 본문에는 URL 텍스트로만. 렌더러가 URL 패턴 인식해서 이미지/첨부 표시.
      const sep = (input && !input.endsWith(' ') && !input.endsWith('\n')) ? ' ' : '';
      insertAtCursor(sep + r.url + ' ');
    } catch (e) { showToast('업로드 실패: ' + e.message); }
  };

  const handlePaste = async (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;
    let handled = false;
    for (const item of items) {
      if (item.kind === 'file') {
        const f = item.getAsFile();
        if (f) { handled = true; await uploadAndInsert(f); }
      }
    }
    if (handled) e.preventDefault();
  };

  const handleDrop = async (e) => {
    if (!e.dataTransfer?.files?.length) return;
    e.preventDefault();
    for (const f of e.dataTransfer.files) await uploadAndInsert(f);
  };

  const handleKey = (e) => {
    if (e.key === 'Enter') {
      if (e.ctrlKey || e.metaKey) {
        // Ctrl/Cmd + Enter = 줄바꿈 — textarea 기본 새 줄을 막지 않도록 직접 \n 삽입
        e.preventDefault();
        const el = inputRef.current;
        if (el) {
          const s = el.selectionStart ?? input.length;
          const en = el.selectionEnd ?? input.length;
          setInput(input.slice(0, s) + '\n' + input.slice(en));
          requestAnimationFrame(() => { el.focus(); el.setSelectionRange(s+1, s+1); });
        }
      } else if (!e.shiftKey) {
        // Enter = 전송
        e.preventDefault();
        send();
      }
      // Shift+Enter = 기본 줄바꿈 (textarea 자체 처리)
    }
  };

  const handleFileChoose = async (e) => {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    for (const f of files) await uploadAndInsert(f);
  };

  // ── 1:1 채팅 시작 ─────────────────────────────────────────────────
  const startDm = async (userId) => {
    try {
      const r = await api.chatRoomDm(userId);
      if (r?.success && r.room_idx) {
        await fetchRooms();
        setActiveIdx(r.room_idx);
        setPicked(new Set());
      } else showToast('채팅 시작 실패: ' + (r?.message || ''));
    } catch (e) { showToast('실패: ' + e.message); }
  };

  // ── 룸 퇴장 ────────────────────────────────────────────────────────
  const leaveRoom = async () => {
    if (!activeRoom || activeRoom.room_type === 'system') return;
    const msg = activeRoom.room_type === 'dm'
      ? '나가시겠습니까?'
      : `[${labelOf(activeRoom)}] 대화방에서 나가시겠습니까?\n\n그룹의 다른 사용자가 새 메시지를 보내면 자동으로 다시 합류됩니다.`;
    if (!confirm(msg)) return;
    try {
      const r = await api.chatLeave(activeIdx);
      if (r?.success) {
        const list = await fetchRooms();
        // 자동알림 룸으로 전환 (없으면 첫번째)
        const sys = (list || []).find(x => x.room_type === 'system');
        setActiveIdx(sys ? sys.idx : (list?.[0]?.idx ?? null));
        showToast('나갔습니다');
      } else showToast('퇴장 실패: ' + (r?.message || ''));
    } catch (e) { showToast('실패: ' + e.message); }
  };

  // ── 그룹 채팅 시작/이동 ────────────────────────────────────────────
  const startGroup = async () => {
    const members = Array.from(picked);
    if (members.length === 0) return;
    try {
      const r = await api.chatRoomGroup('', members);
      if (r?.success && r.room_idx) {
        await fetchRooms();
        setActiveIdx(r.room_idx);
        setPicked(new Set());
      } else showToast('그룹채팅 실패: ' + (r?.message || ''));
    } catch (e) { showToast('실패: ' + e.message); }
  };

  // ── 카톡식 초대: 현재 활성 방에 picked 멤버 추가 ──────────────────
  //   dm  → 새 그룹 방 생성 (DM 보존)  /  group → 기존 방에 멤버 ADD
  const inviteToRoom = async () => {
    const members = Array.from(picked);
    if (!inviteRoomIdx || members.length === 0) return;
    try {
      const r = await api.chatInvite(inviteRoomIdx, members);
      if (r?.success && r.room_idx) {
        await fetchRooms();
        setActiveIdx(r.room_idx);  // dm 였으면 새 그룹방, group 이면 기존방
        setPicked(new Set());
        setInviteRoomIdx(null);
        showToast(r.room_idx === inviteRoomIdx ? '초대 완료' : '새 그룹 채팅방으로 이동했습니다');
      } else showToast('초대 실패: ' + (r?.message || ''));
    } catch (e) { showToast('실패: ' + e.message); }
  };

  // 초대 시작 — 현재 방 컨텍스트 저장 후 조직도 탭으로 전환
  const startInvite = () => {
    if (!activeRoom || activeRoom.room_type === 'system') return;
    setInviteRoomIdx(activeIdx);
    setPicked(new Set());
    setActiveIdx('org');
  };

  // ── 탭 라벨 + 시각 ────────────────────────────────────────────────
  const formatWhen = (s) => {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
      return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    }
    const y = new Date(now); y.setDate(y.getDate()-1);
    if (d.toDateString() === y.toDateString()) return '어제';
    return `${d.getMonth()+1}/${d.getDate()}`;
  };

  const labelOf = (r) => {
    if (r.room_type === 'system') return '🔔 자동알림';
    if (r.room_type === 'dm')     return r.other_names || `대화#${r.idx}`;
    // group (text only, tooltip / 나가기 confirm 용)
    // 본인 포함 N인 룸 → "{first}외{N-1}인" 형식 (others.length = N-1 이므로 그대로 사용)
    const others = (r.other_names || '').split(',').map(s => s.trim()).filter(Boolean);
    if (r.title)             return r.title;
    if (others.length === 0) return `그룹#${r.idx}`;
    if (others.length === 1) return others[0];
    return `${others[0]}외${others.length}인`;
  };

  /** 탭 라벨 JSX — 그룹은 성씨 도트 스타일: 박.양 / 정.엄.<외> */
  const renderTabLabel = (r) => {
    if (r.room_type === 'system') return '🔔 자동알림';
    if (r.room_type === 'dm')     return r.other_names || `대화#${r.idx}`;
    const others = (r.other_names || '').split(',').map(s => s.trim()).filter(Boolean);
    if (r.title)             return r.title;
    if (others.length === 0) return `그룹#${r.idx}`;
    const surnames = others.map(n => n.charAt(0));
    if (surnames.length === 1) return surnames[0];
    if (surnames.length === 2) return `${surnames[0]}.${surnames[1]}`;
    return (
      <>
        {surnames[0]}.{surnames[1]}.<span className="ml-0.5 text-[8.5px] px-1 py-[1px] rounded bg-accent/15 text-link font-bold leading-none">외</span>
      </>
    );
  };

  /** 탭 툴팁 — 그룹은 멤버 전원, dm 은 상대방 + 시각, system 은 라벨 */
  const tooltipOf = (r) => {
    if (r.room_type === 'group') return '👥 ' + (r.other_names || '') + (r.last_message_at ? `\n최근: ${r.last_message_at}` : '');
    if (r.room_type === 'dm')    return (r.other_names || '') + (r.last_message_at ? ` (${formatWhen(r.last_message_at)})` : '');
    return labelOf(r);
  };

  const totalUnread = rooms.reduce((s, r) => s + (r.unread_count || 0), 0);

  // ─────────────────────────────────────────────────────────────
  // 렌더
  // ─────────────────────────────────────────────────────────────
  const lightboxEl = lightbox ? (
    <Lightbox url={lightbox.url} name={lightbox.name} onClose={() => setLightbox(null)} />
  ) : null;

  if (mode === 'closed') return lightboxEl;

  if (mode === 'minimized') {
    return (
      <>
      <button
        onClick={() => setMode('open')}
        className="fixed bottom-5 right-5 w-12 h-12 rounded-full bg-accent text-white shadow-pop flex items-center justify-center cursor-pointer border-0 hover:opacity-90 z-[300]"
        title="자동알리미 열기"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        {totalUnread > 0 && (
          <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center border border-white">
            {totalUnread > 99 ? '99+' : totalUnread}
          </span>
        )}
      </button>
      {lightboxEl}
      </>
    );
  }

  return (
    <>
    <div
      ref={panelRef}
      className={
        'fixed bg-surface border-border-base shadow-pop z-[300] flex flex-col overflow-hidden ' +
        (isMobile ? 'border-0' : 'border rounded-lg')
      }
      style={{
        // 모바일: 풀스크린 + 1.4배 확대 (논리 크기 71.4% → scale(1.4) → 시각 100%)
        top    : isMobile ? 0 : (pos ? `${pos.top}px`  : '60px'),
        left   : isMobile ? 0 : (pos ? `${pos.left}px` : 'auto'),
        right  : isMobile ? 'auto' : (pos ? 'auto'    : '12px'),
        bottom : isMobile ? 'auto' : 'auto',
        width  : isMobile ? 'calc(100vw / 1.4)'  : (isOrg ? 'min(540px, calc(100vw - 24px))' : 'min(440px, calc(100vw - 24px))'),
        height : isMobile ? 'calc(100dvh / 1.4)' : 'min(620px, calc(100vh - 76px))',
        transform       : isMobile ? 'scale(1.4)' : (scale !== 1 ? `scale(${scale})` : undefined),
        transformOrigin : 'top left',
      }}
    >
      {/* ── 드래그 바 (이동핸들) + 우측 끝에 축소/닫기 ── */}
      <div
        className="flex items-center h-6 px-2 bg-accent text-white cursor-move select-none flex-shrink-0"
        onMouseDown={startDrag}
        onTouchStart={startDrag}
      >
        <span className="text-[10px] font-semibold opacity-90">🔔 자동알리미</span>
        <div className="flex-1" />
        <button
          type="button"
          onClick={() => setScale(s => s === 1 ? 0.65 : 1)}
          title={scale === 1 ? '65% 축소' : '원래크기로 확장'}
          className="w-6 h-5 flex items-center justify-center text-white/85 hover:text-white hover:bg-white/15 rounded border-0 bg-transparent cursor-pointer"
        >
          {scale !== 1 ? (
            // 확장 아이콘 — 네 모서리 밖으로 뻗는 화살표
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="10,4 4,4 4,10"/>
              <polyline points="14,4 20,4 20,10"/>
              <polyline points="4,14 4,20 10,20"/>
              <polyline points="20,14 20,20 14,20"/>
            </svg>
          ) : (
            // 축소 아이콘 — 네 모서리 안으로 모이는 화살표
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="4,10 10,10 10,4"/>
              <polyline points="20,10 14,10 14,4"/>
              <polyline points="4,14 10,14 10,20"/>
              <polyline points="20,14 14,14 14,20"/>
            </svg>
          )}
        </button>
        <button
          type="button"
          onClick={() => setMode('closed')}
          title="닫기"
          className="w-6 h-5 ml-0.5 flex items-center justify-center text-white/85 hover:text-white hover:bg-white/20 rounded border-0 bg-transparent cursor-pointer text-[12px]"
        >✕</button>
      </div>

      {/* ── 탭 ── */}
      <div className="relative flex items-stretch border-b border-border-light bg-surface-2 flex-shrink-0">
        <div ref={tabsRef} className="flex flex-1 overflow-x-auto scrollbar-hide">
          {rooms.length === 0 && <span className="px-3 py-2 text-xs text-muted self-center">대화방이 없습니다</span>}
          {(() => {
            // 시스템 탭 바로 뒤에 '조직도' 가상 탭 끼워넣기. 시스템 룸 없으면 맨 앞에.
            const out = [];
            const sysRoom = rooms.find(r => r.room_type === 'system');
            if (!sysRoom) out.push({ kind: 'org' });
            for (const r of rooms) {
              out.push({ kind: 'room', room: r });
              if (r.room_type === 'system') out.push({ kind: 'org' });
            }
            return out.map((it, i) => {
              if (it.kind === 'org') {
                const isActive = activeIdx === 'org';
                return (
                  <button
                    key="org"
                    data-tab-idx="org"
                    onClick={() => selectTab('org')}
                    className={[
                      'relative flex items-center gap-1.5 px-3 py-2 text-[12px] whitespace-nowrap border-0 border-b-2 cursor-pointer transition-colors',
                      isActive ? 'border-accent text-primary font-semibold bg-surface' : 'border-transparent text-secondary bg-transparent hover:text-primary',
                    ].join(' ')}
                    title="조직도 — 동료 클릭=1:1, 체크=그룹채팅"
                  >🧭 조직도</button>
                );
              }
              const r = it.room;
              const isActive = r.idx === activeIdx;
              const unread   = r.unread_count || 0;
              const when     = r.room_type !== 'system' ? formatWhen(r.last_message_at) : '';
              return (
                <button
                  key={`r-${r.idx}`}
                  data-tab-idx={r.idx}
                  onClick={() => selectTab(r.idx)}
                  className={[
                    'relative flex items-center gap-1.5 px-3 py-2 text-[12px] whitespace-nowrap border-0 border-b-2 cursor-pointer transition-colors',
                    isActive ? 'border-accent text-primary font-semibold bg-surface' : 'border-transparent text-secondary bg-transparent hover:text-primary',
                  ].join(' ')}
                  title={tooltipOf(r)}
                >
                  <span className="max-w-[140px] truncate">{renderTabLabel(r)}</span>
                  {when && <span className="text-[10px] text-muted">{when}</span>}
                  {unread > 0 && (
                    <span className="min-w-[16px] h-[16px] px-1 rounded-full bg-danger text-white text-[9px] font-bold flex items-center justify-center">
                      {unread > 99 ? '99+' : unread}
                    </span>
                  )}
                </button>
              );
            });
          })()}
        </div>
        {/* ... 더보기 버튼 — 가려진 탭이 있을 때만 노출 */}
        {hiddenIdxs.size > 0 && (
          <div data-tab-more className="relative flex-shrink-0 border-l border-border-light">
            <button
              type="button"
              onClick={() => setShowMore(v => !v)}
              className="h-full px-2 text-secondary hover:text-primary border-0 bg-transparent cursor-pointer flex items-center text-[14px]"
              title={`가려진 탭 ${hiddenIdxs.size}개`}
            >
              ⋯
              <span className="ml-0.5 min-w-[14px] h-[14px] px-0.5 rounded-full bg-accent text-white text-[9px] font-bold flex items-center justify-center leading-none">
                {hiddenIdxs.size}
              </span>
            </button>
            {showMore && (
              <div className="absolute right-0 top-full mt-0.5 w-[200px] max-h-[280px] overflow-y-auto bg-surface border border-border-base rounded shadow-pop z-[10]">
                {rooms.filter(r => hiddenIdxs.has(r.idx)).map(r => {
                  const unread = r.unread_count || 0;
                  return (
                    <button
                      key={r.idx}
                      onClick={() => selectTab(r.idx)}
                      className="w-full flex items-center gap-2 px-2 py-1.5 text-[12px] text-left text-primary hover:bg-surface-2 border-0 bg-transparent cursor-pointer"
                      title={tooltipOf(r)}
                    >
                      <span className="flex-1 truncate">{labelOf(r)}</span>
                      {unread > 0 && (
                        <span className="min-w-[16px] h-[16px] px-1 rounded-full bg-danger text-white text-[9px] font-bold flex items-center justify-center flex-shrink-0">
                          {unread > 99 ? '99+' : unread}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        )}
      </div>

      {/* ── 룸 멤버 strip + 초대/나가기 (dm/group 만, system/org 제외) ── */}
      {activeRoom && !isOrg && activeRoom.room_type !== 'system' && (
        <div className="px-3 py-1 border-b border-border-light bg-surface-2 text-[11px] text-secondary flex-shrink-0 flex items-center gap-1.5"
             title={(activeRoom.room_type === 'group' ? '멤버: 나, ' : '상대: ') + (activeRoom.other_names || '')}>
          <span className="text-[12px]">{activeRoom.room_type === 'group' ? '👥' : '👤'}</span>
          <span className="truncate flex-1">
            {activeRoom.room_type === 'group' ? <><b>나</b>, {activeRoom.other_names || ''}</> : (activeRoom.other_names || '')}
          </span>
          {/* CHAT_REALTIME_POLLING=N 모드에서는 초대 비활성 — 채팅 사용 제한 일관성 */}
          {window.__APP_CONFIG__?.chatRealtimePolling !== false && (
            <button
              type="button"
              onClick={startInvite}
              title={activeRoom.room_type === 'dm' ? '초대 — 새 그룹 채팅방 생성 (DM 은 그대로)' : '초대 — 이 그룹방에 멤버 추가'}
              className="px-1.5 py-0.5 text-[10px] rounded border border-border-light bg-surface text-secondary hover:text-link hover:border-link cursor-pointer flex-shrink-0"
            >＋초대</button>
          )}
          <button
            type="button"
            onClick={leaveRoom}
            title="이 대화방 나가기"
            className="px-1.5 py-0.5 text-[10px] rounded border border-border-light bg-surface text-secondary hover:text-danger hover:border-danger cursor-pointer flex-shrink-0"
          >🚪 나가기</button>
        </div>
      )}

      {/* ── 본문: org 탭이면 풀 조직도 / 그 외 = 메시지 ── */}
      <div className="flex-1 flex overflow-hidden">
        {isOrg ? (
          <OrgPanel
            tree={orgTree}
            picked={picked}
            setPicked={setPicked}
            collapsed={collapsedSt}
            setCollapsed={setCollapsedSt}
            onPickOne={inviteRoomIdx ? null : startDm}
            onStartGroup={inviteRoomIdx ? inviteToRoom : startGroup}
            onCancelInvite={inviteRoomIdx ? () => { setInviteRoomIdx(null); setPicked(new Set()); } : null}
            inviteMode={!!inviteRoomIdx}
            loaded={orgLoaded}
            readOnly={window.__APP_CONFIG__?.chatRealtimePolling === false}
            full
          />
        ) : (
          <div ref={scrollRef} className="flex-1 overflow-y-auto p-3 flex flex-col gap-2 bg-base">
            {loading && messages.length === 0 && <div className="text-xs text-muted text-center mt-8">불러오는 중…</div>}
            {!loading && messages.length === 0 && <div className="text-xs text-muted text-center mt-8">메시지 없음</div>}
            {messages.map(m => (
              <div key={m.idx} className="flex flex-col gap-0.5">
                <div className="text-[10px] text-muted">
                  보낸사람: <b className={m.from_type === 'system' ? 'text-link' : 'text-secondary'}>
                    {m.from_name || (m.from_type === 'system' ? '자동알림' : '?')}
                  </b>
                  <span className="ml-2">{(m.wdate || '').slice(11, 16)}</span>
                </div>
                <div className={[
                  'inline-block px-3 py-1.5 rounded-md text-[13px] leading-relaxed whitespace-pre-wrap break-words max-w-[85%] self-start',
                  m.from_type === 'system' ? 'bg-accent/10 text-primary' : 'bg-surface text-primary border border-border-light',
                ].join(' ')}>
                  <MessageBody text={m.body || ''} />
                </div>
                {m.meta?.url && (
                  <a href={m.meta.url} target="_blank" rel="noopener noreferrer"
                     className="text-[11px] text-link hover:underline self-start ml-1">→ 바로가기</a>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── 입력 영역 (system/org 는 readonly/없음) ── */}
      {isOrg ? null : !isSystem ? (
        <div className="flex gap-2 p-2 border-t border-border-light bg-surface flex-shrink-0 items-end">
          <input ref={fileInputRef} type="file" multiple style={{ display: 'none' }} onChange={handleFileChoose} />
          <button
            type="button"
            className="w-8 h-8 flex items-center justify-center rounded border border-border-base bg-surface text-secondary cursor-pointer hover:text-primary text-[14px] flex-shrink-0"
            onClick={() => fileInputRef.current?.click()}
            title="파일 첨부"
          >📎</button>
          <textarea
            ref={inputRef}
            rows={1}
            className="flex-1 px-2 py-1.5 border border-border-base rounded text-[11px] bg-surface text-primary outline-none focus:border-accent resize-none leading-snug"
            style={{ minHeight: '32px', maxHeight: '120px' }}
            placeholder="Enter=전송, Ctrl+Enter=줄바꿈, 이미지 붙여넣기 OK"
            value={input}
            onChange={e => setInput(e.target.value)}
            onKeyDown={handleKey}
            onPaste={handlePaste}
            onDrop={handleDrop}
            onDragOver={e => e.preventDefault()}
            disabled={!activeRoom}
          />
          <button
            className="px-3 h-8 bg-accent text-white rounded font-semibold text-[12px] cursor-pointer border-0 hover:opacity-90 flex-shrink-0"
            onClick={send}
            disabled={!activeRoom || !input.trim()}
            style={(!activeRoom || !input.trim()) ? { opacity: 0.5, cursor: 'not-allowed' } : null}
          >전송</button>
        </div>
      ) : (
        <div className="px-3 py-2 text-[11px] text-muted text-center border-t border-border-light bg-surface italic flex-shrink-0">
          🔔 자동알림 — 우측에서 동료를 클릭하면 1:1, 체크하면 그룹 채팅 시작
        </div>
      )}
    </div>
    {lightboxEl}
    </>
  );
}

/* ── 메시지 본문 렌더 ───────────────────────────────────────────────
 * 본문은 plain text 로 저장됨. 안에 chat_temp URL 이 섞여있으면 이미지/첨부 형태로 렌더.
 * URL 의 `?n=` 쿼리에서 원본 파일명을 가져와 다운로드 시 사용.
 */
function MessageBody({ text }) {
  // URL 패턴 — http(s) 또는 /v7/ 로 시작하는 절대경로 (캡쳐 그룹 → split 결과 홀수 인덱스가 매치)
  const urlRe = /(https?:\/\/[^\s<>"]+|\/[\w\-/]+\/uploads\/chat_temp\/[^\s<>"]+)/g;
  const parts = text.split(urlRe);
  return (
    <>
      {parts.map((p, i) => {
        if (i % 2 === 1) {
          // URL 매치
          const isAttach = /\/uploads\/chat_temp\//.test(p);
          if (isAttach) {
            // 원본 파일명 추출 (?n= 우선, 없으면 path 끝)
            let name = '';
            try {
              const u = new URL(p, window.location.origin);
              name = u.searchParams.get('n') || decodeURIComponent(u.pathname.split('/').pop() || '');
            } catch { name = p.split('/').pop() || p; }
            const ext = (name.split('.').pop() || '').toLowerCase();
            const isImg = ['jpg','jpeg','png','gif','webp','bmp','svg','heic'].includes(ext);
            return isImg
              ? <ImageBlock key={i} url={p} name={name} />
              : <FileBlock  key={i} url={p} name={name} />;
          }
          return (
            <a key={i} href={p} target="_blank" rel="noopener noreferrer"
               className="text-link hover:underline break-all">{p}</a>
          );
        }
        // 일반 텍스트 (개행 보존)
        return <span key={i}>{p}</span>;
      })}
    </>
  );
}

function ImageBlock({ url, name }) {
  const onView = (e) => {
    e.preventDefault();
    window.dispatchEvent(new CustomEvent('mis:imageView', { detail: { url, name } }));
  };
  return (
    <span className="inline-flex items-center gap-1 align-middle my-0.5">
      <a href={url} onClick={onView} title={`${name} — 클릭하여 크게 보기`}>
        <img src={url} alt={name}
             data-chat-image={url} data-chat-image-name={name}
             className="block max-w-full max-h-[200px] rounded border border-border-light cursor-zoom-in" />
      </a>
      <a href={url} download={name} title={`${name} 다운로드`}
         className="w-6 h-6 flex items-center justify-center rounded border border-border-light bg-surface text-secondary hover:text-primary hover:bg-surface-2 self-start">💾</a>
    </span>
  );
}

/* ── 이미지 라이트박스 (현재창 모달) ───────────────────────────────────
 * 다중 이미지 네비게이션: 열릴 때 DOM 의 [data-chat-image] 를 모두 수집해 list 구성.
 * 채팅창의 모든 이미지를 좌우 화살표 / 키보드 ←→ / 터치 스와이프 / 썸네일 클릭으로 둘러봄.
 * 업무프로그램 첨부파일 ImageGallery 와 동일 UX.
 */
function Lightbox({ url, name, onClose }) {
  // 처음 열릴 때 채팅창 내 모든 이미지 수집
  const list = useMemo(() => {
    const imgs = Array.from(document.querySelectorAll('img[data-chat-image]'));
    const arr = imgs.map(el => ({
      url: el.dataset.chatImage,
      name: el.dataset.chatImageName || (el.dataset.chatImage.split('/').pop() || ''),
    }));
    return arr.length > 0 ? arr : [{ url, name }];
  }, []); // 한 번만 — 라이트박스 열려있는 동안 잠금
  const [idx, setIdx] = useState(() => {
    const i = list.findIndex(it => it.url === url);
    return i >= 0 ? i : 0;
  });
  const total = list.length;
  const cur = list[idx] || { url, name };
  const hasNav = total > 1;

  const prev = useCallback(() => setIdx(i => (i - 1 + total) % total), [total]);
  const next = useCallback(() => setIdx(i => (i + 1) % total), [total]);

  // 키보드 — 좌/우/Esc
  useEffect(() => {
    const h = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); onClose(); }
      else if (hasNav && e.key === 'ArrowLeft')  { e.preventDefault(); prev(); }
      else if (hasNav && e.key === 'ArrowRight') { e.preventDefault(); next(); }
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [onClose, hasNav, prev, next]);

  // 터치 스와이프
  const touchRef = useRef({ x: 0, y: 0 });
  const onTouchStart = (e) => {
    const t = e.touches[0];
    touchRef.current = { x: t.clientX, y: t.clientY };
  };
  const onTouchEnd = (e) => {
    if (!hasNav) return;
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
    active?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  }, [idx]);

  return (
    <div className="fixed inset-0 z-[400] flex flex-col"
         style={{ background: 'rgba(0,0,0,0.85)' }}
         onClick={onClose}
         onTouchStart={onTouchStart}
         onTouchEnd={onTouchEnd}>
      {/* 상단 헤더 — 파일명 + 카운터 + 다운로드 + 닫기 */}
      <div className="flex items-center justify-between px-4 py-2 text-white/90 text-[13px] flex-shrink-0 gap-3"
           onClick={e => e.stopPropagation()}>
        <span className="truncate flex-1" title={cur.name}>{cur.name}</span>
        {hasNav && <span className="text-white/70 text-[12px] flex-shrink-0">{idx + 1} / {total}</span>}
        <a href={cur.url} download={cur.name} title={`${cur.name} 다운로드`}
           className="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white text-base cursor-pointer no-underline flex-shrink-0">💾</a>
        <button type="button" onClick={onClose} title="닫기 (Esc)"
                className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white text-lg cursor-pointer border-0 leading-none flex items-center justify-center flex-shrink-0">✕</button>
      </div>

      {/* 메인 이미지 영역 */}
      <div className="flex-1 flex items-center justify-center relative overflow-hidden"
           onClick={e => e.stopPropagation()}>
        {hasNav && (
          <button type="button" onClick={prev} title="이전 (←)"
                  className="absolute left-2 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl cursor-pointer border-0 transition-colors">‹</button>
        )}
        <img src={cur.url} alt={cur.name}
             className="max-w-[92vw] max-h-[78vh] rounded shadow-lg object-contain select-none"
             draggable={false} />
        {hasNav && (
          <button type="button" onClick={next} title="다음 (→)"
                  className="absolute right-2 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl cursor-pointer border-0 transition-colors">›</button>
        )}
      </div>

      {/* 하단 썸네일 스트립 */}
      {hasNav && (
        <div ref={stripRef}
             className="flex-shrink-0 flex gap-1.5 px-3 py-2 overflow-x-auto"
             style={{ background: 'rgba(0,0,0,0.5)', scrollbarWidth: 'thin' }}
             onClick={e => e.stopPropagation()}>
          {list.map((im, i) => (
            <button key={i} type="button" data-idx={i}
                    onClick={() => setIdx(i)}
                    className="flex-shrink-0 cursor-pointer rounded overflow-hidden border-2 transition-all bg-transparent p-0"
                    style={{
                      width: 60, height: 60,
                      borderColor: i === idx ? '#4F6EF7' : 'transparent',
                      opacity: i === idx ? 1 : 0.55,
                    }}
                    title={im.name}>
              <img src={im.url} alt={im.name}
                   className="w-full h-full object-cover" loading="lazy" draggable={false} />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function FileBlock({ url, name }) {
  // PWA 에서 새창 열림 방지 — target=_blank 제거, 클릭=다운로드만
  return (
    <a href={url} download={name} title={`${name} 다운로드`}
       className="inline-flex items-center gap-1 px-2 py-1 rounded border border-border-light bg-surface hover:bg-surface-2 text-secondary hover:text-primary no-underline text-[12px] my-0.5 align-middle">
      <span>💾</span>
      <span className="max-w-[200px] truncate">{name}</span>
    </a>
  );
}

/* ── 조직도 패널 (컴팩트 트리) ───────────────────────────────────────
 *   full=true: 풀 너비(별도 탭으로 분리됐을 때)
 */
function OrgPanel({ tree, picked, setPicked, collapsed, setCollapsed, onPickOne, onStartGroup, onCancelInvite, inviteMode = false, loaded, readOnly = false, full = false }) {
  const toggleStation = (idx) => {
    setCollapsed(prev => {
      const n = new Set(prev);
      if (n.has(idx)) n.delete(idx); else n.add(idx);
      return n;
    });
  };
  const togglePick = (uid) => {
    setPicked(prev => {
      const n = new Set(prev);
      if (n.has(uid)) n.delete(uid); else n.add(uid);
      return n;
    });
  };

  return (
    <div className={
      full
        ? 'flex-1 bg-surface flex flex-col overflow-hidden'
        : 'w-[230px] flex-shrink-0 border-l border-border-light bg-surface flex flex-col overflow-hidden'
    }>
      <div className="px-2 py-1.5 text-[11px] font-bold text-secondary border-b border-border-light bg-surface-2 flex items-center gap-1.5">
        <span className="flex-1 truncate">
          {inviteMode ? '＋초대할 멤버 선택' : `조직도${readOnly ? ' (보기전용)' : ''}`}
        </span>
        {inviteMode && (
          <button
            onClick={onCancelInvite}
            className="px-1.5 py-0.5 text-[10px] rounded border border-border-light bg-surface text-secondary hover:text-danger hover:border-danger cursor-pointer"
            title="초대 취소"
          >취소</button>
        )}
        {!readOnly && picked.size > 0 && (
          <button
            onClick={onStartGroup}
            className="px-2 py-0.5 text-[10px] rounded bg-accent text-white border-0 cursor-pointer hover:opacity-90 font-medium"
            title={inviteMode ? '선택한 인원을 현재 방에 초대' : '선택한 인원으로 그룹채팅'}
          >{inviteMode ? `초대(${picked.size})` : `그룹(${picked.size})`}</button>
        )}
      </div>
      <div className="flex-1 overflow-y-auto py-1">
        {!loaded && <div className="text-[11px] text-muted text-center mt-4">불러오는 중…</div>}
        {loaded && tree.length === 0 && <div className="text-[11px] text-muted text-center mt-4">사원 없음</div>}
        {tree.map(node => (
          <OrgNode
            key={node.idx}
            node={node}
            depth={0}
            picked={picked}
            collapsed={collapsed}
            onToggleStation={toggleStation}
            onTogglePick={togglePick}
            onPickOne={onPickOne}
            readOnly={readOnly}
          />
        ))}
      </div>
    </div>
  );
}

function OrgNode({ node, depth, picked, collapsed, onToggleStation, onTogglePick, onPickOne, readOnly = false }) {
  const isCollapsed = collapsed.has(node.idx);
  const indent = depth * 8;
  return (
    <>
      <div
        className="flex items-center gap-1 px-1.5 py-0.5 cursor-pointer hover:bg-surface-2 text-[11px] text-secondary font-semibold"
        style={{ paddingLeft: `${4 + indent}px` }}
        onClick={() => onToggleStation(node.idx)}
        title={node.station_name}
      >
        <span className="text-[9px] w-3 text-center text-muted">{isCollapsed ? '▶' : '▼'}</span>
        <span className="truncate">{node.station_name}</span>
        <span className="text-muted text-[10px] ml-auto">{node.users.length || ''}</span>
      </div>
      {!isCollapsed && (
        <>
          {node.users.map(u => (
            <div
              key={u.user_id}
              className="flex items-center gap-1 py-[1px] hover:bg-surface-2 text-[11px] text-primary group"
              style={{ paddingLeft: `${(readOnly ? 8 : 20) + indent}px`, paddingRight: '4px' }}
            >
              {!readOnly && (
                <input
                  type="checkbox"
                  className="w-3 h-3 cursor-pointer flex-shrink-0"
                  checked={picked.has(u.user_id)}
                  onChange={() => onTogglePick(u.user_id)}
                  onClick={e => e.stopPropagation()}
                  title="그룹채팅 선택"
                />
              )}
              {readOnly ? (
                <span
                  className="flex-1 text-left truncate text-primary"
                  title={`${u.user_name} ${u.position_name || ''}${u.hand_phone ? ' · ' + u.hand_phone : ''}`}
                >{u.user_name}</span>
              ) : onPickOne ? (
                <button
                  type="button"
                  onClick={() => onPickOne(u.user_id)}
                  className="flex-1 text-left truncate bg-transparent border-0 cursor-pointer text-primary hover:text-link p-0"
                  title={`${u.user_name} ${u.position_name || ''} 와 1:1 채팅`}
                >{u.user_name}</button>
              ) : (
                // 초대 모드: 이름 클릭도 체크박스 토글로 동작 (1:1 채팅 비활성)
                <button
                  type="button"
                  onClick={() => onTogglePick(u.user_id)}
                  className="flex-1 text-left truncate bg-transparent border-0 cursor-pointer text-primary hover:text-link p-0"
                  title={`${u.user_name} ${u.position_name || ''} 선택`}
                >{u.user_name}</button>
              )}
              {u.position_name && (
                <span className="text-[10px] text-muted flex-shrink-0">{u.position_name}</span>
              )}
              {u.hand_phone && (
                <a
                  href={`tel:${u.hand_phone}`}
                  className="text-[10px] text-link flex-shrink-0 hover:underline font-mono"
                  onClick={e => e.stopPropagation()}
                  title="전화걸기"
                >{u.hand_phone}</a>
              )}
            </div>
          ))}
          {node.children.map(c => (
            <OrgNode
              key={c.idx}
              node={c}
              depth={depth + 1}
              picked={picked}
              collapsed={collapsed}
              onToggleStation={onToggleStation}
              onTogglePick={onTogglePick}
              onPickOne={onPickOne}
              readOnly={readOnly}
            />
          ))}
        </>
      )}
    </>
  );
}
