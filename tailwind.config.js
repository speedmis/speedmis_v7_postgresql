/**
 * SpeedMIS v7 — Tailwind Config
 *
 * 색상 정의 원칙:
 *  - 모든 색상은 CSS 변수(design-system.css) 기반 시맨틱 토큰만 사용
 *  - text-gray-*, bg-gray-* 등 Tailwind 기본 팔레트 사용 금지 (완전 제거)
 *  - 허용 토큰: text-primary / text-secondary / text-muted / text-link
 *               bg-base / bg-surface / bg-surface-2
 *               border-base / border-light
 *               text-danger / bg-danger-dim / text-success / text-warning
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/**/*.{js,jsx,ts,tsx}',
    './layout/**/*.php',
  ],

  safelist: ['border-solid', 'sticky', 'top-0', 'bottom-0', 'z-10', 'py-1.5'],

  // 라이트/다크 모드: CSS 변수로 처리하므로 Tailwind dark mode 비활성
  darkMode: ['selector', '[data-theme="dark"]'],

  theme: {
    // ── 기본 팔레트 완전 교체 (임의 색상 클래스 사용 방지) ──────────────
    colors: {
      transparent: 'transparent',
      current:     'currentColor',
      inherit:     'inherit',
      white:       '#ffffff',
      black:       '#000000',

      // ── 시맨틱 텍스트 토큰 ─────────────────────────────────────────
      // text-primary  → 실데이터·제목 (모든 기본 텍스트)
      primary:   'var(--color-text-1)',
      // text-secondary → 보조 텍스트·레이블
      secondary: 'var(--color-text-2)',
      // text-muted → placeholder·빈값(-) 전용
      muted:        'var(--color-text-3)',
      // text-tab-inactive → 패널 탭 비활성 상태 (다크모드에서 충분히 어둡게)
      'tab-inactive': 'var(--color-tab-inactive)',
      // text-link → 링크·클릭 가능한 텍스트
      link:      'var(--color-primary)',

      // ── 배경 토큰 ──────────────────────────────────────────────────
      // bg-base → 앱 전체 배경
      base:        'var(--color-bg)',
      // bg-surface → 카드·패널
      surface:     'var(--color-surface)',
      // bg-surface-2 → 테이블 헤더·인풋 배경
      'surface-2': 'var(--color-surface-2)',
      // bg-surface-3 → hover 상태 배경
      'surface-3': 'var(--color-surface-3)',

      // ── 보더 토큰 ──────────────────────────────────────────────────
      // border-base
      'border-base':  'var(--color-border)',
      // border-light
      'border-light': 'var(--color-border-light)',
      // border-grid-line — 엑셀 셀 라인풍 얇고 연한 그리드 선
      'grid-line':    'var(--color-grid-line)',

      // ── 상태 색상 ──────────────────────────────────────────────────
      // text-accent → primary 액션 (버튼 등)
      accent:          'var(--color-primary)',
      'accent-hover':  'var(--color-primary-hover)',
      'accent-dim':    'var(--color-primary-dim)',

      danger:          'var(--color-danger)',
      'danger-dim':    'var(--color-danger-dim)',

      success:         'var(--color-success)',
      'success-dim':   'var(--color-success-dim)',

      warning:         'var(--color-warning)',
      'warning-dim':   'var(--color-warning-dim)',

      // ── 오버레이 ───────────────────────────────────────────────────
      overlay: 'var(--color-overlay)',

      // ── 네비게이션 (topbar + sidebar) — 라이트/다크 공통 딥 네이비 ──
      'nav-bg':          'var(--color-nav-bg)',
      'nav-sidebar':     'var(--color-nav-sidebar)',
      'nav-text':        'var(--color-nav-text)',
      'nav-text-dim':    'var(--color-nav-text-dim)',
      'nav-hover':       'var(--color-nav-hover)',
      'nav-active-bg':   'var(--color-nav-active-bg)',
      'nav-active-text': 'var(--color-nav-active-text)',
      'nav-border':      'var(--color-nav-border)',
      'nav-logo':        'var(--color-nav-logo)',
    },

    // ── 폰트 ───────────────────────────────────────────────────────────
    fontFamily: {
      sans: ["'Pretendard'", "'Inter'", 'system-ui', 'sans-serif'],
      mono: ["'JetBrains Mono'", "'Fira Code'", 'monospace'],
    },

    // ── 폰트 크기 ──────────────────────────────────────────────────────
    fontSize: {
      xs:   ['11px', { lineHeight: '1.4' }],
      sm:   ['12px', { lineHeight: '1.5' }],
      base: ['13px', { lineHeight: '1.5' }],
      md:   ['14px', { lineHeight: '1.5' }],
      lg:   ['16px', { lineHeight: '1.4' }],
      xl:   ['20px', { lineHeight: '1.3' }],
      '2xl':['24px', { lineHeight: '1.3' }],
    },

    // ── border-radius ──────────────────────────────────────────────────
    borderRadius: {
      none: '0',
      sm:   'var(--radius-sm)',   // 4px
      DEFAULT: 'var(--radius-md)', // 6px
      md:   'var(--radius-md)',   // 6px
      lg:   'var(--radius-lg)',   // 10px
      xl:   'var(--radius-xl)',   // 16px
      full: '9999px',
    },

    extend: {
      // ── 컴포넌트 높이 토큰 ─────────────────────────────────────────
      height: {
        topbar:     'var(--topbar-height)',
        toolbar:    'var(--toolbar-height)',
        breadcrumb: 'var(--breadcrumb-height)',
        row:        'var(--grid-row-height)',
        btn:        'var(--btn-height)',
        'btn-sm':   'var(--btn-height-sm)',
        input:      'var(--input-height)',
      },
      minHeight: {
        row: 'var(--grid-row-height)',
      },
      width: {
        sidebar:           'var(--sidebar-width)',
        'sidebar-collapsed': 'var(--sidebar-collapsed)',
      },

      // ── box-shadow ─────────────────────────────────────────────────
      boxShadow: {
        sm:  'var(--shadow-sm)',
        DEFAULT: 'var(--shadow-md)',
        md:  'var(--shadow-md)',
        lg:  'var(--shadow-lg)',
        pop: 'var(--shadow-pop)',
      },

      // ── 트랜지션 ───────────────────────────────────────────────────
      transitionDuration: {
        fast: '120ms',
        base: '200ms',
        slow: '300ms',
      },

      // ── 애니메이션 ─────────────────────────────────────────────────
      // 토스트 — 4s (페이드인 200ms / 홀드 ~3.6s / 페이드아웃 200ms)
      keyframes: {
        toast: {
          '0%':   { opacity: '0', transform: 'translateY(6px)' },
          '5%':   { opacity: '1', transform: 'translateY(0)' },
          '95%':  { opacity: '1', transform: 'translateY(0)' },
          '100%': { opacity: '0', transform: 'translateY(-4px)' },
        },
      },
      animation: {
        toast: 'toast 4s ease forwards',
      },
    },
  },

  // 코어 플러그인 중 허용하지 않는 것들 비활성 (선택적)
  corePlugins: {
    // preflight는 design-system.css 리셋과 중복 방지를 위해 비활성
    preflight: false,
  },

  plugins: [],
};
