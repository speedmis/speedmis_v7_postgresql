import React, { useState } from 'react';

export default function Login({ onLogin, siteTitle }) {
  const [uid, setUid]   = useState('');
  const [pass, setPass] = useState('');
  const [logoutOthers, setLogoutOthers] = useState(false);
  const [err, setErr]   = useState('');
  const [busy, setBusy] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    if (busy) return;
    setErr('');
    setBusy(true);
    try {
      await onLogin(uid, pass, logoutOthers);
    } catch (ex) {
      setErr(ex.message ?? '로그인 실패');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div style={styles.wrap}>
      <form onSubmit={handleSubmit} style={styles.card}>
        <h1 style={styles.title}>{siteTitle}</h1>
        {err && <div style={styles.err}>{err}</div>}
        <input
          style={styles.input}
          type="text"
          placeholder="아이디"
          value={uid}
          onChange={e => setUid(e.target.value)}
          autoFocus
          required
        />
        <input
          style={styles.input}
          type="password"
          placeholder="비밀번호"
          value={pass}
          onChange={e => setPass(e.target.value)}
          required
        />
        <label style={styles.checkLabel}>
          <input
            type="checkbox"
            checked={logoutOthers}
            onChange={e => setLogoutOthers(e.target.checked)}
            style={styles.checkbox}
          />
          타장비 로그아웃 (다른 기기 세션 강제 종료)
        </label>
        <button style={{ ...styles.btn, opacity: busy ? 0.7 : 1 }} disabled={busy}>
          {busy ? '로그인 중...' : '로그인'}
        </button>
      </form>
    </div>
  );
}

const styles = {
  wrap: {
    minHeight: '100vh', display: 'flex',
    alignItems: 'center', justifyContent: 'center',
    background: '#f0f2f5',
  },
  card: {
    background: '#fff', padding: '40px 36px', borderRadius: '8px',
    boxShadow: '0 2px 16px rgba(0,0,0,.12)', width: '320px',
    display: 'flex', flexDirection: 'column', gap: '12px',
  },
  title: { fontSize: '20px', fontWeight: 700, textAlign: 'center', color: '#1a1a1a' },
  err:   { padding: '8px 12px', background: '#fff2f0', color: '#f5222d',
           borderRadius: '4px', fontSize: '13px', border: '1px solid #ffa39e' },
  input: {
    padding: '9px 12px', border: '1px solid #d9d9d9',
    borderRadius: '4px', fontSize: '14px', outline: 'none',
  },
  btn: {
    padding: '10px', background: '#1677ff', color: '#fff',
    border: 'none', borderRadius: '4px', fontSize: '15px',
    cursor: 'pointer', fontWeight: 600,
  },
  checkLabel: {
    display: 'flex', alignItems: 'center', gap: '6px',
    fontSize: '13px', color: '#595959', cursor: 'pointer',
    userSelect: 'none', padding: '2px 0',
  },
  checkbox: {
    width: '16px', height: '16px', cursor: 'pointer',
  },
};
