import React, { useState, useEffect, useRef, useCallback } from 'react';
import { showToast } from './Toast';
import { apiPath } from '../api';

const DAY_W  = 36;
const ROW_H  = 40;
const LEFT_W = 300;
const COLORS = ['#3182F6','#22C55E','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#F97316'];
const PROGRESS_COLORS = { done: '#22C55E', wip: '#3182F6', todo: '#B0B8C1' };

function daysBetween(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000); }
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r.toISOString().slice(0, 10); }
function fmtDate(d) { return d ? d.slice(5).replace('-', '/') : ''; }
function fmtFull(d) { return d ? d.slice(2).replace(/-/g, '.') : ''; }

function fetchJson(url, opts = {}) {
  const csrf = document.cookie.match(/csrf_token=([^;]+)/)?.[1] ?? '';
  return fetch(url, { credentials: 'include', ...opts, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(opts.headers || {}) } }).then(r => r.json());
}

export default function GanttChart({ gubun, menu, projectIdx = 0 }) {
  const [tasks, setTasks] = useState([]);
  const [viewStart, setViewStart] = useState('');
  const [viewDays, setViewDays] = useState(75);
  const [editTask, setEditTask] = useState(null);
  const [drag, setDrag] = useState(null);
  const scrollRef = useRef(null);

  const load = useCallback(() => {
    fetchJson(apiPath(`/api.php?act=ganttList&project_idx=${projectIdx}`)).then(d => {
      if (!d.success) return;
      const rows = d.data ?? [];
      setTasks(rows);
      if (rows.length > 0 && !viewStart) {
        const dates = rows.filter(r => r.start_date).map(r => r.start_date).sort();
        setViewStart(addDays(dates[0] || new Date().toISOString().slice(0, 10), -5));
      }
    });
  }, [projectIdx]);

  useEffect(() => { load(); }, [load]);

  const today = new Date().toISOString().slice(0, 10);
  const vs = viewStart || addDays(today, -10);
  const totalW = viewDays * DAY_W;
  const dates = Array.from({ length: viewDays }, (_, i) => addDays(vs, i));

  // 월 그룹
  const months = {};
  dates.forEach((d, i) => {
    const m = d.slice(0, 7);
    if (!months[m]) months[m] = { start: i, label: parseInt(d.slice(5, 7)) + '월' };
    months[m].end = i;
  });

  function barStyle(task) {
    if (!task.start_date || !task.end_date) return null;
    const left = daysBetween(vs, task.start_date) * DAY_W;
    const width = Math.max(DAY_W, (daysBetween(task.start_date, task.end_date) + 1) * DAY_W);
    const color = task.color || COLORS[(parseInt(task.idx) || 0) % COLORS.length];
    return { left, width, color };
  }

  // 드래그
  const handleBarMouseDown = (e, task, mode) => {
    e.preventDefault(); e.stopPropagation();
    setDrag({ task, mode, startX: e.clientX, origStart: task.start_date, origEnd: task.end_date });
  };

  useEffect(() => {
    if (!drag) return;
    const move = (e) => {
      const dx = Math.round((e.clientX - drag.startX) / DAY_W);
      if (!dx) return;
      setTasks(prev => prev.map(t => {
        if (t.idx !== drag.task.idx) return t;
        if (drag.mode === 'move') return { ...t, start_date: addDays(drag.origStart, dx), end_date: addDays(drag.origEnd, dx) };
        if (drag.mode === 'end') { const ne = addDays(drag.origEnd, dx); return ne >= t.start_date ? { ...t, end_date: ne } : t; }
        if (drag.mode === 'start') { const ns = addDays(drag.origStart, dx); return ns <= t.end_date ? { ...t, start_date: ns } : t; }
        return t;
      }));
    };
    const up = () => {
      const u = tasks.find(t => t.idx === drag.task.idx);
      if (u && (u.start_date !== drag.origStart || u.end_date !== drag.origEnd)) {
        fetchJson(apiPath('/api.php?act=ganttSave'), { method: 'POST', body: JSON.stringify({ idx: u.idx, start_date: u.start_date, end_date: u.end_date }) });
      }
      setDrag(null);
    };
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
    return () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
  }, [drag, tasks]);

  const saveTask = async (data) => {
    const d = await fetchJson(apiPath('/api.php?act=ganttSave'), { method: 'POST', body: JSON.stringify({ ...data, project_idx: projectIdx }) });
    if (d.success) { load(); setEditTask(null); showToast('저장 완료'); }
  };
  const deleteTask = async (idx) => {
    if (!confirm('삭제?')) return;
    await fetchJson(apiPath(`/api.php?act=ganttDelete&idx=${idx}`), { method: 'POST' });
    load(); setEditTask(null);
  };

  const todayX = daysBetween(vs, today) * DAY_W;

  // 진행률 통계
  const totalTasks = tasks.length;
  const doneTasks = tasks.filter(t => parseInt(t.progress) >= 100).length;
  const avgProgress = totalTasks > 0 ? Math.round(tasks.reduce((s, t) => s + (parseInt(t.progress) || 0), 0) / totalTasks) : 0;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%', overflow: 'hidden', background: 'var(--color-surface)' }}>
      {/* 툴바 */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 12px', borderBottom: '1px solid var(--color-border)', flexShrink: 0, background: 'var(--color-surface-2)' }}>
        <button className="h-btn-sm px-3 rounded bg-accent text-white text-xs border-0 cursor-pointer hover:bg-accent-hover transition-colors font-semibold"
          onClick={() => setEditTask({ idx: 0, task_name: '', start_date: today, end_date: addDays(today, 7), progress: 0, assignee: '', project_idx: projectIdx })}>
          + 작업추가
        </button>
        <span style={{ width: 1, height: 20, background: 'var(--color-border)' }} />
        <button className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2" onClick={() => setViewStart(addDays(vs, -14))}>◀</button>
        <button className="h-btn-sm px-3 rounded border border-border-base bg-surface text-link text-xs font-semibold cursor-pointer hover:bg-surface-2" onClick={() => { setViewStart(addDays(today, -10)); setViewDays(75); }}>오늘</button>
        <button className="h-btn-sm px-2 rounded border border-border-base bg-surface text-secondary text-xs cursor-pointer hover:bg-surface-2" onClick={() => setViewStart(addDays(vs, 14))}>▶</button>
        <span style={{ width: 1, height: 20, background: 'var(--color-border)' }} />
        <span style={{ fontSize: 12, color: 'var(--color-text-2)' }}>{totalTasks}개 작업</span>
        <span style={{ fontSize: 12, color: 'var(--color-text-3)' }}>·</span>
        <span style={{ fontSize: 12, color: PROGRESS_COLORS.done, fontWeight: 600 }}>{doneTasks}완료</span>
        <span style={{ fontSize: 12, color: 'var(--color-text-3)' }}>·</span>
        <span style={{ fontSize: 12, color: PROGRESS_COLORS.wip, fontWeight: 600 }}>평균 {avgProgress}%</span>
      </div>

      {/* 메인 */}
      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        {/* 좌측 패널 */}
        <div style={{ width: LEFT_W, flexShrink: 0, borderRight: '1px solid var(--color-border)', overflowY: 'auto' }}>
          <div style={{ display: 'flex', alignItems: 'center', height: 48, padding: '0 12px', borderBottom: '1px solid var(--color-border)', background: 'var(--color-surface-2)', position: 'sticky', top: 0, zIndex: 10 }}>
            <span style={{ flex: 1, fontSize: 12, fontWeight: 700, color: 'var(--color-text-2)' }}>작업명</span>
            <span style={{ width: 56, textAlign: 'center', fontSize: 11, fontWeight: 700, color: 'var(--color-text-3)' }}>기간</span>
            <span style={{ width: 44, textAlign: 'center', fontSize: 11, fontWeight: 700, color: 'var(--color-text-3)' }}>진행</span>
          </div>
          {tasks.map(task => {
            const prog = parseInt(task.progress) || 0;
            const pc = prog >= 100 ? PROGRESS_COLORS.done : prog > 0 ? PROGRESS_COLORS.wip : PROGRESS_COLORS.todo;
            const days = task.start_date && task.end_date ? daysBetween(task.start_date, task.end_date) + 1 : 0;
            return (
              <div key={task.idx} onClick={() => setEditTask({ ...task })}
                style={{ display: 'flex', alignItems: 'center', height: ROW_H, padding: '0 12px', borderBottom: '1px solid var(--color-border-light)', cursor: 'pointer', transition: 'background 0.1s' }}
                className="hover:bg-surface-2">
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text-1)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{task.task_name}</div>
                  <div style={{ fontSize: 10, color: 'var(--color-text-3)', marginTop: 1 }}>{fmtFull(task.start_date)} ~ {fmtFull(task.end_date)}</div>
                </div>
                <span style={{ width: 56, textAlign: 'center', fontSize: 11, color: 'var(--color-text-3)' }}>{days}일</span>
                <div style={{ width: 44, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2 }}>
                  <span style={{ fontSize: 11, fontWeight: 700, color: pc }}>{prog}%</span>
                  <div style={{ width: 32, height: 3, borderRadius: 2, background: 'var(--color-border-light)' }}>
                    <div style={{ width: `${prog}%`, height: '100%', borderRadius: 2, background: pc }} />
                  </div>
                </div>
              </div>
            );
          })}
          {tasks.length === 0 && (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--color-text-3)', fontSize: 13 }}>작업이 없습니다. + 작업추가 버튼을 눌러주세요.</div>
          )}
        </div>

        {/* 우측 타임라인 */}
        <div ref={scrollRef} style={{ flex: 1, overflow: 'auto' }}>
          <div style={{ width: totalW, minHeight: '100%', position: 'relative' }}>
            {/* 월 헤더 */}
            <div style={{ display: 'flex', height: 24, background: 'var(--color-surface-2)', position: 'sticky', top: 0, zIndex: 10 }}>
              {Object.values(months).map(m => (
                <div key={m.label} style={{ position: 'absolute', left: m.start * DAY_W, width: (m.end - m.start + 1) * DAY_W, textAlign: 'center', fontSize: 12, fontWeight: 700, color: 'var(--color-text-2)', lineHeight: '24px', borderRight: '1px solid var(--color-border)' }}>{m.label}</div>
              ))}
            </div>
            {/* 일 헤더 */}
            <div style={{ display: 'flex', height: 24, background: 'var(--color-surface-2)', position: 'sticky', top: 24, zIndex: 10 }}>
              {dates.map((d, i) => {
                const dow = new Date(d).getDay();
                const isWe = dow === 0 || dow === 6;
                const isT = d === today;
                return (
                  <div key={d} style={{
                    position: 'absolute', left: i * DAY_W, width: DAY_W, textAlign: 'center',
                    fontSize: 11, lineHeight: '24px', borderRight: '1px solid var(--color-border-light)',
                    fontWeight: isT ? 800 : 400, color: isT ? '#fff' : isWe ? 'var(--color-danger)' : 'var(--color-text-3)',
                    background: isT ? 'var(--color-primary)' : 'transparent', borderRadius: isT ? 0 : 0,
                  }}>{parseInt(d.slice(8), 10)}</div>
                );
              })}
            </div>

            {/* 바 영역 */}
            <div style={{ paddingTop: 48, position: 'relative' }}>
              {/* 배경 */}
              {dates.map((d, i) => {
                const isWe = [0, 6].includes(new Date(d).getDay());
                return <div key={d} style={{ position: 'absolute', left: i * DAY_W, top: 0, bottom: 0, width: DAY_W, borderRight: '1px solid var(--color-border-light)', background: isWe ? 'rgba(0,0,0,0.02)' : 'transparent' }} />;
              })}
              {/* 오늘 */}
              {todayX > 0 && todayX < totalW && (
                <div style={{ position: 'absolute', left: todayX + DAY_W / 2, top: 0, bottom: 0, width: 2, background: '#EF4444', zIndex: 5, opacity: 0.5, borderRadius: 1 }} />
              )}
              {/* 바 */}
              {tasks.map(task => {
                const bs = barStyle(task);
                if (!bs) return <div key={task.idx} style={{ height: ROW_H }} />;
                const prog = Math.min(100, Math.max(0, parseInt(task.progress) || 0));
                const pc = prog >= 100 ? PROGRESS_COLORS.done : prog > 0 ? PROGRESS_COLORS.wip : PROGRESS_COLORS.todo;
                return (
                  <div key={task.idx} style={{ height: ROW_H, position: 'relative' }}>
                    <div style={{
                      position: 'absolute', top: 6, height: ROW_H - 12, left: bs.left, width: bs.width,
                      borderRadius: 8, overflow: 'hidden', border: `2px solid ${bs.color}`, background: `${bs.color}15`,
                      cursor: drag ? 'grabbing' : 'grab', zIndex: 2, display: 'flex', alignItems: 'center',
                      boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
                    }} onMouseDown={e => handleBarMouseDown(e, task, 'move')}>
                      <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: `${prog}%`, background: bs.color, opacity: 0.2 }} />
                      <span style={{ position: 'relative', zIndex: 1, paddingLeft: 8, fontSize: 11, fontWeight: 700, color: bs.color, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {task.task_name} {prog > 0 && <span style={{ fontWeight: 400, opacity: 0.7 }}>{prog}%</span>}
                      </span>
                      <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: 8, cursor: 'ew-resize' }} onMouseDown={e => handleBarMouseDown(e, task, 'start')} />
                      <div style={{ position: 'absolute', right: 0, top: 0, bottom: 0, width: 8, cursor: 'ew-resize' }} onMouseDown={e => handleBarMouseDown(e, task, 'end')} />
                    </div>
                  </div>
                );
              })}
              {/* 하단 여백 */}
              <div style={{ height: 200 }} />
            </div>
          </div>
        </div>
      </div>

      {/* 편집 모달 */}
      {editTask && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center modal-overlay" onClick={() => setEditTask(null)}>
          <div className="bg-surface rounded-lg shadow-pop flex flex-col overflow-hidden modal-box" style={{ width: 'min(500px, 92vw)' }} onClick={e => e.stopPropagation()}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 20px', borderBottom: '1px solid var(--color-border-light)', background: 'var(--color-surface-2)' }}>
              <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text-1)' }}>{editTask.idx ? '작업 수정' : '작업 추가'}</span>
              <button style={{ background: 'none', border: 'none', fontSize: 18, cursor: 'pointer', color: 'var(--color-text-3)' }} onClick={() => setEditTask(null)}>✕</button>
            </div>
            <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
              <Field label="작업명" value={editTask.task_name} onChange={v => setEditTask(p => ({ ...p, task_name: v }))} />
              <div style={{ display: 'flex', gap: 12 }}>
                <Field label="시작일" type="date" value={editTask.start_date ?? ''} onChange={v => setEditTask(p => ({ ...p, start_date: v }))} />
                <Field label="종료일" type="date" value={editTask.end_date ?? ''} onChange={v => setEditTask(p => ({ ...p, end_date: v }))} />
              </div>
              <div style={{ display: 'flex', gap: 12 }}>
                <Field label="진행률 (%)" type="number" value={editTask.progress ?? 0} onChange={v => setEditTask(p => ({ ...p, progress: parseInt(v) || 0 }))} />
                <Field label="담당자" value={editTask.assignee ?? ''} onChange={v => setEditTask(p => ({ ...p, assignee: v }))} />
              </div>
              <Field label="메모" value={editTask.remark ?? ''} onChange={v => setEditTask(p => ({ ...p, remark: v }))} textarea />
            </div>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 20px', borderTop: '1px solid var(--color-border-light)' }}>
              {editTask.idx > 0 ? <button className="h-btn px-4 rounded border border-danger text-danger text-sm cursor-pointer hover:bg-danger-dim" onClick={() => deleteTask(editTask.idx)}>삭제</button> : <span />}
              <div style={{ display: 'flex', gap: 8 }}>
                <button className="h-btn px-4 rounded border border-border-base bg-surface text-secondary text-sm cursor-pointer hover:bg-surface-2" onClick={() => setEditTask(null)}>취소</button>
                <button className="h-btn px-6 rounded bg-accent text-white text-sm border-0 cursor-pointer hover:bg-accent-hover font-semibold" onClick={() => saveTask(editTask)}>저장</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Field({ label, value, onChange, type = 'text', textarea }) {
  const style = { width: '100%', height: textarea ? 'auto' : 36, padding: textarea ? '8px 12px' : '0 12px', borderRadius: 8, border: '1px solid var(--color-border)', background: 'var(--color-surface-2)', color: 'var(--color-text-1)', fontSize: 14, outline: 'none' };
  return (
    <label style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 4 }}>
      <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text-2)' }}>{label}</span>
      {textarea
        ? <textarea style={style} rows={2} value={value} onChange={e => onChange(e.target.value)} />
        : <input type={type} style={style} value={value} onChange={e => onChange(e.target.value)} min={type === 'number' ? 0 : undefined} max={type === 'number' ? 100 : undefined} />
      }
    </label>
  );
}
