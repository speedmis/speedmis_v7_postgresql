<?php
namespace App;

/**
 * 인쇄양식 템플릿 렌더러
 *
 * 문법:
 *   {{alias_name}}                        → 레코드 필드값
 *   {{#each childAlias}}...{{/each}}      → child 프로그램 루프
 *   루프 안에서 {{alias_name}}            → child 레코드 필드값
 *   {{@index}}                            → 루프 인덱스 (1부터)
 *   {{@total}}                            → child 전체 건수
 */
class PrintRenderer
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function render(string $template, array $data, array $fields, $parentIdx): string
    {
        // child 필드 맵: alias_name → real_pid
        $childMap = [];
        foreach ($fields as $f) {
            if (($f['grid_ctl_name'] ?? '') === 'child' && !empty($f['default_value'])) {
                $childMap[$f['alias_name']] = trim($f['default_value']);
            }
        }

        // {{#each childAlias}}...{{/each}} 처리
        $html = preg_replace_callback(
            '/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s',
            function ($m) use ($childMap, $parentIdx) {
                $alias = $m[1];
                $rowTpl = $m[2];
                $realPid = $childMap[$alias] ?? null;
                if (!$realPid) return "<!-- child '{$alias}' not found -->";

                $childRows = $this->fetchChildData($realPid, $parentIdx);
                $total = count($childRows);
                $out = '';
                foreach ($childRows as $i => $row) {
                    $s = $rowTpl;
                    $s = str_replace('{{@index}}', (string)($i + 1), $s);
                    $s = str_replace('{{@total}}', (string)$total, $s);
                    $s = preg_replace_callback('/\{\{(\w+)\}\}/', function ($fm) use ($row) {
                        return htmlspecialchars($row[$fm[1]] ?? '', ENT_QUOTES, 'UTF-8');
                    }, $s);
                    $out .= $s;
                }
                return $out;
            },
            $template
        );

        // 메인 레코드 {{field}} 치환
        $html = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($data) {
            $key = $m[1];
            if ($key === '@index' || $key === '@total') return $m[0];
            return htmlspecialchars($data[$key] ?? '', ENT_QUOTES, 'UTF-8');
        }, $html);

        return $html;
    }

    /**
     * child 프로그램 데이터 조회 — DataHandler의 list API를 내부 호출
     */
    private function fetchChildData(string $realPid, $parentIdx): array
    {
        // child gubun 조회
        $stmt = $this->pdo->prepare('SELECT idx FROM mis_menus WHERE real_pid = ? LIMIT 1');
        $stmt->execute([$realPid]);
        $childGubun = (int)($stmt->fetchColumn() ?: 0);
        if (!$childGubun) return [];

        // DataHandler 인스턴스 생성하여 list 호출
        try {
            $handler = $this->getDataHandler();
            $user = (object)[
                'uid'           => $GLOBALS['misSessionUserId'] ?? 'admin',
                'is_admin'      => $GLOBALS['misSessionIsAdmin'] ?? 'Y',
                'position_code' => $GLOBALS['misSessionPositionCode'] ?? '',
            ];
            $result = $handler->list([
                'gubun'      => $childGubun,
                'parent_idx' => (string)$parentIdx,
                'pageSize'   => 1000,
                'page'       => 1,
            ], $user);
            return $result['data'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private ?DataHandler $handlerCache = null;

    private function getDataHandler(): DataHandler
    {
        if ($this->handlerCache) return $this->handlerCache;
        $qb = new \App\QueryBuilder();
        $cache = new \App\MisCache();
        // 간이 logger
        $logger = new class implements \Psr\Log\LoggerInterface {
            public function emergency($m, array $c = []): void {}
            public function alert($m, array $c = []): void {}
            public function critical($m, array $c = []): void {}
            public function error($m, array $c = []): void {}
            public function warning($m, array $c = []): void {}
            public function notice($m, array $c = []): void {}
            public function info($m, array $c = []): void {}
            public function debug($m, array $c = []): void {}
            public function log($level, $m, array $c = []): void {}
        };
        $this->handlerCache = new DataHandler($this->pdo, $qb, $cache, $logger);
        return $this->handlerCache;
    }
}
