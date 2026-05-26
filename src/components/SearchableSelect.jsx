import React, { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

const SEARCHABLE_THRESHOLD = 30;

export { SEARCHABLE_THRESHOLD };

export default function SearchableSelect({ options, value, onChange, className }) {
  const [open, setOpen]       = useState(false);
  const [search, setSearch]   = useState('');
  const [pos, setPos]         = useState({ top: 0, left: 0, width: 0 });
  const btnRef   = useRef(null);
  const panelRef = useRef(null);
  const inputRef = useRef(null);
  const listRef  = useRef(null);

  const selected = options.find(o => o.value === String(value ?? ''));

  useEffect(() => {
    if (!open) return;
    const handler = e => {
      if (btnRef.current?.contains(e.target)) return;
      if (panelRef.current?.contains(e.target)) return;
      setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    setSearch('');
    if (btnRef.current) {
      const r = btnRef.current.getBoundingClientRect();
      setPos({ top: r.bottom + 2, left: r.left, width: Math.max(r.width, 200) });
    }
    requestAnimationFrame(() => inputRef.current?.focus());
  }, [open]);

  useEffect(() => {
    if (!open || !listRef.current || !value) return;
    requestAnimationFrame(() => {
      const el = listRef.current?.querySelector('[data-active="true"]');
      if (el) el.scrollIntoView({ block: 'nearest' });
    });
  }, [open, value]);

  const lower = search.toLowerCase();
  const filtered = search
    ? options.filter(o => (o.text ?? o.value).toLowerCase().includes(lower) || o.value.toLowerCase().includes(lower))
    : options;

  return (
    <>
      <button
        ref={btnRef}
        type="button"
        className={className + ' w-full h-full flex items-center justify-between gap-1 text-left'}
        onClick={() => setOpen(v => !v)}
      >
        <span className="truncate flex-1 min-w-0">{selected ? (selected.text ?? selected.value) : '-- 선택 --'}</span>
        <svg width="10" height="10" viewBox="0 0 20 20" fill="currentColor" className="flex-shrink-0 opacity-40"><path d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z"/></svg>
      </button>

      {open && createPortal(
        <div
          ref={panelRef}
          className="fixed z-[300] rounded border border-border-base bg-surface shadow-md"
          style={{ top: pos.top, left: pos.left, width: pos.width }}
        >
          <div className="border-b border-border-base p-1.5">
            <input
              ref={inputRef}
              type="text"
              className="w-full h-7 px-2 text-sm text-primary bg-surface-2 rounded border border-border-base outline-none focus:border-accent"
              placeholder="검색..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>
          <div ref={listRef} className="overflow-auto" style={{ maxHeight: 240 }}>
            <div
              className={'px-2.5 py-1.5 text-sm cursor-pointer transition-colors ' + (!value ? 'bg-accent/10 text-link font-semibold' : 'text-muted hover:bg-surface-2')}
              onClick={() => { onChange(''); setOpen(false); }}
            >-- 선택 --</div>
            {filtered.length === 0 && (
              <div className="px-2.5 py-3 text-sm text-muted text-center">검색 결과 없음</div>
            )}
            {filtered.map(o => {
              const isActive = o.value === String(value ?? '');
              return (
                <div
                  key={o.value}
                  data-active={isActive}
                  className={'px-2.5 py-1.5 text-sm cursor-pointer transition-colors ' + (isActive ? 'bg-accent/10 text-link font-semibold' : 'text-primary hover:bg-surface-2')}
                  onClick={() => { onChange(o.value, o.text ?? o.value); setOpen(false); }}
                >{o.text ?? o.value}</div>
              );
            })}
          </div>
        </div>,
        document.body
      )}
    </>
  );
}
