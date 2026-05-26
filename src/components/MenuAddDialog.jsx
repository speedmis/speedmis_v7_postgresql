import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import api from '../api';
import { showToast } from './Toast';

const MENU_TYPES = [
  { value: '00', label: '메뉴표시용' },
  { value: '01', label: '업무용MIS' },
  { value: '06', label: 'MIS Join' },
  { value: '11', label: '직접링크-현재창' },
  { value: '12', label: '팝업링크-새창' },
  { value: '13', label: '직접링크-iframe' },
  { value: '22', label: '서버로직만' },
];

const POSITIONS = [
  { value: 1, label: '같은 레벨로 맨 위에 추가' },
  { value: 2, label: '같은 레벨로 바로 위에 추가' },
  { value: 3, label: '같은 레벨로 바로 아래에 추가' },
  { value: 4, label: '같은 레벨로 맨 아래에 추가' },
  { value: 5, label: '하위 레벨로 맨 위에 추가' },
  { value: 6, label: '하위 레벨로 맨 아래에 추가' },
];

export default function MenuAddDialog({ srcIdx, onClose, onCreated }) {
  const [pathInfo,  setPathInfo]  = useState(null);
  const [loading,   setLoading]   = useState(true);
  const [saving,    setSaving]    = useState(false);
  const [error,     setError]     = useState('');

  // 폼 상태
  const [position, setPosition] = useState(4);
  const [menuName, setMenuName] = useState('');
  const [menuType, setMenuType] = useState('01');
  const [addUrl,   setAddUrl]   = useState('');
  const [joinPid,  setJoinPid]  = useState('');

  // 업무용MIS 서브옵션
  const [subOption, setSubOption] = useState('clone'); // clone | table | excel | gsheet
  const [sourceList,    setSourceList]    = useState([]);
  const [sourceRealPid, setSourceRealPid] = useState('');
  const [sourceQuery,   setSourceQuery]   = useState('');
  // table 모드 입력
  const [tableName, setTableName] = useState('');
  const [dbAlias,   setDbAlias]   = useState('');

  useEffect(() => {
    api.menuPathInfo(srcIdx)
      .then(r => {
        if (r.success) {
          setPathInfo(r.data);
          setMenuName(`${r.data.menu_name} 사본`);
        } else {
          setError(r.message || '정보 로드 실패');
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [srcIdx]);

  // 업무용MIS 선택 시 소스 목록 로드
  useEffect(() => {
    if (menuType !== '01') return;
    api.menuSourceList(sourceQuery).then(r => {
      if (r.success) setSourceList(r.data || []);
    });
  }, [menuType, sourceQuery]);

  async function handleSubmit() {
    if (!menuName.trim()) { alert('메뉴명을 입력하세요.'); return; }
    if (menuType === '01') {
      if (subOption === 'clone' && !sourceRealPid) {
        alert('복제할 원본 프로그램을 선택하세요.');
        return;
      }
      if (subOption === 'table' && !tableName.trim()) {
        alert('테이블 또는 뷰 이름을 입력하세요.');
        return;
      }
      if (subOption !== 'clone' && subOption !== 'table') {
        alert('해당 옵션은 추후 지원 예정입니다.');
        return;
      }
    }
    if (menuType === '06' && !joinPid.trim()) {
      alert('MIS Join 대상의 real_pid 를 입력하세요.');
      return;
    }
    setSaving(true);
    try {
      const res = await api.menuCreate({
        srcIdx,
        position,
        menuName: menuName.trim(),
        menuType,
        addUrl: addUrl.trim(),
        misJoinPid: joinPid.trim(),
        sourceRealPid: menuType === '01' && subOption === 'clone' ? sourceRealPid : '',
        subOption: menuType === '01' ? subOption : '',
        tableName: menuType === '01' && subOption === 'table' ? tableName.trim() : '',
        dbAlias:   menuType === '01' && subOption === 'table' ? dbAlias.trim()   : '',
      });
      if (res.success) {
        showToast(res.message || '생성 완료');
        onCreated?.(res);
        onClose?.();
      } else {
        alert(res.message || '생성 실패');
      }
    } catch (e) {
      alert(e.message || '요청 실패');
    } finally {
      setSaving(false);
    }
  }

  const labelCls = 'block text-xs font-bold text-secondary mb-1';
  const inputCls = 'w-full h-input px-2 rounded border border-border-base bg-surface text-sm text-primary outline-none focus:border-accent';

  return createPortal(
    <div className="fixed inset-0 z-[300] flex items-center justify-center pointer-events-none">
      <div
        className="bg-surface rounded-lg border border-border-base shadow-pop flex flex-col overflow-hidden pointer-events-auto"
        style={{ width: 'min(720px, 94vw)', maxHeight: '90vh' }}
        onClick={e => e.stopPropagation()}
      >
        {/* 헤더 */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border-base bg-surface-2 flex-shrink-0">
          <span className="text-sm font-bold text-primary">새 메뉴/프로그램 추가</span>
          <button
            type="button"
            className="h-btn-sm px-3 text-xs rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer"
            onClick={onClose}
          >✕ 닫기</button>
        </div>

        {/* 본문 */}
        <div className="flex-1 overflow-auto p-4 flex flex-col gap-3">
          {loading ? (
            <div className="text-center text-sm text-secondary py-10">로딩 중…</div>
          ) : error ? (
            <div className="text-sm text-danger bg-danger-dim rounded p-3">{error}</div>
          ) : (
            <>
              <div>
                <label className={labelCls}>선택한 경로</label>
                <div className="px-2 py-1.5 rounded bg-surface-2 text-sm text-primary">{pathInfo?.path || '-'}</div>
              </div>

              <div>
                <label className={labelCls}>선택한 경로 기준의 추가 위치</label>
                <div className="grid grid-cols-2 gap-1 mt-1">
                  {POSITIONS.map(p => (
                    <label key={p.value} className="flex items-center gap-2 text-sm text-primary cursor-pointer py-0.5">
                      <input
                        type="radio"
                        name="position"
                        checked={position === p.value}
                        onChange={() => setPosition(p.value)}
                      />
                      {p.label}
                    </label>
                  ))}
                </div>
              </div>

              <div>
                <label className={labelCls}>추가할 메뉴명</label>
                <input className={inputCls} value={menuName} onChange={e => setMenuName(e.target.value)} />
              </div>

              <div>
                <label className={labelCls}>생성할 메뉴 또는 프로그램 형태</label>
                <div className="grid grid-cols-2 gap-1 mt-1">
                  {MENU_TYPES.map(t => (
                    <label key={t.value} className="flex items-center gap-2 text-sm text-primary cursor-pointer py-0.5">
                      <input
                        type="radio"
                        name="menuType"
                        checked={menuType === t.value}
                        onChange={() => setMenuType(t.value)}
                      />
                      {t.label}
                    </label>
                  ))}
                </div>
              </div>

              <div>
                <label className={labelCls}>추가쿼리스트링 (선택) — 예: &psize=5</label>
                <input className={inputCls} value={addUrl} onChange={e => setAddUrl(e.target.value)} placeholder="" />
              </div>

              {/* MIS Join 서브필드 */}
              {menuType === '06' && (
                <div>
                  <label className={labelCls}>MIS Join 대상 real_pid</label>
                  <input className={inputCls} value={joinPid} onChange={e => setJoinPid(e.target.value)} placeholder="예: speedmis000123" />
                </div>
              )}

              {/* 업무용MIS 서브옵션 */}
              {menuType === '01' && (
                <div className="border border-border-light rounded p-3 bg-surface-2">
                  <label className={labelCls}>업무용MIS — 데이터 구성 방식 (1가지 선택)</label>
                  <div className="flex flex-col gap-2 mt-1">
                    {[
                      { v: 'clone',  l: '기존 프로그램 복제' },
                      { v: 'table',  l: '테이블/뷰 이름으로 자동 생성 (개발자 권한 + 읽기전용)' },
                      { v: 'excel',  l: '엑셀 업로드 (Phase 2)', disabled: true },
                      { v: 'gsheet', l: '구글스프레드 URL (Phase 2)', disabled: true },
                    ].map(o => (
                      <label key={o.v} className={`flex items-center gap-2 text-sm ${o.disabled ? 'text-muted cursor-not-allowed' : 'text-primary cursor-pointer'}`}>
                        <input
                          type="radio"
                          name="subOption"
                          disabled={o.disabled}
                          checked={subOption === o.v}
                          onChange={() => setSubOption(o.v)}
                        />
                        {o.l}
                      </label>
                    ))}
                  </div>

                  {subOption === 'clone' && (
                    <div className="mt-2">
                      <input
                        className={inputCls + ' mb-1'}
                        value={sourceQuery}
                        onChange={e => setSourceQuery(e.target.value)}
                        placeholder="검색: 메뉴명 또는 real_pid"
                      />
                      <select
                        className={inputCls}
                        value={sourceRealPid}
                        onChange={e => setSourceRealPid(e.target.value)}
                        size={8}
                        style={{ height: 'auto' }}
                      >
                        <option value="">— 원본 프로그램 선택 —</option>
                        {sourceList.map(s => (
                          <option key={s.idx} value={s.real_pid}>
                            {s.menu_name}  ({s.real_pid})
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  {subOption === 'table' && (
                    <div className="mt-2 flex flex-col gap-2">
                      <div>
                        <label className={labelCls}>테이블 또는 뷰 이름</label>
                        <input
                          className={inputCls}
                          value={tableName}
                          onChange={e => setTableName(e.target.value)}
                          placeholder="예: mis_users  또는  car_parts_erp.g5_shop_item"
                        />
                        <div className="text-xs text-muted mt-1">
                          기준 DB(speedmis_v7)에 있는 테이블이면 이름만, 다른 스키마면 <code>schema.table</code> 형태로 입력
                        </div>
                      </div>
                      <div>
                        <label className={labelCls}>외부 DB alias (선택)</label>
                        <input
                          className={inputCls}
                          value={dbAlias}
                          onChange={e => setDbAlias(e.target.value)}
                          placeholder="기본 DB 사용 시 비워두세요"
                        />
                      </div>
                      <div className="text-xs text-secondary mt-1 px-2 py-1 bg-surface rounded border border-border-light">
                        ✓ 자동: 모든 컬럼이 mis_menu_fields 에 등록 · g01='simple_list' (단순목록) · g07='Y' (읽기전용) · 권한 그룹=83 (개발자) · auth_code='02'
                      </div>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>

        {/* 푸터 */}
        <div className="flex items-center justify-end gap-2 px-4 py-3 border-t border-border-base bg-surface-2 flex-shrink-0">
          <button
            type="button"
            disabled={saving}
            className="h-btn px-4 text-sm rounded border border-border-base bg-surface text-secondary hover:bg-surface-2 cursor-pointer"
            onClick={onClose}
          >취소</button>
          <button
            type="button"
            disabled={saving || loading || !!error}
            className="h-btn px-4 text-sm font-semibold rounded border border-accent bg-accent text-white hover:opacity-90 cursor-pointer disabled:opacity-50"
            onClick={handleSubmit}
          >{saving ? '생성 중…' : '생성'}</button>
        </div>
      </div>
    </div>,
    document.body
  );
}
