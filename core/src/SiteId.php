<?php

namespace App;

/**
 * SITE_ID 자동 생성/갱신 헬퍼 (MSSQL 배포판 전용)
 *
 * 규칙 (요청 사양):
 *  - 설치/구동 시 접속 URL 의 호스트에서 의미있는 라벨을 뽑아 소문자·숫자 3~8자로 SITE_ID 생성
 *      예) msgo.xn--or3b27p5mi.com → "msgo"
 *  - IP 로 접속한 경우엔 의미있는 이름을 못 뽑으므로 임시값(provisional) 부여하고 SITE_ID_AUTO=pending 표시
 *  - 이후 영문 도메인으로 접속이 바뀌면 그 도메인 특성으로 SITE_ID 를 자동 갱신 (pending → done)
 *  - 사용자가 .env 에서 직접 SITE_ID 를 지정(=SITE_ID_AUTO 가 manual/없음)했으면 절대 건드리지 않음
 *
 * SITE_ID_AUTO 값:
 *   pending  — IP/임시값 상태. 도메인 접속이 들어오면 갱신 시도
 *   done     — 도메인에서 정상 생성됨. 더 이상 자동 변경 안 함
 *   manual   — 사용자가 직접 지정. 자동 변경 안 함
 */
class SiteId
{
    /** 호스트 라벨에서 무시할 일반 토큰 (www / TLD / SLD / punycode) */
    private const SKIP_LABELS = [
        'www', 'm', 'web', 'app', 'api', 'admin', 'test', 'dev', 'stage', 'staging',
        'com', 'net', 'org', 'co', 'kr', 'io', 'xyz', 'app', 'me', 'biz', 'info',
        'gov', 'edu', 'ac', 'go', 'or', 're', 'pe', 'ne', 'jp', 'cn', 'us',
    ];

    /**
     * 호스트 문자열에서 SITE_ID 후보를 도출.
     * 도메인이면 3~8자 소문자/숫자 문자열, IP/localhost 등 도출 불가하면 null.
     */
    public static function fromHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') return null;

        // 포트 제거 (host:port). IPv6 대괄호도 제거
        $host = trim($host, '[]');
        if (preg_match('/^(.*?):\d+$/', $host, $m)) $host = $m[1];

        // IP / localhost → 도출 불가
        if (filter_var($host, FILTER_VALIDATE_IP)) return null;
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) return null;
        if (!str_contains($host, '.')) {
            // 점 없는 단일 호스트명 (예: 사내 호스트) — 그 자체를 라벨로 사용
            return self::normalize($host, $host);
        }

        $labels = explode('.', $host);
        $cand = [];
        foreach ($labels as $lb) {
            if ($lb === '') continue;
            if (str_starts_with($lb, 'xn--')) continue;     // punycode(한글도메인) 라벨은 ascii 의미없음 → 스킵
            if (in_array($lb, self::SKIP_LABELS, true)) continue;
            $cand[] = $lb;
        }

        // 의미있는 라벨이 없으면 전체 호스트 기반으로 폴백
        if (!$cand) {
            $flat = preg_replace('/[^a-z0-9]/', '', $host);
            return self::normalize($flat !== '' ? $flat : 'site', $host);
        }

        // 가장 왼쪽(서브도메인) 라벨을 우선 사용 (예: msgo.부자톡.com → msgo)
        return self::normalize($cand[0], $host);
    }

    /**
     * IP/localhost 등 도출 불가 호스트용 임시 SITE_ID. 호스트별로 안정적(같은 IP→같은 값).
     */
    public static function provisional(string $host): string
    {
        $host = strtolower(trim($host, '[]'));
        if (preg_match('/^(.*?):\d+$/', $host, $m)) $host = $m[1];
        return 'ip' . substr(md5($host !== '' ? $host : 'unknown'), 0, 4); // 예: ip3f2a (6자)
    }

    /** 후보 문자열을 [a-z0-9] 3~8자로 정규화 */
    private static function normalize(string $raw, string $seed): string
    {
        $id = preg_replace('/[^a-z0-9]/', '', strtolower($raw));
        if ($id === '') $id = 'site';
        if (strlen($id) > 8) $id = substr($id, 0, 8);
        // 3자 미만이면 호스트 해시로 채움
        $i = 0;
        while (strlen($id) < 3) {
            $id .= substr(md5($seed . $i), 0, 1);
            $i++;
        }
        return $id;
    }

    /**
     * 현재 요청 호스트 기준으로 .env 의 SITE_ID 를 필요 시 갱신.
     * - 매 요청 호출되지만 SITE_ID_AUTO 가 'pending' 일 때만 파일을 건드린다 (그 외엔 즉시 반환).
     * - IP→도메인 전환을 잡아 SITE_ID 를 도메인 기반으로 업그레이드.
     *
     * @param string $envPath  .env 절대경로
     */
    public static function reconcile(string $envPath): void
    {
        // 사용자가 직접 지정했거나 이미 도메인으로 확정(done)이면 아무것도 안 함
        $auto = strtolower((string)($_ENV['SITE_ID_AUTO'] ?? 'manual'));
        if ($auto !== 'pending') return;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return;

        $derived = self::fromHost($host);
        if ($derived === null) return; // 아직도 IP 접속 → 다음 기회에

        // 도메인에서 정상 도출됨 → SITE_ID 업그레이드 & 잠금(done)
        if (!is_file($envPath)) return;
        if (!class_exists(InstallAuth::class)) {
            require_once __DIR__ . '/InstallAuth.php';
        }
        InstallAuth::writeEnvMerge($envPath, [
            'SITE_ID'      => $derived,
            'SITE_ID_AUTO' => 'done',
        ]);
        // 현재 프로세스에도 즉시 반영
        $_ENV['SITE_ID'] = $derived;
        $_ENV['SITE_ID_AUTO'] = 'done';
        putenv('SITE_ID=' . $derived);
    }
}
