import { useState, useCallback } from 'react';

const KEY = 'mis_view_mode';

function detectMobile() {
  const url = new URLSearchParams(window.location.search);
  const urlMode = url.get('mode');
  if (urlMode === 'mobile') return true;
  if (urlMode === 'pc')     return false;
  const saved = localStorage.getItem(KEY);
  if (saved) return saved === 'mobile';
  // 자동 감지: UA 기반 → 폰/태블릿 OS 면 모바일.
  // (innerWidth 만으로는 PC 의 좁은 창과 구별 안 되고, maxTouchPoints 만으로는 터치PC 와 구별 안 됨)
  const ua = navigator.userAgent || '';
  const isMobileUA = /Mobi|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|webOS/i.test(ua);
  // iPadOS 13+ 은 iPad UA 가 Mac 으로 위장 → maxTouchPoints + Mac UA 조합으로 추가 탐지
  const isIpadOS   = /Macintosh/i.test(ua) && navigator.maxTouchPoints > 1;
  if (isMobileUA || isIpadOS) return true;
  // UA 가 PC 인데 화면이 매우 좁고 터치 가능 → 폴백
  return window.innerWidth <= 768 && navigator.maxTouchPoints > 0;
}

export default function useMobileMode() {
  const [isMobile, setIsMobile] = useState(detectMobile);

  const toggleMode = useCallback(() => {
    const next = !isMobile;
    localStorage.setItem(KEY, next ? 'mobile' : 'pc');
    setIsMobile(next);
    // URL에 mode 파라미터 업데이트
    const p = new URLSearchParams(window.location.search);
    p.set('mode', next ? 'mobile' : 'pc');
    window.history.replaceState(null, '', '?' + decodeURIComponent(p.toString()));
  }, [isMobile]);

  return { isMobile, toggleMode };
}
