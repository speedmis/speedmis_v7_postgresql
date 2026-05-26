import React, { useState, useEffect, useRef } from 'react';

/** 전역 토스트: window.dispatchEvent(new CustomEvent('mis:toast', { detail: '메시지' })) */
export default function Toast() {
  const [items, setItems] = useState([]); // [{ id, msg }]
  const nextId = useRef(0);

  useEffect(() => {
    const handler = (e) => {
      const id  = ++nextId.current;
      const raw = e.detail;
      const msg = typeof raw === 'object' ? (raw.msg ?? '완료') : (raw ?? '완료');
      const duration = typeof raw === 'object' ? (raw.duration ?? 4000) : 4000;
      const type = typeof raw === 'object' ? (raw.type ?? '') : '';
      setItems(prev => [...prev, { id, msg, type }]);
      setTimeout(() => {
        setItems(prev => prev.filter(t => t.id !== id));
      }, duration);
    };
    window.addEventListener('mis:toast', handler);
    return () => window.removeEventListener('mis:toast', handler);
  }, []);

  if (!items.length) return null;

  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col items-center gap-2 pointer-events-none">
      {items.map(t => {
        const icon = t.type === 'success' ? '✓ ' : t.type === 'error' ? '✕ ' : t.type === 'warn' ? '⚠ ' : t.type === 'info' ? 'ℹ ' : '';
        const typeClass = t.type === 'success' ? 'toast-success' : t.type === 'error' ? 'toast-error' : t.type === 'warn' ? 'toast-warn' : t.type === 'info' ? 'toast-info' : '';
        return (
          <div key={t.id} className={`toast-item animate-toast ${typeClass}`}>
            {icon}{t.msg}
          </div>
        );
      })}
    </div>
  );
}

/** 토스트 트리거 헬퍼
 *  - showToast('메시지')                    — 기본 4초
 *  - showToast('메시지', 5000)              — 5초간 표시
 *  - showToast('메시지', 'error')           — error 스타일 (success/warn/info 도 동일)
 *  - showToast({msg, type, duration})       — 모든 옵션
 *  ⚠ 두 번째 인자가 string 이면 type, number 면 duration. (기존엔 string 도 duration 자리로 가서 NaN→즉시 소실 버그)
 */
export function showToast(msg = '복사되었습니다', typeOrDuration) {
  let detail;
  if (typeof msg === 'object' && msg !== null) {
    detail = msg;
  } else if (typeof typeOrDuration === 'string') {
    detail = { msg, type: typeOrDuration };
  } else if (typeof typeOrDuration === 'number') {
    detail = { msg, duration: typeOrDuration };
  } else {
    detail = msg;
  }
  window.dispatchEvent(new CustomEvent('mis:toast', { detail }));
}
