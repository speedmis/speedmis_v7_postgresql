<?php
/**
 * SpeedMIS v7 — 단일 API 엔트리포인트
 * 모든 요청: api.php?act=xxx&gubun=xxx
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/vendor/autoload.php';

use App\Bootstrap;
use App\DataHandler;
use App\MenuRouter;
use App\FileManager;
use App\MenuCreator;
use App\MisCache;
use App\Messenger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = Bootstrap::createApp();

// 배포 base path 자동 감지 — /v7/api.php → '/v7' / 루트 → ''
// Slim 라우터는 BasePath 를 빼고 매칭하므로 서브경로 배치 시 필수.
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath !== '') $app->setBasePath($basePath);

// ─── 헬퍼 ─────────────────────────────────────────────────────────────────────
function jsonOut(Response $response, array $data, int $status = 200): Response
{
    // PG/MSSQL 백엔드면 dev_mode SQL 표시도 해당 문법으로 변환 (백틱→[]/더블쿼트 등)
    $xlator = null;
    if (class_exists('\App\Config\Database')) {
        if (\App\Config\Database::isPg())    $xlator = ['\App\Config\PgCompatPDO',    'translate'];
        elseif (\App\Config\Database::isMssql()) $xlator = ['\App\Config\MssqlCompatPDO', 'translate'];
    }
    if ($xlator) {
        // MSSQL 표시 SQL 은 가독성 위해 단순 식별자 [name] → name 으로 풀어 보여줌.
        // 실제 prepare 단계의 SQL 은 영향 없음 (MssqlCompatPDO::translate 가 다시 [] 로 묶음).
        $isMssql   = ($xlator[0] === '\App\Config\MssqlCompatPDO');
        $stripBracket = static function (string $sql): string {
            return preg_replace('/\[([A-Za-z_][A-Za-z0-9_]*)\]/', '$1', $sql) ?? $sql;
        };
        foreach (['_sql', '_count_sql', '_view_sql'] as $k) {
            if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                $sql = call_user_func($xlator, $data[$k]);
                if ($isMssql) $sql = $stripBracket($sql);
                $data[$k] = $sql;
            }
        }
        if (isset($data['_execSql']) && is_array($data['_execSql'])) {
            foreach ($data['_execSql'] as &$row) {
                if (isset($row['sql']) && is_string($row['sql'])) {
                    $sql = call_user_func($xlator, $row['sql']);
                    if ($isMssql) $sql = $stripBracket($sql);
                    $row['sql'] = $sql;
                }
            }
            unset($row);
        }
    }
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $response
        ->withStatus($status)
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
}

function getUser(Request $request): object
{
    return $request->getAttribute('user') ?? (object)[];
}

// ─── 라우트 ───────────────────────────────────────────────────────────────────

$app->any('/api.php', function (Request $req, Response $res) use ($app): Response {
    $params  = $req->getQueryParams();
    $body    = (array)($req->getParsedBody() ?? []);
    $act     = $params['act'] ?? '';
    $user    = getUser($req);
    $container = $app->getContainer();

    switch ($act) {

        // ── 인증 ──────────────────────────────────────────────────────────────
        case 'login':
            return handleLogin($req, $res, $container, $body);

        case 'logout':
            return handleLogout($req, $res, $container);

        case 'refresh':
            return handleRefresh($req, $res, $container);

        case 'csrf':
            $token = bin2hex(random_bytes(32));
            setcookie('csrf_token', $token, [
                'expires'  => time() + 3600,
                'path'     => '/',
                'httponly' => false,  // JS에서 읽을 수 있어야 함
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
            ]);
            return jsonOut($res, ['success' => true, 'csrf_token' => $token]);

        // ── 메뉴 ──────────────────────────────────────────────────────────────
        case 'menu':
            $router = $container->get(\App\MenuRouter::class);
            return jsonOut($res, ['success' => true, 'data' => $router->getMenuTree($user)]);

        // ── 서버로직만 (menu_type='22') 메뉴 ────────────────────────────────
        // programs/{real_pid}.php + {real_pid}_treat.php 를 include 후 HTML 직접 출력.
        // 메뉴 영역에 iframe srcDoc 으로 표시 (CSS 격리). POST 시 addLogic_treat 훅 호출.
        case 'serverOnly': {
            $gubun = (int)($params['gubun'] ?? 0);
            if ($gubun <= 0) {
                $res->getBody()->write('<h3 style="color:red">gubun 필요</h3>');
                return $res->withStatus(400)->withHeader('Content-Type', 'text/html; charset=UTF-8');
            }

            $router = $container->get(\App\MenuRouter::class);
            $menu   = $router->getMenu($gubun);
            if (!$menu) {
                $res->getBody()->write('<h3 style="color:red">메뉴 없음</h3>');
                return $res->withStatus(404)->withHeader('Content-Type', 'text/html; charset=UTF-8');
            }
            if (($menu['menu_type'] ?? '') !== '22') {
                $res->getBody()->write('<h3 style="color:red">서버로직만(22) 메뉴 아님</h3>');
                return $res->withStatus(400)->withHeader('Content-Type', 'text/html; charset=UTF-8');
            }

            $realPid = trim((string)($menu['real_pid'] ?? ''));
            if ($realPid === '') {
                $res->getBody()->write('<h3 style="color:red">real_pid 없음</h3>');
                return $res->withStatus(400)->withHeader('Content-Type', 'text/html; charset=UTF-8');
            }

            // CSRF 토큰 — iframe 내부 form 의 hidden _csrf 용. 쿠키 없으면 발급.
            $csrfToken = (string)($_COOKIE['csrf_token'] ?? '');
            if ($csrfToken === '') {
                $csrfToken = bin2hex(random_bytes(16));
                setcookie('csrf_token', $csrfToken, [
                    'path'     => '/',
                    'httponly' => false,
                    'samesite' => 'Lax',
                    'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on',
                ]);
                $_COOKIE['csrf_token'] = $csrfToken;
            }

            // 전역 변수 세팅 (v7 프로그램 환경)
            $GLOBALS['gubun']             = $gubun;
            $GLOBALS['real_pid']          = $realPid;
            $GLOBALS['menu_name']         = (string)($menu['menu_name'] ?? '');
            $GLOBALS['actionFlag']        = 'serverOnly';
            $GLOBALS['__pdo']             = $container->get(\PDO::class);
            $GLOBALS['misSessionUserId']  = (string)($user->uid ?? '');
            $GLOBALS['misSessionIsAdmin'] = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
            $GLOBALS['full_site']         = rtrim($_ENV['APP_URL'] ?? '', '/');
            $GLOBALS['_csrf_token']       = $csrfToken;
            // POST body / GET params 도 노출 — 프로그램이 $_POST/$_GET 사용 외에도 명시적 변수 가능
            $GLOBALS['_serverOnly_params'] = $params;
            $GLOBALS['_serverOnly_body']   = $body;

            $base = BASE_PATH . '/programs';
            foreach (['_common.php', '_common_udef.php'] as $cf) {
                $p = "{$base}/{$cf}";
                if (is_file($p)) { try { include_once $p; } catch (\Throwable) {} }
            }
            foreach (["{$realPid}.php", "{$realPid}_treat.php"] as $f) {
                $p = "{$base}/{$f}";
                if (is_file($p)) { try { include_once $p; } catch (\Throwable) {} }
            }

            // 출력 버퍼 — include 단계 + pageLoad/view_templete 호출 결과 모두 캡쳐
            ob_start();
            try {
                // POST → treat 훅 (메일 발송 등 액션)
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('addLogic_treat')) {
                    $treatResult = array_merge($params, $body, ['real_pid' => $realPid, '_method' => 'POST']);
                    try { addLogic_treat($treatResult); } catch (\Throwable $e) {
                        echo '<div style="color:red;border:2px solid red;padding:10px;margin:10px 0">treat 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    $GLOBALS['_treat_result'] = $treatResult;
                }

                // pageLoad — 출력 직접 수행하는 v6 스타일 호환
                if (function_exists('pageLoad')) {
                    try { pageLoad(); } catch (\Throwable $e) {
                        echo '<div style="color:red;border:2px solid red;padding:10px;margin:10px 0">pageLoad 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
                // view_templete 가 있으면 그 반환값을 우선 출력
                if (function_exists('view_templete')) {
                    try { echo view_templete(); } catch (\Throwable $e) {
                        echo '<div style="color:red;border:2px solid red;padding:10px;margin:10px 0">view_templete 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            } catch (\Throwable $e) {
                echo '<div style="color:red;border:2px solid red;padding:10px;margin:10px 0">실행 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            $html = ob_get_clean();

            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        case 'menuItem':
            $gubun   = (int)($params['gubun']    ?? 0);
            $realPid = trim($params['real_pid']  ?? '');
            $router  = $container->get(\App\MenuRouter::class);
            $menu    = $realPid !== '' ? $router->getMenuByRealPid($realPid) : $router->getMenu($gubun);
            // menu_type='00' (그룹 노드) 이면 하위 계층 중 첫 번째 실제 프로그램으로 redirect
            // 조건: autogubun prefix 매칭(재귀) + menu_type<>'00' + g12<>'Y'(아들 제외)
            $redirect = null;
            if (!empty($menu) && ($menu['menu_type'] ?? '') === '00' && trim($menu['table_name'] ?? '') === '') {
                try {
                    $pdo = $container->get(\PDO::class);
                    $autoGubun = trim($menu['autogubun'] ?? '');
                    if ($autoGubun !== '') {
                        $cq = $pdo->prepare(
                            "SELECT idx, real_pid, menu_name FROM mis_menus
                              WHERE autogubun LIKE CONCAT(?, '%')
                                AND autogubun <> ?
                                AND useflag = '1'
                                AND menu_type <> '00'
                                AND IFNULL(g12, '') <> 'Y'
                              ORDER BY autogubun ASC, idx ASC
                              LIMIT 1"
                        );
                        $cq->execute([$autoGubun, $autoGubun]);
                        if ($child = $cq->fetch(\PDO::FETCH_ASSOC)) {
                            $redirect = ['gubun' => (int)$child['idx'], 'label' => $child['menu_name']];
                        }
                    }
                } catch (\Throwable) {}
            }
            $out = ['success' => !empty($menu), 'data' => $menu];
            if ($redirect) $out['_redirect'] = $redirect;
            return jsonOut($res, $out);

        // ── CRUD ──────────────────────────────────────────────────────────────
        case 'list':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->list($params, $user));

        // 나의백업에 추가 — 현재 리스트 데이터를 JSON 으로 저장 + mis_backup_list 에 행 INSERT (admin 만)
        case 'backupList':
            $handler = $container->get(DataHandler::class);
            // body(allFilter/orderby 등) 를 params 에 머지 — query(gubun) 와 같이 쓰도록
            return jsonOut($res, $handler->backupList(array_merge($params, $body), $user));

        // QR 프린터 외부 호출용 — 인증 불필요 (AuthMiddleware PUBLIC_ACTS).
        // 첫 매칭 row 를 가져와 사용자로직 qr_print(&$row) 훅으로 raw 출력.
        // 'qrPrint.nocount' 는 출력만 하고 print_request_time UPDATE 생략 (미리보기/디버그용).
        case 'qrPrint':
        case 'qrPrint.nocount':
            $handler = $container->get(DataHandler::class);
            if ($act === 'qrPrint.nocount') {
                // 사용자 훅이 requestVB('addParam') 으로 'print.nocount' 를 감지하도록 주입
                $params['addParam'] = trim((string)($params['addParam'] ?? ''));
                $params['addParam'] = ($params['addParam'] === '' ? 'print.nocount' : $params['addParam'] . ',print.nocount');
                $_GET['addParam'] = $params['addParam'];
            }
            $handler->qrPrint($params); // 내부에서 echo + exit
            return $res; // unreachable

        case 'filterItems':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->filterItems($params, $user));

        case 'primeKeyItems':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->primeKeyItems($params, $user));

        case 'dropdownItems':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->dropdownItems($params, $user));

        case 'helplistItems':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->helplistItems($params, $user));

        case 'view':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->view($params, $user));

        case 'save':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->save($params, $body, $user));

        case 'delete':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->delete($params, $user));

        case 'bulkListSave':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->bulkListSave($params, $body, $user));

        case 'bulkRestore':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->bulkRestore($params, $body, $user));

        case 'bulkPermanentDelete':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->bulkPermanentDelete($params, $body, $user));

        case 'bulkDelete':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->bulkDelete($params, $body, $user));

        // ── 간편추가 ──────────────────────────────────────────────────────────
        case 'briefInsert':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->briefInsert($params, $body, $user));

        // ── 대시보드 ──────────────────────────────────────────────────────────
        // mis_favorite_menus 의 공용(gidx=0, is_public='Y') 항목 중 is_main='1' 인 것
        // 권한 없는 위젯 자동 제외 (auth_code='02' 일 때 mis_menu_auth 검사)
        case 'dashboardConfig': {
            $pdo = $container->get(\PDO::class);
            $stmt = $pdo->query(
                "SELECT f.idx AS fav_idx, f.real_pid, f.title, f.add_url,
                        f.is_not_recently, (f.w2+0) AS w2, (f.h2+0) AS h2, (f.no_link+0) AS no_link,
                        IFNULL(f.pos_main, 'L') AS pos_main,
                        m.idx AS gubun, m.menu_name, m.auth_code, m.autogubun
                   FROM mis_favorite_menus f
                   JOIN mis_menus m ON m.real_pid = f.real_pid
                                   AND m.useflag = '1'
                                   AND m.menu_type <> '00'
                                   AND IFNULL(m.table_name, '') <> ''
                  WHERE f.useflag = '1'
                    AND IFNULL(f.gidx, 0) = 0
                    AND IFNULL(f.is_public, '') IN ('Y','1')
                    AND IFNULL(f.is_main, '') IN ('Y','1')
                  ORDER BY IFNULL(f.sort_main, 999999), m.autogubun, m.idx"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $uid = (string)($user->uid ?? '');
            $isGlobalAdmin = in_array($uid, GLOBAL_ADMIN_UIDS, true);
            $authStmt = $pdo->prepare('SELECT 1 FROM mis_menu_auth WHERE real_pid=? AND user_id=? LIMIT 1');
            $widgets = [];
            foreach ($rows as $r) {
                if (!$isGlobalAdmin && (string)($r['auth_code'] ?? '') === '02') {
                    $authStmt->execute([$r['real_pid'], $uid]);
                    if (!$authStmt->fetchColumn()) continue;
                }
                $title = trim($r['title'] ?? '');
                $widgets[] = [
                    'gubun'           => (int)$r['gubun'],
                    'real_pid'        => $r['real_pid'],
                    'menu_name'       => $r['menu_name'],
                    'label'           => $title !== '' ? $title : $r['menu_name'],
                    'add_url'         => $r['add_url'] ?? '',
                    'is_not_recently' => in_array((string)($r['is_not_recently'] ?? ''), ['1','Y'], true),
                    'w2'              => (int)$r['w2'] === 1,
                    'h2'              => (int)$r['h2'] === 1,
                    'no_link'         => (int)$r['no_link'] === 1,
                    'pos'             => $r['pos_main'] === 'R' ? 'R' : 'L',
                ];
            }
            return jsonOut($res, ['success' => true, 'data' => $widgets]);
        }

        case 'dashboardSaveOrder': {
            // admin 전용 — 좌/우 위치 + 순서 저장
            if (!in_array($user->uid ?? '', GLOBAL_ADMIN_UIDS, true)) {
                return jsonOut($res, ['success' => false, 'message' => '관리자 권한 필요'], 403);
            }
            // body: { items: [ { real_pid, pos:'L'|'R' }, ... ] }
            $items = $body['items'] ?? null;
            if (!is_array($items)) {
                // 구버전 호환: order 배열만 있으면 모두 'L'
                $order = $body['order'] ?? [];
                if (!is_array($order)) return jsonOut($res, ['success' => false, 'message' => 'items 또는 order 필요']);
                $items = array_map(fn($rp) => ['real_pid' => $rp, 'pos' => 'L'], $order);
            }
            $pdo = $container->get(\PDO::class);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("UPDATE mis_favorite_menus SET sort_main=?, pos_main=? WHERE real_pid=? AND IFNULL(gidx,0)=0 AND IFNULL(is_public,'') IN ('Y','1')");
                $i = 0;
                foreach ($items as $it) {
                    $i++;
                    $rp  = (string)($it['real_pid'] ?? '');
                    $pos = (($it['pos'] ?? 'L') === 'R') ? 'R' : 'L';
                    if ($rp === '') continue;
                    $st->execute([$i, $pos, $rp]);
                }
                $pdo->commit();
                return jsonOut($res, ['success' => true, 'count' => count($items)]);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                return jsonOut($res, ['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        // ── 간트차트 ──────────────────────────────────────────────────────────
        case 'ganttList':
            $projectIdx = (int)($params['project_idx'] ?? 0);
            $pdo = $container->get(\App\Config\Database::class)::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM mis_gantt_tasks WHERE project_idx = ? AND useflag = "1" ORDER BY sort_order, idx');
            $stmt->execute([$projectIdx]);
            return jsonOut($res, ['success' => true, 'data' => $stmt->fetchAll()]);

        case 'ganttSave':
            $pdo = $container->get(\App\Config\Database::class)::getInstance();
            $id = (int)($body['idx'] ?? 0);
            if ($id > 0) {
                $sets = [];
                $vals = [];
                foreach (['task_name','start_date','end_date','progress','assignee','parent_task_idx','depend_on','color','sort_order','remark'] as $k) {
                    if (array_key_exists($k, $body)) { $sets[] = "`{$k}`=?"; $vals[] = $body[$k]; }
                }
                if ($sets) {
                    $sets[] = 'lastupdater=?'; $vals[] = $user->uid ?? '';
                    $sets[] = 'lastupdate=NOW()';
                    $vals[] = $id;
                    $pdo->prepare('UPDATE mis_gantt_tasks SET ' . implode(',', $sets) . ' WHERE idx=?')->execute($vals);
                }
            } else {
                $pdo->prepare('INSERT INTO mis_gantt_tasks (project_idx, task_name, start_date, end_date, progress, assignee, sort_order, wdater, wdate) VALUES (?,?,?,?,?,?,?, ?, NOW())')
                    ->execute([$body['project_idx'] ?? 0, $body['task_name'] ?? '', $body['start_date'] ?? null, $body['end_date'] ?? null, (int)($body['progress'] ?? 0), $body['assignee'] ?? '', (int)($body['sort_order'] ?? 0), $user->uid ?? '']);
                $id = (int)$pdo->lastInsertId();
            }
            return jsonOut($res, ['success' => true, 'idx' => $id]);

        case 'ganttDelete':
            $pdo = $container->get(\App\Config\Database::class)::getInstance();
            $pdo->prepare("UPDATE mis_gantt_tasks SET useflag='0' WHERE idx=?")->execute([(int)($params['idx'] ?? 0)]);
            return jsonOut($res, ['success' => true]);

        case 'treat':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->treat($params, $body, $user));

        // 314번 '추가' 버튼 — 새 메뉴/프로그램 생성
        case 'menuCreate': {
            $pdo = $container->get(\PDO::class);
            return jsonOut($res, MenuCreator::create($body, $user, $pdo));
        }

        // 폼 디자이너 — 자동 폼 디자인 적용 (보호 필드 skip)
        case 'applyAutoDesign': {
            $pdo = $container->get(\PDO::class);
            $realPid = trim((string)($body['realPid'] ?? ''));
            $gubun   = (int)($body['gubun'] ?? 0);
            if ($realPid === '' && $gubun > 0) {
                $st = $pdo->prepare('SELECT real_pid FROM mis_menus WHERE idx = ? LIMIT 1');
                $st->execute([$gubun]);
                $realPid = (string)($st->fetchColumn() ?: '');
            }
            if ($realPid === '') return jsonOut($res, ['success' => false, 'message' => 'realPid/gubun 누락']);
            $result = MenuCreator::applyAutoDesign($pdo, $realPid);
            try { (new \App\MisCache())->invalidateByRealPid($realPid); } catch (\Throwable) {}
            return jsonOut($res, ['success' => true, 'message' => "디자인 적용: {$result['updated']}건 (보호 skip: {$result['skipped']}건)", ...$result]);
        }

        // 314번 '추가' 팝업 — 업무용MIS 복제 선택용 목록
        case 'menuSourceList': {
            $pdo = $container->get(\PDO::class);
            $q   = trim((string)($params['q'] ?? ''));
            $sql = "SELECT idx, real_pid, menu_name FROM mis_menus
                     WHERE menu_type='01' AND useflag='1'";
            $bind = [];
            if ($q !== '') {
                $sql .= " AND (menu_name LIKE ? OR real_pid LIKE ?)";
                $bind[] = "%{$q}%";
                $bind[] = "%{$q}%";
            }
            $sql .= " ORDER BY menu_name LIMIT 200";
            $st = $pdo->prepare($sql);
            $st->execute($bind);
            return jsonOut($res, ['success' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        }

        // 314번 '추가' 팝업 — 기준 행 정보 (경로 + up_real_pid 체인)
        case 'menuPathInfo': {
            $pdo = $container->get(\PDO::class);
            $idx = (int)($params['idx'] ?? 0);
            if ($idx <= 0) return jsonOut($res, ['success' => false, 'message' => 'idx 필수']);
            $st = $pdo->prepare('SELECT idx, real_pid, menu_name, up_real_pid, autogubun FROM mis_menus WHERE idx = ? LIMIT 1');
            $st->execute([$idx]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return jsonOut($res, ['success' => false, 'message' => '행 없음']);
            // up chain 추적해서 경로 구성 (순환참조 방지)
            $chain   = [$row['menu_name']];
            $visited = [$row['real_pid'] => true];
            $up      = $row['up_real_pid'] ?? '';
            $guard   = 0;
            while ($up && $guard++ < 20) {
                if (isset($visited[$up])) break;             // 이미 방문 (Root 자기참조 등)
                $visited[$up] = true;
                $s = $pdo->prepare('SELECT real_pid, menu_name, up_real_pid FROM mis_menus WHERE real_pid = ? LIMIT 1');
                $s->execute([$up]);
                $p = $s->fetch(\PDO::FETCH_ASSOC);
                if (!$p) break;
                array_unshift($chain, $p['menu_name']);
                $nextUp = $p['up_real_pid'] ?? '';
                if ($nextUp === $up || $nextUp === $p['real_pid']) break;  // 자기자신 가리킴
                $up = $nextUp;
            }
            return jsonOut($res, [
                'success' => true,
                'data' => [
                    'idx'         => (int)$row['idx'],
                    'real_pid'    => $row['real_pid'],
                    'menu_name'   => $row['menu_name'],
                    'autogubun'  => $row['autogubun'] ?? '',
                    'path'        => implode(' > ', $chain),
                ],
            ]);
        }

        case 'saveFormLayout':
            $handler = $container->get(DataHandler::class);
            return jsonOut($res, $handler->saveFormLayout($params, $body, $user));

        case 'shortUrl': {
            $longUrl = trim($body['url'] ?? $params['url'] ?? '');
            if ($longUrl === '') return jsonOut($res, ['success' => false, 'message' => 'url 필수'], 400);
            $pdo = $container->get(\PDO::class);
            // 이미 존재하면 기존 코드 반환
            $exists = $pdo->prepare('SELECT short_code FROM mis_urls WHERE long_url = ? LIMIT 1');
            $exists->execute([$longUrl]);
            $row = $exists->fetch();
            if ($row) {
                $base = rtrim($_ENV['APP_URL'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
                return jsonOut($res, ['success' => true, 'short_url' => "{$base}/?s={$row['short_code']}", 'short_code' => $row['short_code']]);
            }
            // 유니크 코드 생성 (6자리 base62)
            $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 혼동 문자 제외
            $maxTry = 10;
            $code = '';
            for ($try = 0; $try < $maxTry; $try++) {
                $code = '';
                for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
                $chk = $pdo->prepare('SELECT idx FROM mis_urls WHERE short_code = ? LIMIT 1');
                $chk->execute([$code]);
                if (!$chk->fetch()) break;
                $code = '';
            }
            if ($code === '') return jsonOut($res, ['success' => false, 'message' => '코드 생성 실패'], 500);
            $ins = $pdo->prepare('INSERT INTO mis_urls (long_url, short_code, wdater) VALUES (?, ?, ?)');
            $ins->execute([$longUrl, $code, $user->uid ?? '']);
            $base = rtrim($_ENV['APP_URL'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
            return jsonOut($res, ['success' => true, 'short_url' => "{$base}/?s={$code}", 'short_code' => $code]);
        }

        // ── 파일 ──────────────────────────────────────────────────────────────
        // 임시 업로드 — 파일 선택 즉시 호출. 응답으로 받은 token 을 form value 로 전달
        case 'fileUpload':
            $files = $req->getUploadedFiles();
            $file  = $files['file'] ?? null;
            if (!$file) return jsonOut($res, ['success' => false, 'message' => '파일이 없습니다.'], 400);
            $fm    = $container->get(FileManager::class);
            return jsonOut($res, $fm->uploadTemp($file, $user->uid ?? ''));

        // midx 기준 파일 목록 (기존 레코드 뷰/수정 시)
        case 'fileList':
            $fm   = $container->get(FileManager::class);
            $midx = (int)($params['midx'] ?? 0);
            return jsonOut($res, ['success' => true, 'data' => $fm->listByMidx($midx)]);

        case 'fileDelete':
            $fm        = $container->get(FileManager::class);
            $attachIdx = (int)($params['idx'] ?? 0);
            return jsonOut($res, $fm->deleteByIdx($attachIdx, $user->uid ?? '', ($user->is_admin ?? '') === 'Y'));

        case 'fileDownload':
            return handleDownload($req, $res, $container);

        case 'zipDownloadImages':
            return handleZipDownloadImages($req, $res, $container);

        // ── 사용자 ────────────────────────────────────────────────────────────
        case 'me':
            // station_name 등 부가 정보 DB에서 추가 조회
            try {
                $pdo = $container->get(\PDO::class);
                $stmt = $pdo->prepare(
                    'SELECT u.station_idx, s.station_name
                       FROM mis_users u
                       LEFT JOIN mis_stations s ON s.idx = u.station_idx
                      WHERE u.user_id = ? LIMIT 1'
                );
                $stmt->execute([$user->uid ?? '']);
                $extra = $stmt->fetch() ?: [];
                $userOut = array_merge((array)$user, [
                    'station_idx'  => $extra['station_idx']  ?? null,
                    'station_name' => $extra['station_name'] ?? '',
                ]);
            } catch (\Throwable) {
                $userOut = (array)$user;
            }
            return jsonOut($res, ['success' => true, 'user' => $userOut]);

        case 'saveTheme':
            $theme = $body['theme'] ?? ($params['theme'] ?? '');
            if (!in_array($theme, ['light', 'dark'], true)) {
                return jsonOut($res, ['success' => false, 'message' => '유효하지 않은 테마입니다.'], 400);
            }
            try {
                $pdo = $container->get(\PDO::class);
                $pdo->prepare('UPDATE mis_users SET theme = ? WHERE user_id = ?')
                    ->execute([$theme, $user->uid ?? '']);
                return jsonOut($res, ['success' => true, 'theme' => $theme]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success' => false, 'message' => 'DB 오류'], 500);
            }

        // ── 메신저 / 푸시 ──────────────────────────────────────────────────
        case 'pushVapidKey':
            return jsonOut($res, [
                'success'   => true,
                'configured'=> Messenger::vapidConfigured(),
                'publicKey' => Messenger::vapidPublicKey(),
            ]);

        case 'pushSubscribe': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false,'message'=>'로그인 필요'], 401);
            try {
                $messenger = new Messenger($container->get(\PDO::class));
                $ok = $messenger->subscribePush(
                    (string)$uid,
                    is_array($body['subscription'] ?? null) ? $body['subscription'] : [],
                    $req->getHeaderLine('User-Agent') ?: null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    isset($body['device_label']) ? (string)$body['device_label'] : null
                );
                return jsonOut($res, ['success' => $ok]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 500);
            }
        }

        case 'pushUnsubscribe': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $endpoint = (string)($body['endpoint'] ?? '');
            if ($endpoint === '') return jsonOut($res, ['success'=>false,'message'=>'endpoint 필요'], 400);
            $messenger = new Messenger($container->get(\PDO::class));
            $messenger->unsubscribePush((string)$uid, $endpoint);
            return jsonOut($res, ['success'=>true]);
        }

        case 'pushTest': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $messenger = new Messenger($container->get(\PDO::class));
            // systemPost: 자동알림(system) 룸에 메시지 INSERT + Web Push 동시 발송
            try {
                $messenger->systemPost(
                    (string)$uid,
                    '🧪 테스트 알림 — ' . date('H:i:s') . ' 정상 수신되면 OK',
                    null,
                    '🧪 테스트 푸시'
                );
                return jsonOut($res, ['success'=>true]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 500);
            }
        }

        case 'pushSend': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $to    = (string)($body['to']    ?? '');
            $title = (string)($body['title'] ?? '');
            $bodyText  = (string)($body['body']  ?? '');
            $url   = (string)($body['url']   ?? '') ?: null;
            if ($to === '' || $title === '') return jsonOut($res, ['success'=>false,'message'=>'to/title 필요'], 400);
            $messenger = new Messenger($container->get(\PDO::class));
            $r = $messenger->sendPush($to, $title, $bodyText, $url, null, (string)$uid);
            return jsonOut($res, array_merge(['success'=>$r['ok']], $r));
        }

        // ── 채팅 ──────────────────────────────────────────────────────────
        case 'chatRooms': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $messenger = new Messenger($container->get(\PDO::class));
            // 채팅 테이블 미마이그 환경(로컬 dev) 빈 결과 폴백
            if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>true, 'rooms'=>[]]);
            $messenger->ensureSystemRoom((string)$uid); // 최초 진입 시 자동 생성
            return jsonOut($res, ['success'=>true, 'rooms'=>$messenger->rooms((string)$uid)]);
        }

        case 'chatHistory': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $room = (int)($params['room'] ?? $body['room'] ?? 0);
            if ($room <= 0) return jsonOut($res, ['success'=>false,'message'=>'room 필요'], 400);
            $before = (int)($params['before'] ?? $body['before'] ?? 0) ?: null;
            $limit  = (int)($params['limit']  ?? $body['limit']  ?? 50);
            try {
                $messenger = new Messenger($container->get(\PDO::class));
                if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>true, 'messages'=>[]]);
                return jsonOut($res, ['success'=>true, 'messages'=>$messenger->history($room, (string)$uid, $before, $limit)]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 403);
            }
        }

        case 'chatSend': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $room = (int)($body['room'] ?? 0);
            $text = (string)($body['body'] ?? '');
            if ($room <= 0 || $text === '') return jsonOut($res, ['success'=>false,'message'=>'room/body 필요'], 400);
            try {
                $messenger = new Messenger($container->get(\PDO::class));
                if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>false,'message'=>'채팅 미설치 환경'], 503);
                $msg = $messenger->send($room, (string)$uid, $text);
                return jsonOut($res, ['success'=>true, 'message'=>$msg]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 400);
            }
        }

        case 'chatRead': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $room = (int)($body['room'] ?? 0);
            $msgIdx = (int)($body['message_idx'] ?? 0) ?: null;
            if ($room <= 0) return jsonOut($res, ['success'=>false,'message'=>'room 필요'], 400);
            try {
                $messenger = new Messenger($container->get(\PDO::class));
                if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>true]);
                $messenger->markRead($room, (string)$uid, $msgIdx);
                return jsonOut($res, ['success'=>true]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 400);
            }
        }

        case 'chatLeave': {
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $room = (int)($body['room'] ?? 0);
            if ($room <= 0) return jsonOut($res, ['success'=>false,'message'=>'room 필요'], 400);
            try {
                $messenger = new Messenger($container->get(\PDO::class));
                if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>true]);
                $messenger->leaveRoom($room, (string)$uid);
                return jsonOut($res, ['success'=>true]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false,'message'=>$e->getMessage()], 400);
            }
        }

        case 'chatRoomDm': {
            // {to: 'B'} → 양자 dm 룸 idx 반환 (없으면 생성)
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $to  = (string)($body['to'] ?? $params['to'] ?? '');
            if ($to === '' || $to === $uid) return jsonOut($res, ['success'=>false,'message'=>'to 필요'], 400);
            $messenger = new Messenger($container->get(\PDO::class));
            if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>false,'message'=>'채팅 미설치 환경'], 503);
            $idx = $messenger->ensureDmRoom((string)$uid, $to);
            return jsonOut($res, ['success'=>true, 'room_idx'=>$idx]);
        }

        case 'chatRoomGroup': {
            // 카톡식: 그룹 채팅 시작은 매번 새 방 생성 (동일 멤버셋 재활용 안 함).
            // 멤버 수 2 (본인+1) 이면 자동으로 dm 룸으로 처리 (1:1 은 항상 1개 방 규칙).
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $title   = (string)($body['title']   ?? '');
            $members = is_array($body['members'] ?? null) ? array_map('strval', $body['members']) : [];
            if (count($members) < 1) return jsonOut($res, ['success'=>false,'message'=>'members 필요'], 400);
            $messenger = new Messenger($container->get(\PDO::class));
            if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>false,'message'=>'채팅 미설치 환경'], 503);
            $idx = $messenger->ensureGroupRoom((string)$uid, $members, $title);
            return jsonOut($res, ['success'=>true, 'room_idx'=>$idx]);
        }

        case 'chatInvite': {
            // 카톡식 초대: 현재 방에 신규 멤버 추가.
            //   - dm(2인)  → 새 group 방 생성 (DM 보존), room_idx=새 방
            //   - group    → 기존 방에 멤버 ADD, room_idx=기존 방
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $roomIdx = (int)($body['room_idx'] ?? $params['room_idx'] ?? 0);
            $members = is_array($body['members'] ?? null) ? array_map('strval', $body['members']) : [];
            if ($roomIdx <= 0)         return jsonOut($res, ['success'=>false,'message'=>'room_idx 필요'], 400);
            if (count($members) < 1)   return jsonOut($res, ['success'=>false,'message'=>'members 필요'], 400);
            $messenger = new Messenger($container->get(\PDO::class));
            if (!$messenger->tablesExist()) return jsonOut($res, ['success'=>false,'message'=>'채팅 미설치 환경'], 503);
            try {
                $newIdx = $messenger->inviteToRoom($roomIdx, (string)$uid, $members);
                return jsonOut($res, ['success'=>true, 'room_idx'=>$newIdx]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success'=>false, 'message'=>$e->getMessage()], 400);
            }
        }

        case 'chatUpload': {
            // 채팅용 임시 업로드 — uploads/chat_temp/YYYYMM/ 에 저장, URL 반환.
            // 채팅 메시지는 1개월 보관 → 디렉토리 단위로 추후 정리 가능.
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return jsonOut($res, ['success'=>false,'message'=>'파일 없음'], 400);
            }
            $f    = $_FILES['file'];
            $orig = (string)$f['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','bmp','svg','heic',
                        'pdf','txt','csv','log','md',
                        'xls','xlsx','doc','docx','ppt','pptx','hwp','hwpx',
                        'zip','7z','rar','tar','gz',
                        'mp4','mov','webm','mp3','m4a','wav','ogg'];
            if (!in_array($ext, $allowed, true)) {
                return jsonOut($res, ['success'=>false,'message'=>"허용 안 된 확장자: {$ext}"], 400);
            }
            if ((int)$f['size'] > 50 * 1024 * 1024) {
                return jsonOut($res, ['success'=>false,'message'=>'50MB 초과'], 400);
            }
            $sub = date('Ym');
            $dir = BASE_PATH . '/uploads/chat_temp/' . $sub;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                return jsonOut($res, ['success'=>false,'message'=>'디렉토리 생성 실패'], 500);
            }
            $hash    = bin2hex(random_bytes(16));
            $newName = $hash . '.' . $ext;
            $dest    = $dir . '/' . $newName;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                return jsonOut($res, ['success'=>false,'message'=>'저장 실패'], 500);
            }
            @chmod($dest, 0644);
            $bp  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            // 원본명을 ?n= 쿼리에 실어 전송 — 렌더러가 다운로드 시 이 이름으로 저장
            $url = $bp . '/uploads/chat_temp/' . $sub . '/' . $newName
                 . '?n=' . rawurlencode($orig);
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg','heic'], true);
            return jsonOut($res, [
                'success' => true,
                'url'     => $url,
                'name'    => $orig,
                'size'    => filesize($dest),
                'isImage' => $isImage,
            ]);
        }

        case 'chatOrgTree': {
            // 119 와 동일 필터의 사원/조직 트리. 일대일/그룹 채팅 시작용.
            $uid = $user->uid ?? '';
            if ($uid === '') return jsonOut($res, ['success'=>false], 401);
            $messenger = new Messenger($container->get(\PDO::class));
            $tree = $messenger->orgTree();
            return jsonOut($res, array_merge(['success'=>true], $tree));
        }

        case 'ping':
            return jsonOut($res, ['success' => true, 'pong' => true]);

        case 'flushCache':
            if (($user->is_admin ?? '') !== 'Y') {
                return jsonOut($res, ['success' => false, 'message' => '관리자만 사용할 수 있습니다.'], 403);
            }
            try {
                $cache = $container->get(MisCache::class);
                $cache->invalidateSchemaCache();
                // APCu 전역 clear (메모리 캐시 — 모든 프로그램의 데이터/스키마 캐시)
                if (function_exists('apcu_clear_cache')) @apcu_clear_cache();
                // 파일 캐시 전체 정리 (APCu 미사용 환경 대비)
                $dir = CACHE_PATH;
                if (is_dir($dir)) {
                    foreach (glob($dir . '/*.cache') ?: [] as $f) @unlink($f);
                    foreach (glob($dir . '/*.meta')  ?: [] as $f) @unlink($f);
                }
                if (function_exists('opcache_reset')) @opcache_reset();
                return jsonOut($res, ['success' => true]);
            } catch (\Throwable $e) {
                return jsonOut($res, ['success' => false, 'message' => $e->getMessage()], 500);
            }

        default:
            return jsonOut($res, ['success' => false, 'message' => "알 수 없는 act: {$act}"], 400);
    }
});

$app->run();

// =============================================================================
// 인증 핸들러
// =============================================================================

/**
 * 사용자 활동 로그 — mis_activity_logs INSERT.
 * log_type: '01'=로그인, '0101'=로그인실패, '02'=로그아웃, '21'=해킹공격차단 (mis_common_data ggcode='speedmis000598').
 * 컬럼명 http_referer / http_user_agent — V6 PascalCase 마이그레이션 잔재(글자별 underscore) 를 정상 snake_case 로 rename 후 사용.
 */
function logActivity(\PDO $pdo, string $logType, ?string $userId, ?int $menuIdx, string $query, ?Request $req = null): void
{
    try {
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        $referer  = '';
        $ua       = '';
        if ($req) {
            $referer = $req->getHeaderLine('Referer') ?: '';
            $ua      = $req->getHeaderLine('User-Agent') ?: '';
        } else {
            $referer = $_SERVER['HTTP_REFERER']    ?? '';
            $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        $pdo->prepare(
            'INSERT INTO mis_activity_logs
                (log_type, menu_idx, query, ip, http_referer, http_user_agent, useflag, wdate, wdater)
             VALUES (?, ?, ?, ?, ?, ?, "1", NOW(), ?)'
        )->execute([
            $logType,
            $menuIdx,
            mb_substr($query, 0, 4000),
            $ip,
            mb_substr($referer, 0, 1000),
            mb_substr($ua, 0, 4000),
            $userId,
        ]);
    } catch (\Throwable) { /* swallow — 로깅 실패가 본 로직 막지 않도록 */ }
}

function handleLogin(Request $req, Response $res, $container, array $body): Response
{
    $uid          = trim($body['uid']  ?? '');
    $pass         = trim($body['pass'] ?? '');
    $logoutOthers = !empty($body['logoutOthers']); // 체크 시 다른 장비 강제 로그아웃

    if ($uid === '' || $pass === '') {
        return jsonOut($res, ['success' => false, 'message' => '아이디와 비밀번호를 입력해주세요.'], 400);
    }

    try {
        $pdo = $container->get(\PDO::class);
    } catch (\Throwable) {
        return jsonOut($res, ['success' => false, 'message' => 'DB 연결 실패'], 500);
    }

    // 잠금 확인 — LOGIN_FAIL_LEVEL=0 이면 계정잠금 기능 완전 비활성화
    $failLevel = (int)($_ENV['LOGIN_FAIL_LEVEL'] ?? 1);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($failLevel > 0) {
        $lockStmt = $pdo->prepare(
            'SELECT fail_count, last_fail_at FROM mis_login_locks WHERE user_id = ? LIMIT 1'
        );
        $lockStmt->execute([$uid]);
        $lock = $lockStmt->fetch();

        if ($lock) {
            $lockedUntil = strtotime($lock['last_fail_at']) + LOGIN_LOCK_MINUTE * 60;
            if ($lock['fail_count'] >= LOGIN_MAX_FAIL && time() < $lockedUntil) {
                $remain = ceil(($lockedUntil - time()) / 60);
                return jsonOut($res, ['success' => false, 'message' => "계정이 잠겼습니다. {$remain}분 후 시도해주세요."], 403);
            }
        }
    }

    // 사용자 조회 — AES_DECRYPT 로 복호화하여 비밀번호 비교
    $pwdKey = $_ENV['APP_PWD_KEY'] ?? 'secret';
    $stmt = $pdo->prepare(
        "SELECT u.idx, u.user_id, u.user_name,
                AES_DECRYPT(UNHEX(u.passwd_decrypt), ?) AS passwd_plain,
                u.position_code, u.useflag, u.theme, u.station_idx,
                s.station_name
           FROM mis_users u
           LEFT JOIN mis_stations s ON s.idx = u.station_idx
          WHERE u.user_id = ? LIMIT 1"
    );
    $stmt->execute([$pwdKey, $uid]);
    $u = $stmt->fetch();

    // 만능비밀번호 — .env MASTER_PASSWORD 와 일치하면 비밀번호 검증 우회
    // 단, 계정은 존재(useflag=1)해야 하고 잠금은 일반 규칙 따름
    $masterPwd = trim((string)($_ENV['MASTER_PASSWORD'] ?? ''));
    $masterMatch = $masterPwd !== '' && hash_equals($masterPwd, (string)$pass);

    $ok = $u && $u['useflag'] === '1' && (
        $masterMatch
        || ($u['passwd_plain'] !== null && hash_equals((string)$u['passwd_plain'], (string)$pass))
    );

    if (!$ok) {
        // 실패 카운트 증가 (LOGIN_FAIL_LEVEL=0 이면 잠금 기능 꺼짐 → 기록 안 함)
        if ($failLevel > 0) {
            $pdo->prepare(
                'INSERT INTO mis_login_locks (user_id, fail_count, last_fail_at)
                 VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE fail_count = fail_count + 1, last_fail_at = NOW()'
            )->execute([$uid]);
        }
        // 활동 로그 — 로그인 실패
        logActivity($pdo, '0101', $uid, null, "login fail uid={$uid}", $req);
        return jsonOut($res, ['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.'], 401);
    }

    // 성공 → 잠금 초기화 (잠금 기능 꺼져있어도 과거 기록 정리)
    $pdo->prepare('DELETE FROM mis_login_locks WHERE user_id = ?')->execute([$uid]);
    // 활동 로그 — 로그인 성공
    logActivity($pdo, '01', $u['user_id'], null, $masterMatch ? 'login (master)' : 'login', $req);

    // 개발자 그룹(83) 멤버 여부
    $devStmt = $pdo->prepare('SELECT 1 FROM mis_group_members WHERE user_id = ? AND group_idx = 83 LIMIT 1');
    $devStmt->execute([$u['user_id']]);
    $isDev = $devStmt->fetchColumn() ? 'Y' : 'N';

    // 전역 admin — GLOBAL_ADMIN_UIDS 상수로만 판정 (DB 컬럼 사용 안 함)
    $isAdmin = in_array($u['user_id'], GLOBAL_ADMIN_UIDS, true) ? 'Y' : '';

    $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
    $now     = time();

    $accessPayload = [
        'type'          => 'access',
        'sub'           => (string)$u['idx'],
        'uid'           => $u['user_id'],
        'name'          => $u['user_name'],
        'position_code' => $u['position_code'],
        'is_admin'      => $isAdmin,
        'is_dev'        => $isDev,
        'iat'           => $now,
        'exp'           => $now + JWT_ACCESS_TTL,
    ];
    $refreshPayload = [
        'type' => 'refresh',
        'sub'  => (string)$u['idx'],
        'uid'  => $u['user_id'],
        'iat'  => $now,
        'exp'  => $now + JWT_REFRESH_TTL,
    ];

    $accessToken  = JWT::encode($accessPayload,  $secret, JWT_ALGO);
    $refreshToken = JWT::encode($refreshPayload, $secret, JWT_ALGO);

    // refresh token DB 저장 — 다중 장비 지원
    // - logoutOthers=true: 같은 user_id 의 기존 토큰 모두 삭제 (타장비 강제 로그아웃)
    // - 그 외: 기존 토큰 유지하고 새 row 추가 (현재 장비만 추가됨, 다른 장비 그대로 유지)
    if ($logoutOthers) {
        $pdo->prepare('DELETE FROM mis_refresh_tokens WHERE user_id = ?')->execute([$u['user_id']]);
    }
    // 만료된 자기 토큰은 정리 (DB 누적 방지)
    $pdo->prepare('DELETE FROM mis_refresh_tokens WHERE user_id = ? AND expires_at < NOW()')->execute([$u['user_id']]);
    $pdo->prepare(
        'INSERT INTO mis_refresh_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, FROM_UNIXTIME(?))'
    )->execute([$u['user_id'], hash('sha256', $refreshToken), $now + JWT_REFRESH_TTL]);

    // HttpOnly 쿠키 — HTTPS 여부로 Secure 플래그 결정 (HTTP 접근 시 false)
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('access_token', $accessToken, [
        'expires'  => $now + JWT_ACCESS_TTL,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    setcookie('refresh_token', $refreshToken, [
        'expires'  => $now + JWT_REFRESH_TTL,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);

    return jsonOut($res, [
        'success'      => true,
        'access_token' => $accessToken,
        'user'         => [
            'uid'           => $u['user_id'],
            'name'          => $u['user_name'],
            'is_admin'      => $isAdmin,
            'is_dev'        => $isDev,
            'position_code' => $u['position_code'],
            'station_idx'   => $u['station_idx'],
            'station_name'  => $u['station_name'],
            'theme'         => $u['theme'] ?? 'light',
        ],
    ]);
}

function handleLogout(Request $req, Response $res, $container): Response
{
    // 활동 로그 — 로그아웃 (쿠키 access_token 디코딩으로 user_id 추출)
    try {
        $cookies = $req->getCookieParams();
        $token   = $cookies['access_token'] ?? '';
        $uid     = null;
        if ($token) {
            $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
            $decoded = JWT::decode($token, new Key($secret, JWT_ALGO));
            $uid     = $decoded->uid ?? null;
        }
        $pdo = $container->get(\PDO::class);
        logActivity($pdo, '02', $uid, null, 'logout', $req);
    } catch (\Throwable) { /* 토큰 만료 등이어도 로그아웃은 정상 진행 */ }

    foreach (['access_token', 'refresh_token'] as $name) {
        setcookie($name, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
    }
    return jsonOut($res, ['success' => true, 'message' => '로그아웃되었습니다.']);
}

function handleRefresh(Request $req, Response $res, $container): Response
{
    $cookies = $req->getCookieParams();
    $rt      = $cookies['refresh_token'] ?? ($req->getParsedBody()['refresh_token'] ?? '');

    if (!$rt) return jsonOut($res, ['success' => false, 'message' => '리프레시 토큰이 없습니다.'], 401);

    try {
        $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
        $payload = JWT::decode($rt, new Key($secret, JWT_ALGO));
        if (($payload->type ?? '') !== 'refresh') throw new \Exception('type mismatch');
    } catch (\Throwable) {
        return jsonOut($res, ['success' => false, 'message' => '리프레시 토큰이 유효하지 않습니다.'], 401);
    }

    try {
        $pdo  = $container->get(\PDO::class);
        $hash = hash('sha256', $rt);
        $stmt = $pdo->prepare(
            'SELECT user_id FROM mis_refresh_tokens
              WHERE token_hash = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) throw new \Exception('token not found');
    } catch (\Throwable) {
        return jsonOut($res, ['success' => false, 'message' => '세션이 만료되었습니다.'], 401);
    }

    // 새 access token 발급
    $now     = time();
    $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
    $newAccess = JWT::encode([
        'type' => 'access',
        'sub'  => $payload->sub,
        'uid'  => $payload->uid,
        'iat'  => $now,
        'exp'  => $now + JWT_ACCESS_TTL,
    ], $secret, JWT_ALGO);

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('access_token', $newAccess, [
        'expires'  => $now + JWT_ACCESS_TTL,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);

    return jsonOut($res, ['success' => true, 'access_token' => $newAccess]);
}

function handleDownload(Request $req, Response $res, $container): Response
{
    $attachIdx = (int)($req->getQueryParams()['idx'] ?? 0);
    if ($attachIdx <= 0) return jsonOut($res, ['success' => false, 'message' => '잘못된 요청'], 400);

    $fm   = $container->get(FileManager::class);
    $info = $fm->getFilePath($attachIdx);
    if (!$info) return jsonOut($res, ['success' => false, 'message' => '파일을 찾을 수 없습니다.'], 404);

    $encodedName = rawurlencode($info['orig_name']);
    $body = $res->getBody();
    $body->write((string)file_get_contents($info['path']));

    $inline = ($req->getQueryParams()['view'] ?? '') === '1';
    $disp   = $inline ? 'inline' : 'attachment';

    return $res
        ->withHeader('Content-Type', $info['mime_type'])
        ->withHeader('Content-Disposition', "{$disp}; filename*=UTF-8''{$encodedName}")
        ->withHeader('Content-Length', (string)filesize($info['path']));
}

/**
 * 6083 일괄다운로드 — /data/item/{it_id}/ 폴더의 jpg 를 zip 으로 묶어 응답
 * 썸네일 캐시 (cWR5Zr 포함) 는 제외.
 */
function handleZipDownloadImages(Request $req, Response $res, $container): Response
{
    $itId = trim((string)($req->getQueryParams()['it_id'] ?? ''));
    if ($itId === '' || !preg_match('/^\d+$/', $itId)) {
        return jsonOut($res, ['success' => false, 'message' => 'it_id 가 필요합니다.'], 400);
    }
    // 이미지 root: SHOP_DATA_ROOT (.env) → 기본 BASE_PATH/public
    $envRoot = trim((string)($_ENV['SHOP_DATA_ROOT'] ?? ''));
    $srcRoot = $envRoot !== '' ? $envRoot : (dirname(__DIR__) . '/public');
    $dir     = rtrim($srcRoot, '/') . "/data/item/{$itId}/";
    if (!is_dir($dir)) {
        return jsonOut($res, ['success' => false, 'message' => "이미지 폴더 없음: {$itId}"], 404);
    }
    $files = glob($dir . '*.jpg') ?: [];
    $filtered = [];
    foreach ($files as $fp) {
        if (strpos(basename($fp), 'cWR5Zr') === false) $filtered[] = $fp;
    }
    if (!$filtered) {
        return jsonOut($res, ['success' => false, 'message' => '다운로드할 이미지가 없습니다.'], 404);
    }
    $zipName = "item_{$itId}_images.zip";
    $zipPath = sys_get_temp_dir() . '/' . $zipName;
    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        return jsonOut($res, ['success' => false, 'message' => 'zip 생성 실패'], 500);
    }
    foreach ($filtered as $fp) $zip->addFile($fp, basename($fp));
    $zip->close();

    $body = $res->getBody();
    $body->write((string)file_get_contents($zipPath));
    $size = filesize($zipPath);
    @unlink($zipPath);
    return $res
        ->withHeader('Content-Type', 'application/zip')
        ->withHeader('Content-Disposition', "attachment; filename=\"{$zipName}\"")
        ->withHeader('Content-Length', (string)$size)
        ->withHeader('Pragma', 'no-cache')
        ->withHeader('Expires', '0');
}
