import React, { useState, useEffect } from 'react';

/**
 * gadmin 으로 로그인했고, envmanage.php 로 .env 를 한 번도 저장한 적이 없으면
 * 화면 중앙에 dismissable 안내 카드 표시.
 *
 * 판정 기준: window.__APP_CONFIG__.envTouched === false
 *   (PHP 측에서 .env.bak.YYYYMMDD_HHmmss 백업 파일 존재 여부로 결정)
 *
 * 닫으면 sessionStorage 에 마크 → 같은 세션에서는 재표시 안 함.
 */
const SS_KEY = 'mis_env_notice_dismissed';

export default function EnvManageNotice({ user }) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!user || user.uid !== 'gadmin') return;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    if (cfg.envTouched === true) return;             // 이미 저장한 흔적 있음
    if (sessionStorage.getItem(SS_KEY) === '1') return;  // 이 세션에서 닫음
    setOpen(true);
  }, [user]);

  if (!open) return null;

  const dismiss = () => {
    sessionStorage.setItem(SS_KEY, '1');
    setOpen(false);
  };

  return (
    <div
      onClick={dismiss}
      style={{
        position: 'fixed', inset: 0, zIndex: 9999,
        background: 'rgba(0,0,0,0.45)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        padding: 16,
      }}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          background: 'var(--color-surface, #FFFFFF)',
          color: 'var(--color-text-1, #1A1D27)',
          borderRadius: 12,
          boxShadow: '0 12px 48px rgba(0,0,0,0.25)',
          padding: '28px 32px',
          width: '100%', maxWidth: 480,
          textAlign: 'center',
          border: '1px solid var(--color-border, #DDE0E8)',
        }}
      >
        <div style={{ fontSize: 44, marginBottom: 8 }}>⚙</div>
        <h2 style={{ fontSize: 18, fontWeight: 700, margin: '6px 0 12px' }}>
          환경설정을 한 번 확인하세요
        </h2>
        <p style={{ fontSize: 14, lineHeight: 1.7, color: 'var(--color-text-2, #4A5068)', margin: 0 }}>
          이 사이트는 설치 후 <b>환경설정 관리(envmanage.php)</b>를 통한 저장이<br />
          한 번도 이루어지지 않았습니다.
        </p>
        <p style={{ fontSize: 13, lineHeight: 1.7, color: 'var(--color-text-3, #8C93B0)', margin: '8px 0 20px' }}>
          DB 접속·관리자 이메일·만능비번 등 운영 전환에 필요한 설정을 검토해 주세요.<br />
          (저장 한 번 이루어지면 이 안내는 자동으로 사라집니다.)
        </p>
        <div style={{ display: 'flex', gap: 10, justifyContent: 'center' }}>
          <button
            onClick={() => { window.open('/envmanage.php', '_blank', 'noopener'); dismiss(); }}
            style={{
              padding: '10px 22px', borderRadius: 8, border: 0,
              background: 'var(--color-primary, #4F6EF7)',
              color: '#FFF', fontWeight: 600, fontSize: 14, cursor: 'pointer',
            }}
          >환경설정 관리 열기 →</button>
          <button
            onClick={dismiss}
            style={{
              padding: '10px 22px', borderRadius: 8,
              border: '1px solid var(--color-border, #DDE0E8)',
              background: 'transparent', color: 'var(--color-text-2, #4A5068)',
              fontWeight: 500, fontSize: 14, cursor: 'pointer',
            }}
          >나중에</button>
        </div>
      </div>
    </div>
  );
}
