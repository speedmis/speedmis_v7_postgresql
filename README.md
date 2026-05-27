# SpeedMIS v7 — PostgreSQL 배포판 (speedmis_v7_postgresql)

> ⚠️ **이 레포는 `speedmis_v7_mariadb` / `speedmis_v7_mssql` 와 별개입니다.**
> 이 레포는 **PostgreSQL 전용 다운로드 배포판**입니다.

워드프레스처럼 **다운로드 → 브라우저에서 install.php 호출**로 자동 설치되는 독립 패키지. 웹서버 설정 없이 시작 가능.

## 🚀 30초 빠른 시작

### 시나리오 ① 공유 호스팅 (cafe24 / 가비아 / dothome — Apache)

1. 호스팅 관리자 페이지에서 **DB 1개 생성** (이름/사용자/비번 메모)
2. 이 레포를 ZIP 다운로드 → FTP / 파일매니저로 `public_html/` 에 압축 해제
3. 브라우저에서 `https://내도메인.com/install.php` 접속
4. DB 정보 입력 → **연결 & 자동 설치** 한 번 → 끝
5. 로그인: **`gadmin / 4321`**

→ `.htaccess` 가 포함돼 있어 Apache 가 자동 인식. **호스팅 설정 변경 0회**.

### 시나리오 ② 로컬 PC (Windows / Mac / Linux — 웹서버 없이)

```bash
git clone https://github.com/speedmis/speedmis_v7_postgresql.git
cd speedmis_v7_mariadb
./start.sh          # Linux / Mac    (또는 start.bat — Windows)
```

→ http://localhost:8080/install.php 자동 안내. **nginx/Apache 설치 불필요** (PHP 만 있으면 OK).

### 시나리오 ③ VPS / 클라우드 (nginx + PHP-FPM)

`docs/install/nginx.conf.example` 복사 → `sudo systemctl reload nginx` → 끝.

---

## 설치 마법사가 자동으로 해주는 것

- DB 가 없으면 **자동 생성** (공유호스팅은 권한 부족 시 graceful — 이미 만든 DB 사용)
- 초기 데이터(`db/speedmis_db.sql.gz`) 적재 (로컬 우선, 없으면 GitHub Public raw 자동 다운로드)
- 접속 URL 에서 `SITE_ID` 자동 생성
- `.env` 자동 작성 (JWT 키 자동 생성)
- 디렉토리 권한 자동 설정 (`uploadFiles/`, `logs/`, `.cache/`)

## 동봉 자산

| 파일 | 역할 |
|------|------|
| `install.php` | 설치 마법사 (DB 자동 생성/적재 + .env 작성) |
| `.htaccess` | Apache 자동 동작 — URL 라우팅, 보안, 캐시 |
| `web.config` | IIS 자동 동작 (Windows 서버) |
| `start.sh` / `start.bat` | 로컬 PHP 내장 서버 1줄 시작 |
| `router.php` | PHP 내장 서버용 라우터 |
| `db/speedmis_db.sql.gz` | 마스킹된 샘플 DB (116 테이블, 850KB) |
| `core/`, `programs/`, `src/`, `vendor/` | v7 애플리케이션 본체 (다른 DB 배포판과 동일 베이스) |

## 특징

- **MariaDB / MySQL 전용** — `.env` 의 `DB_DRIVER=pgsql` 고정
- **SITE_ID 자동 생성** — 접속 호스트에서 소문자/숫자 3~8자 추출 (예: `v7ma.speedmis.com` → `v7ma`)
- **만능비밀번호 4321** — 동봉 DB 비밀번호는 마스킹돼 있어 설치 직후 `gadmin / 4321` 로 로그인
  - 🔒 **운영 전환 시 `.env` 의 `MASTER_PASSWORD` 반드시 변경/비활성**

## 보안 주의

- 동봉 DB(`db/speedmis_db.sql.gz`)는 **개인정보·비밀번호가 마스킹**된 샘플 데이터입니다.
- 설치 후 `install.php` 삭제를 권장합니다.
- `.env` 는 git 에 올라가지 않습니다 (`.gitignore` + `.htaccess` + `web.config` 다중 차단).

## 자매 레포 (다른 DB 백엔드)

| DB | 레포 |
|----|------|
| MariaDB / MySQL | https://github.com/speedmis/speedmis_v7_mariadb |
| Microsoft SQL Server | https://github.com/speedmis/speedmis_v7_mssql |
| **PostgreSQL** | https://github.com/speedmis/speedmis_v7_postgresql (이 레포) |

## 스택

PHP 8.3 + Slim 4 + React 18 + Vite / **PostgreSQL 14+**
