<?php

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    /** act= 값 중 인증 불필요 목록 (외부 프린터 등 비-브라우저 클라이언트용 qrPrint 포함) */
    private const PUBLIC_ACTS = ['login', 'logout', 'refresh', 'ping', 'csrf', 'qrPrint', 'qrPrint.nocount'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getQueryParams();
        $act    = $params['act'] ?? '';

        if (in_array($act, self::PUBLIC_ACTS, true)) {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);
        if (!$token) {
            return $this->unauthorized('토큰이 없습니다.');
        }

        try {
            $secret  = $_ENV['APP_PWD_KEY'] ?? 'secret';
            $payload = JWT::decode($token, new Key($secret, JWT_ALGO));

            if (($payload->type ?? '') !== 'access') {
                return $this->unauthorized('유효하지 않은 토큰 유형입니다.');
            }

            // DB에서 useflag / theme / is_dev(그룹83) 최신값으로 덮어쓰기 (JWT 캐시 방지)
            // is_admin 은 DB 컬럼 대신 GLOBAL_ADMIN_UIDS 상수로 판정
            $uid = $payload->uid ?? '';
            $payload->is_admin = in_array($uid, GLOBAL_ADMIN_UIDS, true) ? 'Y' : '';
            try {
                $pdo  = \App\Config\Database::getInstance();
                $stmt = $pdo->prepare(
                    'SELECT useflag, theme FROM mis_users WHERE user_id = ? LIMIT 1'
                );
                $stmt->execute([$uid]);
                $row = $stmt->fetch();
                if ($row) {
                    $payload->theme    = $row['theme'] ?? 'light';
                    if ($row['useflag'] !== '1') {
                        return $this->unauthorized('사용이 중지된 계정입니다.');
                    }
                }
                // 개발자 그룹(83) 멤버 여부 — 매 요청마다 DB 에서 확인
                $devStmt = $pdo->prepare('SELECT 1 FROM mis_group_members WHERE user_id = ? AND group_idx = 83 LIMIT 1');
                $devStmt->execute([$payload->uid ?? '']);
                $payload->is_dev = $devStmt->fetchColumn() ? 'Y' : 'N';
            } catch (\Throwable) {}

            $request = $request->withAttribute('user', $payload);
        } catch (\Throwable $e) {
            return $this->unauthorized('토큰이 만료되었거나 유효하지 않습니다.');
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        // 1) Authorization: Bearer {token}
        $auth = $request->getHeaderLine('Authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        // 2) HttpOnly 쿠키
        $cookies = $request->getCookieParams();
        return $cookies['access_token'] ?? null;
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response(401);
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
