<?php

namespace App;

use DI\ContainerBuilder;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

class Bootstrap
{
    public static function createApp(): \Slim\App
    {
        // ── 환경변수 로드 ─────────────────────────────────────────────────────
        $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
        $dotenv->safeLoad();

        // SITE_ID 자동 갱신 (IP→도메인 전환 감지). SITE_ID_AUTO=pending 일 때만 동작.
        if (defined('BASE_PATH') && is_file(BASE_PATH . '/.env')) {
            SiteId::reconcile(BASE_PATH . '/.env');
        }

        // ── DI 컨테이너 ───────────────────────────────────────────────────────
        $builder = new ContainerBuilder();
        $builder->addDefinitions(self::definitions());
        $container = $builder->build();

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // ── 미들웨어 ──────────────────────────────────────────────────────────
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // CORS — 요청 Origin을 동적으로 반영 (같은 서버 내 IP 접근 허용)
        $app->add(function ($request, $handler) {
            $response    = $handler->handle($request);
            $reqOrigin   = $request->getHeaderLine('Origin');
            $allowOrigin = $reqOrigin !== '' ? $reqOrigin : ($_ENV['APP_URL'] ?? '*');
            return $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');
        });

        // CSRF — GET 에서 자동 발급/갱신, POST 에서 검증
        // 사용자 친화 정책:
        //   GET 요청 (list/view/menu 등 어떤 조회든) 시 csrf_token 쿠키가 없으면 자동 발급.
        //   덕분에 SPA 가 별도 act=csrf 호출 안 해도 다음 POST 가 자연스럽게 통과.
        //   응답에 X-CSRF-Token 헤더로도 노출 — 외부 도구/디버깅 용이.
        $app->add(function ($request, $handler) {
            if ($request->getMethod() === 'OPTIONS') {
                return new \Slim\Psr7\Response(200);
            }

            // GET → csrf_token 쿠키 자동 발급 (없을 때만)
            if ($request->getMethod() === 'GET' && empty($_COOKIE['csrf_token'])) {
                $newToken = bin2hex(random_bytes(16));
                setcookie('csrf_token', $newToken, [
                    'path'     => '/',
                    'httponly' => false, // JS 가 읽어서 헤더에 동봉 가능해야 함
                    'samesite' => 'Lax',
                    'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on',
                ]);
                $_COOKIE['csrf_token'] = $newToken;
            }

            if ($request->getMethod() === 'POST') {
                $act = $request->getQueryParams()['act'] ?? '';
                if (!in_array($act, ['login', 'refresh'], true)) {
                    $csrf = $request->getHeaderLine('X-CSRF-Token');
                    // 헤더가 없으면 form body 의 _csrf 필드도 fallback (iframe 내부 form POST 호환)
                    if ($csrf === '') {
                        $body = $request->getParsedBody();
                        if (is_array($body)) $csrf = (string)($body['_csrf'] ?? '');
                    }
                    $sessionCsrf = $_COOKIE['csrf_token'] ?? '';
                    if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
                        $response = new \Slim\Psr7\Response(403);
                        $response->getBody()->write(json_encode([
                            'success' => false,
                            'message' => 'CSRF 검증 실패',
                            'hint'    => '브라우저에서 한 번 새로고침(F5) 하면 토큰이 자동 발급됩니다.',
                        ], JSON_UNESCAPED_UNICODE));
                        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
                    }
                }
            }

            $response = $handler->handle($request);
            // 응답에 현재 토큰 헤더로 노출 — SPA 가 후속 요청에 사용 가능
            if (!empty($_COOKIE['csrf_token'])) {
                $response = $response->withHeader('X-CSRF-Token', $_COOKIE['csrf_token']);
            }
            return $response;
        });

        // JWT 인증
        $app->add($container->get(AuthMiddleware::class));

        $errorMiddleware = $app->addErrorMiddleware(
            $_ENV['APP_ENV'] === 'development',
            true,
            true,
            $container->get(LoggerInterface::class)
        );

        // JSON 에러 핸들러
        $errorMiddleware->setDefaultErrorHandler(
            function ($request, \Throwable $e, bool $displayDetails) use ($container) {
                try { $container->get(LoggerInterface::class)->error('Unhandled exception', [
                    'msg'   => $e->getMessage(),
                    'file'  => $e->getFile() . ':' . $e->getLine(),
                    'uri'   => (string)$request->getUri(),
                ]); } catch (\Throwable $ignored) {}
                $response = new \Slim\Psr7\Response(500);
                $body     = ['success' => false, 'message' => '서버 오류가 발생했습니다.', 'detail' => $e->getMessage()];
                $response->getBody()->write(json_encode($body, JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }
        );

        return $app;
    }

    // -------------------------------------------------------------------------
    private static function definitions(): array
    {
        return [
            LoggerInterface::class => function () {
                $log = new Logger('speedmis');
                $log->pushHandler(new RotatingFileHandler(
                    LOGS_PATH . '/app.log', 30, Logger::DEBUG
                ));
                return $log;
            },

            \PDO::class => function () {
                return \App\Config\Database::getInstance();
            },

            QueryBuilder::class   => \DI\create(QueryBuilder::class),
            MisCache::class       => \DI\create(MisCache::class),

            DataHandler::class => \DI\create(DataHandler::class)->constructor(
                \DI\get(\PDO::class),
                \DI\get(QueryBuilder::class),
                \DI\get(MisCache::class),
                \DI\get(LoggerInterface::class),
                \DI\get(FileManager::class)
            ),

            MenuRouter::class => \DI\create(MenuRouter::class)->constructor(
                \DI\get(\PDO::class),
                \DI\get(LoggerInterface::class)
            ),

            FileManager::class => \DI\create(FileManager::class)->constructor(
                \DI\get(\PDO::class),
                \DI\get(LoggerInterface::class)
            ),

            AuthMiddleware::class => \DI\create(AuthMiddleware::class),
        ];
    }
}
