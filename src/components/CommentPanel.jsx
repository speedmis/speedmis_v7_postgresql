import React, { useState, useEffect, useCallback, useRef, lazy, Suspense } from 'react';
import api, { apiPath } from '../api';
import { showToast } from './Toast';

/* 네이버 뉴스 스타일 댓글 패널 (DataForm '댓글' 탭).
 * props: realPid, midx, user, onCountChange(topLevelCount)
 * - 원댓글 입력: HTML 에디터(Quill) / 답글·수정: textarea ('<' 로 시작하면 HTML 로 인식)
 * - 수정/삭제: 작성자·admin 만 ⋮ 클릭 시 노출
 * - 색은 디자인시스템 토큰(다크모드 호환)으로 매핑, 레이아웃은 네이버 댓글과 동일
 * 백엔드: act=commentList/commentWrite/commentUpdate/commentDelete/commentLike */

const ReactQuill = lazy(() =>
  Promise.all([import('react-quill'), import('react-quill/dist/quill.snow.css')]).then(([m]) => ({ default: m.default }))
);
const QUILL_MODULES = { toolbar: [['bold', 'italic', 'underline', 'strike'], [{ color: [] }, { background: [] }], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] };
const QUILL_FORMATS = ['bold', 'italic', 'underline', 'strike', 'color', 'background', 'list', 'bullet', 'link'];

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
// '<' 로 시작하면 HTML 그대로, 아니면 텍스트로 escape + 줄바꿈 보존
function bodyHtml(contents) {
  const c = String(contents ?? '');
  if (c.trimStart().startsWith('<')) return c;
  return escapeHtml(c).replace(/\n/g, '<br>');
}
function isBlankHtml(html) {
  return !String(html ?? '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '').trim();
}
function fmtDate(s) {
  const m = String(s ?? '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/);
  return m ? `${m[1]}.${m[2]}.${m[3]}. ${m[4]}` : (s || '');
}

// 작성자 사진: thumbnail.php, 없으면 사람 아이콘 placeholder (네이버식)
function Avatar({ id, size = 36 }) {
  const [ok, setOk] = useState(true);
  const src = id ? apiPath(`/tools/thumbnail.php?w=128&/uploadFiles/staffList/${encodeURIComponent(id)}.jpg`) : null;
  return (
    <div className="flex-shrink-0 rounded-full overflow-hidden bg-surface-2 flex items-center justify-center" style={{ width: size, height: size }}>
      {src && ok
        ? <img src={src} alt="" className="w-full h-full object-cover" onError={() => setOk(false)} />
        : <svg viewBox="0 0 24 24" fill="currentColor" className="text-muted" style={{ width: size * 0.62, height: size * 0.62 }}>
            <circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 4-6 8-6s8 2 8 6z" />
          </svg>}
    </div>
  );
}

// 공감/비공감 — 네이버 댓글 thumbs (디자인 SVG path)
function Reaction({ up, on, count, onClick }) {
  return (
    <span onClick={onClick}
          className={'inline-flex items-center gap-1.5 text-xs cursor-pointer select-none ' + (on ? (up ? 'text-link' : 'text-danger') : 'text-secondary hover:text-primary')}>
      <svg viewBox="0 0 24 24" width="15" height="15" fill={on ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.6">
        {up
          ? <path d="M7 11v9H4v-9h3zm3 0l1.5-6c.3-1 1.2-1.6 2.2-1.4 1 .2 1.6 1.2 1.4 2.2L18 11h2.5c1 0 1.7.9 1.5 1.9l-1.3 6c-.2.9-1 1.6-2 1.6H10V11z" />
          : <path d="M17 13V4h3v9h-3zm-3 0l-1.5 6c-.3 1-1.2 1.6-2.2 1.4-1-.2-1.6-1.2-1.4-2.2L6 13H3.5c-1 0-1.7-.9-1.5-1.9l1.3-6C3.5 4.2 4.3 3.5 5.3 3.5H14V13z" />}
      </svg>
      <span className={'tabular-nums ' + (on ? '' : 'text-primary')}>{count}</span>
    </span>
  );
}

// 입력 에디터 — isReply=false → Quill(HTML), true → textarea('<' HTML 규칙)
function Editor({ isReply, value, onChange, onSubmit, onCancel, saving, submitLabel = '등록', autoFocus }) {
  const taRef = useRef(null);
  useEffect(() => { if (isReply && autoFocus && taRef.current) taRef.current.focus(); }, [isReply, autoFocus]);
  const blank = isReply ? !String(value || '').trim() : isBlankHtml(value);
  return (
    <div className="border border-border-base rounded-md bg-surface overflow-hidden focus-within:border-accent transition-colors">
      {isReply ? (
        <textarea
          ref={taRef}
          value={value}
          onChange={e => onChange(e.target.value)}
          placeholder="건전한 댓글문화 정착을 위해 이용에 주의를 부탁드립니다."
          className="w-full h-20 resize-none bg-transparent text-sm text-primary outline-none placeholder:text-muted px-3.5 py-3"
        />
      ) : (
        <Suspense fallback={<div className="h-28 flex items-center justify-center text-muted text-sm">에디터 로딩 중…</div>}>
          <div className="comment-quill">
            <ReactQuill theme="snow" value={value || ''} onChange={onChange} modules={QUILL_MODULES} formats={QUILL_FORMATS} placeholder="댓글을 입력해주세요" />
          </div>
        </Suspense>
      )}
      <div className="flex items-center justify-between px-3.5 py-2 border-t border-border-base bg-surface-2">
        <span className="text-xs text-muted truncate">{isReply ? '< 로 시작하면 HTML 로 인식됩니다.' : 'HTML 에디터'}</span>
        <div className="flex items-center gap-2 flex-shrink-0">
          {onCancel && (
            <button onClick={onCancel} className="h-8 px-3.5 rounded-lg text-sm font-medium text-secondary hover:bg-surface-3 hover:text-primary transition-colors">취소</button>
          )}
          <button disabled={saving || blank} onClick={onSubmit}
                  className="h-8 px-4 rounded-lg text-sm font-semibold bg-accent text-white hover:bg-accent-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            {saving ? '처리 중…' : submitLabel}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function CommentPanel({ realPid, midx, user, onCountChange }) {
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [sort, setSort] = useState('new');
  const [text, setText] = useState('');
  const [saving, setSaving] = useState(false);
  const [replyOpen, setReplyOpen] = useState(null);
  const [replyText, setReplyText] = useState('');
  const [menuOpen, setMenuOpen] = useState(null);   // ⋮ 클릭으로 수정/삭제 노출된 댓글 idx
  const [editId, setEditId] = useState(null);
  const [editText, setEditText] = useState('');

  const isAdmin = user?.is_admin === 'Y';
  const canWrite = !!(realPid && midx);

  const onCountChangeRef = useRef(onCountChange);
  onCountChangeRef.current = onCountChange;

  const load = useCallback(async () => {
    if (!realPid || !midx) { setComments([]); setLoading(false); onCountChangeRef.current?.(0); return; }
    setLoading(true);
    try {
      const r = await api.commentList(realPid, midx);
      const list = r.comments ?? [];
      setComments(list);
      onCountChangeRef.current?.(list.length);
    } catch (e) {
      showToast(e?.message || '댓글을 불러오지 못했습니다.');
    } finally { setLoading(false); }
  }, [realPid, midx]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (menuOpen == null) return;
    const h = () => setMenuOpen(null);
    window.addEventListener('click', h);
    return () => window.removeEventListener('click', h);
  }, [menuOpen]);

  const submitComment = async () => {
    if (isBlankHtml(text)) { showToast('내용을 입력하세요.'); return; }
    setSaving(true);
    try { await api.commentWrite(realPid, midx, text, 0); setText(''); await load(); }
    catch (e) { showToast(e?.message || '저장에 실패했습니다.'); }
    finally { setSaving(false); }
  };

  const submitReply = async (refidx) => {
    if (!String(replyText || '').trim()) { showToast('내용을 입력하세요.'); return; }
    setSaving(true);
    try { await api.commentWrite(realPid, midx, replyText, refidx); setReplyText(''); setReplyOpen(null); await load(); }
    catch (e) { showToast(e?.message || '저장에 실패했습니다.'); }
    finally { setSaving(false); }
  };

  const submitEdit = async (idx) => {
    setSaving(true);
    try { await api.commentUpdate(idx, editText); setEditId(null); setEditText(''); await load(); showToast('수정되었습니다.'); }
    catch (e) { showToast(e?.message || '수정에 실패했습니다.'); }
    finally { setSaving(false); }
  };

  const del = async (idx) => {
    setMenuOpen(null);
    if (!window.confirm('해당 댓글을 삭제할까요?')) return;
    try { await api.commentDelete(idx); await load(); showToast('삭제되었습니다.'); }
    catch (e) { showToast(e?.message || '삭제에 실패했습니다.'); }
  };

  const like = async (idx, LH) => {
    try {
      const r = await api.commentLike(idx, LH);
      if (r?.msg) showToast(r.msg);
      setComments(prev => applyLike(prev, idx, r));
    } catch (e) { showToast(e?.message || '처리에 실패했습니다.'); }
  };

  const startEdit = (c) => { setMenuOpen(null); setEditId(c.idx); setEditText(c.contents || ''); };

  const renderComment = (c, isReply, topIdx = 0) => {
    const editable = c.isMine || isAdmin;
    const editing = editId === c.idx;
    // 원댓글: 홀짝 배경(zebra) — 짝수번째 행만 살짝 다른 배경. -mx-5 px-5 로 패널 폭 전체 줄무늬.
    const topCls = 'flex gap-3 py-5 px-5 -mx-5 border-b border-border-light' + (topIdx % 2 === 1 ? ' bg-surface-2' : '');
    return (
      <div key={c.idx} className={isReply ? 'flex gap-3 mt-4' : topCls}>
        <Avatar id={c.wdater} size={isReply ? 30 : 36} />
        <div className="flex-1 min-w-0">
          {/* 헤드: 닉네임 · 화살표 · 날짜 · (우측) ⋮ + 수정/삭제 */}
          <div className="flex items-center mb-2">
            <span className="text-[13px] font-bold text-primary mr-1 truncate">{c.author_name}</span>
            <span className="inline-flex items-center justify-center w-4 h-4 rounded-full border border-border-base text-[9px] text-muted mr-2 flex-shrink-0">›</span>
            <span className="text-xs text-muted whitespace-nowrap">{fmtDate(c.wdate)}</span>
            {editable && !editing && (
              <div className="ml-auto flex items-center gap-2 flex-shrink-0">
                <span onClick={e => { e.stopPropagation(); setMenuOpen(menuOpen === c.idx ? null : c.idx); }}
                      className="text-muted hover:text-primary cursor-pointer leading-none px-0.5" title="더보기"
                      style={{ fontSize: '16px', letterSpacing: '1px' }}>⋮</span>
                {menuOpen === c.idx && (
                  <div className="flex gap-1.5" onClick={e => e.stopPropagation()}>
                    <button onClick={() => startEdit(c)} className="border border-border-base bg-surface rounded px-3 py-1 text-xs text-secondary hover:bg-surface-2 transition-colors">수정</button>
                    <button onClick={() => del(c.idx)} className="border border-border-base bg-surface rounded px-3 py-1 text-xs text-secondary hover:bg-surface-2 transition-colors">삭제</button>
                  </div>
                )}
              </div>
            )}
          </div>

          {editing ? (
            <Editor isReply={isReply} value={editText} onChange={setEditText} saving={saving} submitLabel="저장" autoFocus
                    onSubmit={() => submitEdit(c.idx)} onCancel={() => { setEditId(null); setEditText(''); }} />
          ) : (
            <div className="comment-body text-sm leading-[1.55] text-primary mb-2.5 break-words" dangerouslySetInnerHTML={{ __html: bodyHtml(c.contents) }} />
          )}

          {!editing && (
            <div className="flex items-center">
              {!isReply ? (
                <span onClick={() => { setReplyOpen(replyOpen === c.idx ? null : c.idx); setReplyText(''); }}
                      className="text-xs text-secondary hover:text-primary cursor-pointer">답글 {c.replies?.length || 0}</span>
              ) : <span />}
              <div className="ml-auto flex items-center gap-3.5">
                <Reaction up on={c.my_lh === 'L'} count={c.sel_like} onClick={() => like(c.idx, 'L')} />
                <Reaction up={false} on={c.my_lh === 'H'} count={c.sel_hate} onClick={() => like(c.idx, 'H')} />
              </div>
            </div>
          )}

          {!isReply && c.replies?.map(rep => renderComment(rep, true))}

          {!isReply && replyOpen === c.idx && (
            <div className="mt-3">
              <Editor isReply value={replyText} onChange={setReplyText} saving={saving} autoFocus
                      onSubmit={() => submitReply(c.idx)} onCancel={() => { setReplyOpen(null); setReplyText(''); }} />
            </div>
          )}
        </div>
      </div>
    );
  };

  const sorted = sort === 'new' ? comments : [...comments].sort((a, b) => a.idx - b.idx);

  return (
    <div className="p-5 max-w-[680px] mx-auto">
      <style>{`
        .comment-quill .ql-toolbar{border:0;border-bottom:1px solid var(--color-border);padding:5px 8px}
        .comment-quill .ql-container{border:0;font-family:inherit;font-size:14px}
        .comment-quill .ql-editor{min-height:84px;max-height:240px;padding:8px 12px;color:var(--color-text-1)}
        .comment-quill .ql-editor.ql-blank::before{color:var(--color-text-3);font-style:normal;left:12px}
        .comment-body p{margin:0}
        .comment-body img{max-width:100%}
        .comment-body a{color:var(--color-primary);text-decoration:underline}
      `}</style>

      {/* 작성 박스 (HTML 에디터) */}
      {canWrite ? (
        <Editor isReply={false} value={text} onChange={setText} saving={saving} onSubmit={submitComment} />
      ) : (
        <div className="border border-border-base rounded-md bg-surface-2 px-4 py-4 text-sm text-muted">저장된 레코드에만 댓글을 달 수 있습니다.</div>
      )}

      {/* 정렬 */}
      <div className="flex items-center gap-3.5 border-b border-border-base pb-3 mt-6 mb-1">
        <button onClick={() => setSort('new')} className={'text-sm ' + (sort === 'new' ? 'font-bold text-primary' : 'text-muted hover:text-secondary')}>최신순</button>
        <button onClick={() => setSort('old')} className={'text-sm ' + (sort === 'old' ? 'font-bold text-primary' : 'text-muted hover:text-secondary')}>과거순</button>
      </div>

      {loading ? (
        <div className="py-10 text-center text-sm text-muted">불러오는 중…</div>
      ) : sorted.length === 0 ? (
        <div className="py-10 text-center text-sm text-muted">첫 댓글을 남겨보세요.</div>
      ) : (
        <div>{sorted.map((c, i) => renderComment(c, false, i))}</div>
      )}
    </div>
  );
}

// like 결과 inline 반영 (재조회 없이)
function applyLike(list, idx, r) {
  return list.map(c => {
    let nc = c;
    if (c.idx === idx) nc = { ...c, sel_like: r.cntL, sel_hate: r.cntH, my_lh: r.resultLH };
    if (nc.replies && nc.replies.length) {
      const reps = applyLike(nc.replies, idx, r);
      if (reps !== nc.replies) nc = { ...nc, replies: reps };
    }
    return nc;
  });
}
