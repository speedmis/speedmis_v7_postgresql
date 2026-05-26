import React, { useState, useEffect, useCallback } from 'react';
import api from '../../api';
import DataForm from '../DataForm';
import ChildProgram from '../ChildProgram';

export default function MobileFormView({ gubun, idx, linkVal, mode, user, menu, onBack, onModify, onSaved, onDeleted }) {
  const [allTabs, setAllTabs] = useState([{ type: 'form', label: '기본폼' }]);
  const [activeTab, setActiveTab] = useState('기본폼');
  const [printHtml, setPrintHtml] = useState(null);
  const [printLoading, setPrintLoading] = useState(false);

  const isPrintMode = menu?.is_use_print == '1' || menu?.is_use_print === 1;

  // 인쇄양식 로드
  useEffect(() => {
    if (!isPrintMode || !gubun || !idx) return;
    setPrintLoading(true);
    setPrintHtml(null);
    api.view(gubun, idx).then(res => {
      setPrintHtml(res.printHtml || null);
    }).catch(() => {}).finally(() => setPrintLoading(false));
  }, [isPrintMode, gubun, idx]);

  // 탭 변경 콜백 — child 탭 포함, gubun 해석
  const handleTabsChange = useCallback(async (tabs) => {
    const resolved = await Promise.all(tabs.map(async t => {
      if (t.type !== 'child') return t;
      try {
        const d = await api.menuItemByRealPid(t.realPid);
        const gid = d.data?.idx ?? 0;
        return gid > 0 ? { ...t, gubun: gid } : null;
      } catch { return null; }
    }));
    const valid = resolved.filter(Boolean);
    setAllTabs(valid);
    setActiveTab(prev => {
      const keys = valid.map(t => t.type === 'form' ? t.label : `child-${t.gubun}`);
      if (keys.includes(prev)) return prev;
      return keys[0] ?? '기본폼';
    });
  }, []);

  // 인쇄 새 창
  const openPrintPopup = useCallback(() => {
    if (!printHtml) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${menu?.menu_name ?? ''} - 인쇄</title>
      <style>body{font-family:Pretendard,-apple-system,sans-serif;padding:20px;font-size:13px;color:#191F28}
      table{border-collapse:collapse;width:100%} th,td{border:1px solid #E5E8EB;padding:6px 10px;text-align:left}
      th{background:#F5F6F8;font-weight:600} h1,h2,h3{margin:0 0 12px}
      .no-print{margin-top:24px;text-align:center}
      @media print{.no-print{display:none!important}}</style>
      </head><body>${printHtml}
      <div class="no-print">
        <button onclick="window.print()" style="padding:10px 32px;font-size:15px;cursor:pointer;border:1px solid #E5E8EB;border-radius:12px;background:#3182F6;color:#fff;font-weight:600">🖨 인쇄하기</button>
        <button onclick="window.close()" style="padding:10px 32px;font-size:15px;cursor:pointer;border:1px solid #E5E8EB;border-radius:12px;margin-left:8px;background:#fff;color:#4E5968;font-weight:600">닫기</button>
      </div></body></html>`);
    w.document.close();
    setTimeout(() => w.print(), 300);
  }, [printHtml, menu]);

  const formTabs = allTabs.filter(t => t.type === 'form');
  const childTabs = allTabs.filter(t => t.type === 'child' && t.gubun > 0);
  const isChildTab = activeTab.startsWith('child-');
  const activeChildGubun = isChildTab ? parseInt(activeTab.replace('child-', ''), 10) : 0;

  // 인쇄양식 모드 (view만)
  if (isPrintMode && mode === 'view' && !isChildTab) {
    return (
      <div style={{ height: '100%', display: 'flex', flexDirection: 'column', overflow: 'hidden', background: 'var(--m-bg)' }}>
        {/* 탭 바 */}
        {allTabs.length > 1 && <TabBar tabs={allTabs} active={activeTab} onChange={setActiveTab} />}
        <div className="m-scroll" style={{ flex: 1, padding: 16 }}>
          {printLoading ? (
            <div style={{ textAlign: 'center', padding: 40, color: 'var(--m-text-3)' }}>로딩 중...</div>
          ) : printHtml ? (
            <>
              <div data-theme="light"
                   style={{ background: '#fff', color: '#191F28', borderRadius: 12, padding: 16, boxShadow: 'var(--m-shadow-sm)' }}
                   dangerouslySetInnerHTML={{ __html: printHtml }} />
              <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
                <button onClick={openPrintPopup}
                  style={{ flex: 1, padding: '12px 0', fontSize: 15, fontWeight: 600, borderRadius: 12, border: 'none', background: 'var(--m-accent)', color: '#fff', cursor: 'pointer' }}>🖨 인쇄</button>
                <button onClick={onModify}
                  style={{ flex: 1, padding: '12px 0', fontSize: 15, fontWeight: 600, borderRadius: 12, border: '1px solid var(--m-border)', background: 'var(--m-surface)', color: 'var(--m-text-2)', cursor: 'pointer' }}>수정</button>
              </div>
            </>
          ) : (
            <div style={{ textAlign: 'center', padding: 40, color: 'var(--m-text-3)' }}>인쇄양식이 없습니다.</div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div style={{ height: '100%', display: 'flex', flexDirection: 'column', overflow: 'hidden', background: 'var(--m-bg)' }}>
      {/* 탭 바 */}
      {allTabs.length > 1 && <TabBar tabs={allTabs} active={activeTab} onChange={setActiveTab} />}

      {/* child 프로그램 */}
      {isChildTab && activeChildGubun > 0 ? (
        <div style={{ flex: 1, overflow: 'hidden' }}>
          <ChildProgram childGubun={activeChildGubun} parentIdx={linkVal ?? idx} user={user} />
        </div>
      ) : (
        /* 기본 폼 */
        <div style={{ flex: 1, overflow: 'auto', padding: 12 }} className="m-scroll">
          <DataForm
            key={`mobile-${gubun}-${idx}-${mode}`}
            gubun={gubun} idx={idx} mode={mode} user={user}
            onSaved={onSaved} onCancel={onBack} onModify={onModify} onDelete={onDeleted}
            activeTab={activeTab} onTabChange={setActiveTab} onTabsChange={handleTabsChange}
          />
        </div>
      )}
    </div>
  );
}

/** 탭 바 컴포넌트 */
function TabBar({ tabs, active, onChange }) {
  return (
    <div style={{ display: 'flex', overflowX: 'auto', flexShrink: 0, background: 'var(--m-surface)', borderBottom: '1px solid var(--m-border-light)' }}
         className="scrollbar-hide">
      {tabs.map(t => {
        const key = t.type === 'form' ? t.label : `child-${t.gubun}`;
        const isActive = active === key;
        return (
          <button
            key={key}
            style={{
              flexShrink: 0, padding: '12px 16px', fontSize: 14,
              fontWeight: isActive ? 700 : 500,
              color: isActive ? 'var(--m-accent)' : 'var(--m-text-3)',
              border: 'none', borderBottom: isActive ? '2px solid var(--m-accent)' : '2px solid transparent',
              background: 'transparent', cursor: 'pointer',
            }}
            onClick={() => onChange(key)}
          >{t.label}</button>
        );
      })}
    </div>
  );
}
