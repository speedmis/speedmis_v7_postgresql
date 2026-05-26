# db/ — 초기 데이터 번들 (PostgreSQL)

`install.php` 가 최초 구동 시 여기서 초기 데이터(`speedmis_db`)를 읽어 설치합니다.

## 파일

| 파일 | 설명 |
|------|------|
| `speedmis_db.sql.gz` | PostgreSQL 초기 데이터 (스키마 + 데이터). gzip 압축. **설치 시 우선 사용** |
| `speedmis_db.sql` | 위의 비압축 버전 (둘 중 있는 것을 사용) |

설치 마법사 동작 순서:
1. 로컬 `db/speedmis_db.sql.gz` → `db/speedmis_db.sql` 순으로 찾아 사용
2. 둘 다 없으면 `.env` 의 `DB_BUNDLE_URL`(Public 레포 raw)에서 자동 다운로드

## 마스킹 정책 (중요)

이 번들은 **운영 데이터를 거의 그대로** 담되, 아래만 마스킹합니다:
- `mis_users.passwd_decrypt` → 비움 (로그인은 `.env` 의 `MASTER_PASSWORD=4321` 만능비번으로)
- 고객/사용자의 개인정보 컬럼(전화·휴대폰·이메일·주소·주민/사업자번호 등) → 더미값으로 치환
- 채팅/메일/토큰/외부키류 테이블 → 비움 (PII 위험)

> Public 레포이므로 **실제 개인정보·실접속정보는 절대 커밋하지 않습니다.**

## 덤프 생성 절차 (관리자용)

PostgreSQL 서버에서 `pg_dump` 로 schema+data 를 추출 후 마스킹합니다.

```bash
# 예시 (DB 서버 또는 같은 망에서 실행)
pg_dump \
  -h <host> -p 5432 -U postgres -d speedmis_db \
  --no-owner --no-acl \
  --no-publications --no-subscriptions \
  --schema=public \
  --inserts --column-inserts \
  > speedmis_db.raw.sql

# → 마스킹 패스 적용 후 gzip 으로 압축
gzip -9 speedmis_db.sql -c > speedmis_db.sql.gz
```

생성 규칙:
- `CREATE DATABASE` / `\connect` / `SET ROLE` 구문 제외 (설치 마법사가 DB 를 만들고 선택함)
- `--no-owner --no-acl` 로 OWNER/GRANT 라인 제거 (배포 환경의 role 이 다름)
- `--inserts --column-inserts` 권장 — COPY 보다 호환성 좋고 partial-failure 시 디버깅 용이
- 만약 함수/뷰가 dollar-quoted (`$$...$$`) 형태로 들어있다면 그대로 두어도 PDO 가 처리

## 적재 동작

설치 마법사는 적재를 **하나의 PDO 트랜잭션** 으로 감쌉니다:
- libpq 가 다문장 SQL 을 한꺼번에 처리하므로 `;` 단위 split 불필요
- 도중 어떤 문이라도 실패하면 전체 rollback → 부분 적재 상태가 남지 않음
- 적재 후 `mis_menus` 행수로 sanity check
