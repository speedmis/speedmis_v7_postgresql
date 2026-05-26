# SpeedMIS v7 — PostgreSQL 배포판 (speedmis_v7_postgresql)

> ⚠️ **이 레포는 `clude_speedmis_v7` / `speedmis_v7` (MariaDB 운영본) 및 `speedmis_v7_mssql` (MSSQL 배포판)과 완전히 별개입니다.**
> 코드를 섞거나 혼동하지 마세요. 이 레포는 **PostgreSQL 전용 다운로드 배포판**입니다.

워드프레스처럼 **다운로드 → 최초 구동 시 설치 마법사**로 DB 정보를 입력하면, 초기 데이터를 자동으로 받아 설치하는 독립 패키지입니다.

## 빠른 시작

1. 이 레포를 웹루트에 클론/압축해제 (PHP 8.3 + `pdo_pgsql` 확장 필요)
2. 브라우저에서 `https://<내도메인>/install.php` 접속
3. PostgreSQL 접속정보(호스트/포트/이름/계정) 입력 → **연결 & 자동 설치**
   - DB 가 없으면 자동 생성 (`CREATE DATABASE ... TEMPLATE template0 ENCODING 'UTF8'`)
   - 초기 데이터(`db/speedmis_db.sql.gz`)를 적재 (없으면 GitHub Public 레포에서 자동 다운로드)
   - 접속 URL 에서 `SITE_ID` 자동 생성, `.env` 자동 작성
4. 로그인: 아이디 `admin` / 비밀번호 **`4321`** (만능비밀번호)

## 특징

- **PostgreSQL 전용** — `.env` 의 `DB_DRIVER=pgsql` 고정, `PgCompatPDO` 로 MariaDB 문법(`DATE_FORMAT`, `IFNULL`, `concat()` 인자 형변환 등)을 자동 호환
- **SITE_ID 자동 생성** — 접속 호스트에서 소문자/숫자 3~8자 추출
  - 예) `postgo.xn--or3b27p5mi.com` → `postgo`
  - IP 로 접속 시 임시값(`ipXXXX`) → 이후 도메인 접속 시 자동으로 도메인 기반 값으로 갱신
  - 직접 지정하려면 `.env` 에서 `SITE_ID=원하는값`, `SITE_ID_AUTO=manual`
- **만능비밀번호 4321** — 동봉 DB 의 비밀번호는 마스킹돼 있어, 설치 직후엔 4321 로 로그인
  - 🔒 **운영 전환 시 `.env` 의 `MASTER_PASSWORD` 를 반드시 변경/비활성** 하세요

## 보안 주의

- 동봉 DB(`db/speedmis_db.sql.gz`)는 **개인정보·비밀번호가 마스킹**된 데이터입니다 (Public 레포).
- 설치 후 `install.php` 삭제를 권장합니다.
- `.env` 는 git 에 올라가지 않습니다(`.gitignore`). 설치 시 자동 생성됩니다.

## 폴더

| 경로 | 설명 |
|------|------|
| `install.php` | PostgreSQL 설치 마법사 |
| `.env.example` | 환경설정 템플릿 (SITE_ID 공백, MASTER_PASSWORD=4321) |
| `db/` | 초기 데이터 번들 (자세한 내용은 `db/README.md`) |
| `core/src/SiteId.php` | SITE_ID 자동 생성/갱신 헬퍼 |
| `core/src/Config/PgCompatPDO.php` | MariaDB 문법 PG 호환 PDO 래퍼 |
| `core/`, `programs/`, `src/` | v7 애플리케이션 코드 (MariaDB / MSSQL 배포판과 동일 베이스) |

## 스택

PHP 8.3 + Slim 4 + React 18 + Vite / **PostgreSQL 13+**
