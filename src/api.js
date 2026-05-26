/**
 * SpeedMIS v7 — API 클라이언트
 * 모든 요청: /api.php?act=xxx
 */

const BASE_PATH = window.__APP_CONFIG__?.basePath ?? '';
const BASE = (window.__APP_CONFIG__?.apiUrl ?? (BASE_PATH + '/api.php')).replace(/api\.php.*$/, 'api.php');
// 다른 모듈에서 절대 경로 prepend 용 — apiPath('/api.php?act=xxx') → '/v7/api.php?act=xxx'
export function apiPath(p) { return BASE_PATH + p; }

let _csrfToken = null;

async function ensureCsrf() {
  if (_csrfToken) return _csrfToken;
  // 쿠키에서 읽기
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
  if (match) {
    _csrfToken = decodeURIComponent(match[1]);
    return _csrfToken;
  }
  // 없으면 서버에서 발급
  const res = await fetch(`${BASE}?act=csrf`, { credentials: 'include' });
  const data = await res.json();
  _csrfToken = data.csrf_token ?? '';
  return _csrfToken;
}

// CSRF 토큰 강제 재발급 — 쿠키 만료(1시간)/불일치로 403 발생 시 호출.
// 캐시를 비우고 서버에서 새 토큰+쿠키를 받아 다음 요청이 통과되게 함.
async function refreshCsrf() {
  _csrfToken = null;
  try {
    const res = await fetch(`${BASE}?act=csrf`, { credentials: 'include' });
    const data = await res.json();
    _csrfToken = data.csrf_token ?? '';
  } catch { _csrfToken = ''; }
  return _csrfToken;
}

async function request(act, options = {}) {
  const { params = {}, body = null, method = body ? 'POST' : 'GET' } = options;

  // undefined / null 값 제거 — URLSearchParams 가 문자열 "undefined"/"null" 로 인코딩하는 것 방지
  const cleanParams = { act };
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null) cleanParams[k] = v;
  }
  const qs = new URLSearchParams(cleanParams).toString();
  const url = `${BASE}?${qs}`;

  const headers = { 'Content-Type': 'application/json' };

  if (method === 'POST') {
    headers['X-CSRF-Token'] = await ensureCsrf();
  }

  const init = {
    method,
    headers,
    credentials: 'include',
  };

  if (body !== null) {
    init.body = JSON.stringify(body);
  }

  let res = await fetch(url, init);

  // 403 CSRF (토큰 만료/불일치) → 토큰 재발급 후 1회 자동 재시도. 사용자에게 에러 안 보이게.
  if (res.status === 403 && method === 'POST') {
    let isCsrf = false;
    try { const j = await res.clone().json(); isCsrf = /csrf/i.test(j?.message ?? ''); } catch {}
    if (isCsrf) {
      await refreshCsrf();
      init.headers['X-CSRF-Token'] = _csrfToken;
      res = await fetch(url, init);
    }
  }

  // 401 → refresh 시도
  if (res.status === 401) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      res = await fetch(url, init);
    } else {
      window.dispatchEvent(new CustomEvent('mis:logout'));
      throw new Error('인증이 만료되었습니다. 다시 로그인해주세요.');
    }
  }

  const data = await res.json();
  if (!data.success) {
    // 서버 confirm 요청: 에러 대신 _confirm 포함하여 반환
    if (data._confirm) return data;
    const err = new Error(data.message ?? '요청 실패');
    if (data._sql) err._sqlData = { sql: data._sql, count_sql: data._count_sql ?? null, bindings: data._bindings ?? [], error: data._sql_error ?? null };
    throw err;
  }
  return data;
}

async function tryRefresh() {
  try {
    const res = await fetch(`${BASE}?act=refresh`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
    });
    const data = await res.json();
    return data.success === true;
  } catch {
    return false;
  }
}

// ─── 공개 API ────────────────────────────────────────────────────────────────

export const api = {
  // 인증
  login:   (uid, pass, logoutOthers = false) => request('login', { body: { uid, pass, logoutOthers: !!logoutOthers } }),
  logout:  ()           => request('logout', { method: 'POST', body: {} }),
  me:      ()           => request('me'),

  // 메뉴
  menu:              ()         => request('menu'),
  menuItem:          (gubun)    => request('menuItem', { params: { gubun } }),
  menuItemByRealPid: (realPid)  => request('menuItem', { params: { real_pid: realPid } }),

  // CRUD
  list: (gubun, opts = {}) => request('list', {
    params: { gubun, ...opts },
  }),

  view: (gubun, idx, devMode = false, actionFlag = '') => request('view', {
    params: { gubun, idx, ...(devMode ? { dev_mode: '1' } : {}), ...(actionFlag ? { actionFlag } : {}) },
  }),

  save: (gubun, body, idx = 0, devMode = false) => request('save', {
    params: { gubun, idx, ...(devMode ? { dev_mode: '1' } : {}) },
    body,
  }),

  delete: (gubun, idx) => request('delete', { params: { gubun, idx } }),

  bulkDelete: (gubun, idxList) => request('bulkDelete', {
    params: { gubun },
    body: { idxList },
  }),

  bulkListSave: (gubun, edits) => request('bulkListSave', {
    params: { gubun },
    body: { edits },
  }),

  bulkRestore: (gubun, idxList) => request('bulkRestore', {
    params: { gubun },
    body: { idxList },
  }),

  bulkPermanentDelete: (gubun, idxList) => request('bulkPermanentDelete', {
    params: { gubun },
    body: { idxList },
  }),

  filterItems:    (gubun, field) => request('filterItems',    { params: { gubun, field } }),
  primeKeyItems:  (gubun, field, idx = '', ctx = null) => request('primeKeyItems',  { params: { gubun, field, ...(idx !== '' && idx != null ? { idx } : {}), ...(ctx ?? {}) } }),
  dropdownItems:  (gubun, alias) => request('dropdownItems',  { params: { gubun, alias } }),

  treat: (gubun, body) => request('treat', {
    params: { gubun },
    body,
  }),

  briefInsert: (gubun, count, parentIdx = '') => request('briefInsert', {
    params: { gubun },
    body: { gubun, count, parent_idx: parentIdx },
  }),

  saveFormLayout: (gubun, items) => request('saveFormLayout', {
    params: { gubun },
    body: { items },
  }),

  shortUrl: (url) => request('shortUrl', { body: { url } }),

  flushCache: () => request('flushCache', { method: 'POST' }),

  // 나의백업에 추가 — 현재 리스트 데이터를 JSON 으로 저장 + mis_backup_list INSERT (admin 만)
  backupList: (gubun, opts = {}) => request('backupList', {
    method: 'POST',
    params: { gubun },
    body: opts,  // { allFilter, orderby, recently, parent_gubun, parent_idx }
  }),

  // 대시보드 — refPid 지정 시 해당 프로그램의 데이터를 위젯 소스로 사용
  dashboardConfig:    (refPid = '') => request('dashboardConfig', refPid ? { params: { refPid } } : {}),
  // items: [{ real_pid, pos:'L'|'R' }, ...]
  dashboardSaveOrder: (items) => request('dashboardSaveOrder', { body: { items } }),

  // 314번 "추가" 버튼 전용
  menuPathInfo:   (idx)      => request('menuPathInfo',   { params: { idx } }),
  menuSourceList: (q = '')   => request('menuSourceList', { params: { q } }),
  menuCreate:     (body)     => request('menuCreate',     { body }),
  applyAutoDesign: ({ realPid, gubun } = {}) => request('applyAutoDesign', { body: { realPid, gubun } }),

  // 파일
  // 임시 업로드 — 파일 선택 즉시 호출. 응답의 token 을 보관 후 저장 시 _tempAttach 로 전달
  fileUpload: (file) => {
    const fd = new FormData();
    fd.append('file', file);
    return ensureCsrf().then(csrf =>
      fetch(`${BASE}?act=fileUpload`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrf },
        body: fd,
      }).then(r => r.json())
    );
  },

  // midx 기준 파일 목록
  fileList:   (midx) => request('fileList', { params: { midx } }),
  fileDelete: (idx)        => request('fileDelete',  { params: { idx } }),
  fileDownloadUrl: (idx)   => `${BASE}?act=fileDownload&idx=${idx}`,
  fileViewUrl:     (idx)   => `${BASE}?act=fileDownload&idx=${idx}&view=1`,

  // ── 메신저 / 푸시 ─────────────────────────────────────────────
  pushVapidKey:    () => request('pushVapidKey'),
  pushSubscribe:   (subscription, deviceLabel = '') => request('pushSubscribe', { body: { subscription, device_label: deviceLabel } }),
  pushUnsubscribe: (endpoint) => request('pushUnsubscribe', { body: { endpoint } }),
  pushTest:        () => request('pushTest', { method: 'POST' }),
  pushSend:        (to, title, body, url) => request('pushSend', { body: { to, title, body, url } }),

  chatRooms:       () => request('chatRooms'),
  chatHistory:     (room, before = 0, limit = 50) => request('chatHistory', { params: { room, before, limit } }),
  chatSend:        (room, body) => request('chatSend', { body: { room, body } }),
  chatRead:        (room, message_idx) => request('chatRead', { body: { room, message_idx } }),
  chatLeave:       (room) => request('chatLeave', { body: { room } }),
  chatRoomDm:      (to) => request('chatRoomDm', { body: { to } }),
  chatRoomGroup:   (title, members) => request('chatRoomGroup', { body: { title, members } }),
  chatInvite:      (room_idx, members) => request('chatInvite', { body: { room_idx, members } }),
  chatOrgTree:     () => request('chatOrgTree'),
  // helplist 팝업 데이터 — { gubun, field, page, size, filters: {key:val,...} }
  helplistItems: (gubun, field, page = 1, size = 20, filters = {}) =>
    request('helplistItems', { params: { gubun, field, page, size, filters: JSON.stringify(filters) } }),
  // 채팅 첨부/이미지 임시 업로드 — 응답 url 을 HTML 본문에 임베드
  chatUpload: (file) => {
    const fd = new FormData();
    fd.append('file', file);
    return ensureCsrf().then(csrf =>
      fetch(`${BASE}?act=chatUpload`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrf },
        body: fd,
      }).then(r => r.json())
    );
  },
};

export default api;
