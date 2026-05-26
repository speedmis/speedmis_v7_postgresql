import React, { useState, useEffect, useCallback, useRef } from 'react';
import api from '../api';
import DataForm from './DataForm';

const PAGE_SIZE = 25;

/** 마스터-디테일용 자식 그리드
 *  - parentIdx: 부모 레코드 idx (두 번째 필드 필터 자동 적용)
 *  - childGubun: 자식 프로그램 메뉴 idx
 */
export default function ChildDataGrid({ childGubun, parentIdx, user }) {
  const [rows,    setRows]    = useState([]);
  const [fields,  setFields]  = useState([]);
  const [total,   setTotal]   = useState(0);
  const [page,    setPage]    = useState(1);
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');

  // 인라인 폼 상태
  const [formMode, setFormMode] = useState(null); // null | 'write' | 'modify'
  const [formIdx,  setFormIdx]  = useState(0);
  const [formKey,  setFormKey]  = useState(0);

  // 컨테이너 폭
  const containerRef = useRef(null);
  const [cw, setCw] = useState(600);
  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    setCw(el.offsetWidth);
    const obs = new ResizeObserver(entries => {
      for (const e of entries) setCw(e.contentRect.width);
    });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  const isNarrow = cw < 400;

  const load = useCallback(async (pg = 1) => {
    if (!childGubun || !parentIdx) return;
    setLoading(true);
    setError('');
    try {
      const data = await api.list(childGubun, {
        page: pg, pageSize: PAGE_SIZE, parent_idx: parentIdx,
      });
      setRows(data.data   ?? []);
      setFields(data.fields ?? []);
      setTotal(data.total ?? 0);
      setPage(pg);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [childGubun, parentIdx]);

  useEffect(() => { load(1); }, [load]);

  const openWrite = () => {
    setFormIdx(0);
    setFormMode('write');
    setFormKey(k => k + 1);
  };

  const openModify = (idx) => {
    setFormIdx(idx);
    setFormMode('modify');
    setFormKey(k => k + 1);
  };

  const handleSaved = () => {
    setFormMode(null);
    load(page);
  };

  const handleCancel = () => {
    setFormMode(null);
  };

  const handleDelete = async (idx) => {
    if (!window.confirm('삭제하시겠습니까?')) return;
    try {
      await api.delete(childGubun, idx);
      load(page);
    } catch (e) {
      alert(e.message);
    }
  };

  // 표시할 컬럼 (col_width >= 0 인 것만)
  const listFields = fields.filter(f => parseInt(f.col_width ?? '0', 10) > 0);
  // 가시 컬럼 수에 따라 최대 표시 (좁은 경우 일부 생략)
  const visFields = isNarrow ? listFields.slice(0, 3) : listFields;

  // PK alias
  const pkAlias = fields.find(f => f.alias_name === 'idx')?.alias_name ?? 'idx';

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <div ref={containerRef} className="flex flex-col h-full overflow-hidden">
      {/* 상단 툴바 */}
      <div className="flex items-center justify-between px-3 py-1.5 border-b border-solid border-border-base bg-surface-2 flex-shrink-0">
        <span className="text-sm text-secondary">전체 {total.toLocaleString()}건</span>
        <button
          className="h-btn-sm px-3 rounded bg-accent text-white text-sm border-0 cursor-pointer hover:bg-accent-hover transition-colors"
          onClick={openWrite}
        >+ 등록</button>
      </div>

      {/* 테이블 영역 */}
      <div className="flex-1 overflow-auto">
        {error && (
          <div className="px-4 py-3 text-danger text-sm">{error}</div>
        )}
        {loading ? (
          <div className="p-4">
            {Array.from({ length: 5 }, (_, i) => (
              <div key={i} className="skeleton h-3 rounded mb-2" />
            ))}
          </div>
        ) : (
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b border-border-base bg-surface-2">
                {visFields.map(f => (
                  <th
                    key={f.alias_name}
                    className="px-2 py-1 text-left text-xs font-bold text-secondary whitespace-nowrap"
                    style={{ minWidth: 60 }}
                  >
                    {colLabel(f)}
                  </th>
                ))}
                <th className="px-2 py-1 text-right text-xs font-bold text-secondary whitespace-nowrap w-16">관리</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={visFields.length + 1} className="px-3 py-6 text-center text-muted text-sm">
                    데이터가 없습니다.
                  </td>
                </tr>
              ) : rows.map((row, ri) => {
                const rowIdx = row[pkAlias] ?? row.idx ?? ri;
                return (
                  <tr
                    key={rowIdx}
                    className="border-b border-border-base hover:bg-surface-2 cursor-pointer"
                    onClick={() => openModify(rowIdx)}
                  >
                    {visFields.map(f => (
                      <td key={f.alias_name} className="px-2 h-row text-primary truncate max-w-[200px]">
                        {formatCell(row[f.alias_name], f.schema_type)}
                      </td>
                    ))}
                    <td className="px-2 h-row text-right" onClick={e => e.stopPropagation()}>
                      <button
                        className="text-xs px-2 py-0.5 rounded border border-danger text-danger hover:bg-danger-dim transition-colors cursor-pointer"
                        onClick={() => handleDelete(rowIdx)}
                      >삭제</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {/* 페이지네이션 */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-1 px-3 py-1.5 border-t border-border-base bg-surface flex-shrink-0">
          <PagerBtn label="◀" disabled={page <= 1}          onClick={() => load(page - 1)} />
          {isNarrow ? (
            <span className="text-sm text-secondary tabular-nums px-1">{page}/{totalPages}</span>
          ) : (
            Array.from({ length: Math.min(totalPages, 7) }, (_, i) => {
              const pg = Math.max(1, page - 3) + i;
              if (pg > totalPages) return null;
              return <PagerBtn key={pg} label={pg} active={pg === page} onClick={() => load(pg)} />;
            })
          )}
          <PagerBtn label="▶" disabled={page >= totalPages} onClick={() => load(page + 1)} />
        </div>
      )}

      {/* 인라인 폼 패널 */}
      {formMode && (
        <div className="border-t border-border-base bg-surface flex-shrink-0 flex flex-col" style={{ maxHeight: '60%' }}>
          <div className="flex items-center justify-between px-3 py-1.5 bg-surface-2 border-b border-border-base flex-shrink-0">
            <span className="text-sm font-semibold text-secondary">
              {formMode === 'write' ? '등록' : '수정'}
            </span>
            <button
              className="text-xs text-muted hover:text-primary cursor-pointer px-2 py-0.5"
              onClick={handleCancel}
            >✕ 닫기</button>
          </div>
          <div className="flex-1 overflow-auto p-3">
            <DataForm
              key={`child-${childGubun}-${formIdx}-${formKey}`}
              gubun={childGubun}
              idx={formIdx}
              mode={formMode}
              user={user}
              onSaved={handleSaved}
              onCancel={handleCancel}
            />
          </div>
        </div>
      )}
    </div>
  );
}

function colLabel(f) {
  const s = f.col_title ?? f.alias_name ?? '';
  const ci = s.indexOf(',');
  return ci === -1 ? s : s.slice(ci + 1) || s.slice(0, ci) || f.alias_name;
}

function formatCell(val, schemaType) {
  if (val === null || val === undefined || val === '') {
    return <span className="text-muted">-</span>;
  }
  const s = String(val);
  if ((schemaType === 'datetime' || schemaType === 'date') && s.length >= 10) {
    return <span className="tabular-nums">{s.slice(0, schemaType === 'date' ? 10 : 16)}</span>;
  }
  return s;
}

function PagerBtn({ label, active, disabled, onClick }) {
  return (
    <button
      className={[
        'min-w-[26px] h-btn-sm px-1.5 text-sm rounded border transition-colors',
        active
          ? 'bg-accent border-accent text-white font-semibold'
          : 'bg-surface border-border-base text-secondary hover:bg-surface-2 hover:text-primary',
        disabled ? 'opacity-40 cursor-default' : 'cursor-pointer',
      ].join(' ')}
      disabled={disabled}
      onClick={onClick}
    >{label}</button>
  );
}
