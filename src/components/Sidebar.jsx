import React, { useState, useEffect } from 'react';

// brief_title 뱃지 색상 — 문자열 해시로 팔레트에서 자동 선택
const BADGE_PALETTE = [
  { bg: '#EEF2FF', text: '#4338CA' }, // 인디고
  { bg: '#FDF2F8', text: '#BE185D' }, // 핑크
  { bg: '#ECFDF5', text: '#065F46' }, // 에메랄드
  { bg: '#FFF7ED', text: '#C2410C' }, // 오렌지
  { bg: '#EFF6FF', text: '#1D4ED8' }, // 블루
  { bg: '#F5F3FF', text: '#6D28D9' }, // 바이올렛
  { bg: '#ECFEFF', text: '#0E7490' }, // 시안
  { bg: '#FFF1F2', text: '#BE123C' }, // 로즈
  { bg: '#F0FDF4', text: '#166534' }, // 그린
  { bg: '#FEFCE8', text: '#A16207' }, // 옐로우
];

function briefBadgeStyle(text) {
  let hash = 0;
  for (let i = 0; i < text.length; i++) hash = (hash * 31 + text.charCodeAt(i)) & 0xFFFF;
  const { bg, text: color } = BADGE_PALETTE[hash % BADGE_PALETTE.length];
  return { backgroundColor: bg, color };
}

// 노드 서브트리에 currentGubun 이 있으면 true
function subtreeContainsGubun(node, gubun) {
  if (!node) return false;
  if (node.idx === gubun) return true;
  for (const c of (node.children ?? [])) {
    if (subtreeContainsGubun(c, gubun)) return true;
  }
  return false;
}

// currentGubun 을 포함한 중분류 idx 반환 (없으면 첫 번째 노드 idx, 없으면 null)
function pickOpenTopIdx(menuTree, currentGubun) {
  if (currentGubun) {
    for (const node of menuTree) {
      if (subtreeContainsGubun(node, currentGubun)) return node.idx;
    }
  }
  return menuTree[0]?.idx ?? null;
}

export default function Sidebar({ menuTree, currentGubun, onSelect }) {
  // 최상단(중분류) open 상태를 부모(Sidebar)가 관리 — currentGubun 변경 시 동기화
  const [openSet, setOpenSet] = useState(() => {
    const idx = pickOpenTopIdx(menuTree, currentGubun);
    return idx != null ? new Set([idx]) : new Set();
  });

  // currentGubun 변경 시 매칭 중분류만 open (다른 사용자 토글은 덮어씀 — 의도된 네비게이션 동작)
  useEffect(() => {
    const idx = pickOpenTopIdx(menuTree, currentGubun);
    if (idx == null) return;
    setOpenSet(new Set([idx]));
  }, [currentGubun, menuTree]);

  const toggleTop = (idx) => {
    setOpenSet(prev => {
      const next = new Set(prev);
      if (next.has(idx)) next.delete(idx); else next.add(idx);
      return next;
    });
  };

  return (
    <nav className="w-sidebar h-full flex-shrink-0 bg-nav-sidebar border-r border-nav-border overflow-y-auto">
      <ul className="list-none m-0 p-0">
        {menuTree.map((node) => (
          <MenuNode
            key={node.idx}
            node={node}
            currentGubun={currentGubun}
            onSelect={onSelect}
            depth={0}
            isOpen={openSet.has(node.idx)}
            onToggleTop={() => toggleTop(node.idx)}
          />
        ))}
      </ul>
    </nav>
  );
}

function MenuNode({ node, currentGubun, onSelect, depth, isOpen, onToggleTop }) {
  const hasChildren = node.children && node.children.length > 0;
  // depth=0(중분류) 은 부모 제어, 그 외는 로컬 상태
  const [localOpen, setLocalOpen] = useState(false);
  const open = depth === 0 ? !!isOpen : localOpen;
  const setOpen = depth === 0 ? (() => onToggleTop?.()) : setLocalOpen;
  const isActive = currentGubun === node.idx;

  function handleClick(e) {
    const type = node.menu_type ?? '';
    const url  = node.add_url  ?? '';

    // 11: 현재창 이동
    if (type === '11') {
      window.location.href = url;
      return;
    }
    // 12: 새창
    if (type === '12') {
      window.open(url, '_blank');
      return;
    }

    if (hasChildren) {
      setOpen(v => !v);
    } else if (e.ctrlKey || e.metaKey) {
      const p = new URLSearchParams();
      p.set('gubun', node.idx);
      p.set('isMenuIn', 'Y');
      window.open('?' + p.toString(), '_blank');
    } else {
      // 13: iframe 탭, 그 외: 일반 탭
      onSelect(node.idx, node.menu_name, e.shiftKey, type === '13' ? url : null);
    }
  }

  return (
    <li>
      <div
        className={[
          'relative flex items-center h-row text-base cursor-pointer select-none',
          'border-b border-nav-border transition-colors',
          isActive
            ? 'bg-nav-active-bg text-nav-active-text font-semibold border-l border-l-nav-logo'
            : 'text-nav-text hover:bg-nav-hover hover:text-nav-text-hover border-l border-l-transparent',
        ].join(' ')}
        style={{ paddingLeft: `${16 + depth * 14}px`, paddingRight: '12px' }}
        onClick={handleClick}
        onMouseDown={e => { if (e.shiftKey) e.preventDefault(); }}
      >
        {hasChildren ? (
          <span className="text-nav-text-dim text-xs mr-1.5 w-3 text-center">
            {open ? '▼' : '▶'}
          </span>
        ) : (
          <span className="text-nav-text-dim text-xs mr-1.5 w-3 text-center">•</span>
        )}
        <span className="truncate">{node.menu_name}</span>
        {node.brief_title && (
          <span
            className="absolute text-[10px] font-bold leading-none px-1 py-0.5 rounded pointer-events-none"
            style={{
              ...briefBadgeStyle(node.brief_title),
              left: '-4px',
              top: '50%',
              transform: 'translateY(-50%) rotate(-30deg)',
              transformOrigin: 'center',
            }}
          >{node.brief_title}</span>
        )}
      </div>

      {hasChildren && open && (
        <ul className="list-none m-0 p-0">
          {node.children.map(child => (
            <MenuNode
              key={child.idx}
              node={child}
              currentGubun={currentGubun}
              onSelect={onSelect}
              depth={depth + 1}
            />
          ))}
        </ul>
      )}
    </li>
  );
}
