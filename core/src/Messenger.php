<?php

namespace App;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 부자톡 메신저 + 푸시
 *
 *  - 채팅: dm / group / system 룸 + 메시지 + 읽음 위치
 *  - 푸시: VAPID Web Push (FCM/Mozilla autopush 등 표준 endpoint 직접 발송)
 *  - 자동알림 = 사용자별 'system' 룸으로 통합되어 채팅과 같은 화면에서 보임
 */
class Messenger
{
    public function __construct(private \PDO $pdo) {}

    /**
     * 채팅 테이블이 마이그레이션되었는지 확인 (요청당 1회 캐시).
     * 마이그레이션되지 않은 환경(로컬 dev) 에선 chat* 엔드포인트가 빈 결과로 graceful 폴백.
     */
    public function tablesExist(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;
        try {
            $this->pdo->query("SELECT 1 FROM mis_chat_rooms LIMIT 1");
            return $cached = true;
        } catch (\Throwable) {
            return $cached = false;
        }
    }

    /** VAPID 가 설정돼있나 */
    public static function vapidConfigured(): bool {
        return !empty($_ENV['VAPID_PUBLIC_KEY']) && !empty($_ENV['VAPID_PRIVATE_KEY']);
    }
    public static function vapidPublicKey(): string {
        return (string)($_ENV['VAPID_PUBLIC_KEY'] ?? '');
    }

    // ─── PUSH 구독 ───────────────────────────────────────────────────────

    public function subscribePush(string $userId, array $sub, ?string $ua = null, ?string $ip = null, ?string $deviceLabel = null): bool
    {
        $endpoint = (string)($sub['endpoint'] ?? '');
        $p256dh   = (string)($sub['keys']['p256dh'] ?? '');
        $auth     = (string)($sub['keys']['auth'] ?? '');
        if ($endpoint === '' || $p256dh === '' || $auth === '') return false;

        $stmt = $this->pdo->prepare(
            "INSERT INTO mis_push_subscriptions
               (user_id, endpoint, p256dh, auth_key, ua, ip, device_label, is_active, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Y', NOW())
             ON DUPLICATE KEY UPDATE
               user_id=VALUES(user_id),
               p256dh=VALUES(p256dh), auth_key=VALUES(auth_key),
               ua=VALUES(ua), ip=VALUES(ip), device_label=VALUES(device_label),
               is_active='Y', fail_count=0, last_used_at=NOW()"
        );
        $stmt->execute([$userId, $endpoint, $p256dh, $auth, $ua, $ip, $deviceLabel]);
        return true;
    }

    public function unsubscribePush(string $userId, string $endpoint): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE mis_push_subscriptions SET is_active='N'
              WHERE user_id = ? AND endpoint = ?"
        );
        $stmt->execute([$userId, $endpoint]);
    }

    /**
     * 사용자에게 Web Push 발송. mis_push_messages 에 이력 기록.
     * @return array ['ok'=>bool, 'sent'=>int, 'failed'=>int, 'error'=>string]
     */
    public function sendPush(string $toUserId, string $title, string $body, ?string $clickUrl = null, ?array $payload = null, ?string $fromUserId = null): array
    {
        if (!self::vapidConfigured()) {
            return ['ok'=>false,'sent'=>0,'failed'=>0,'error'=>'VAPID not configured'];
        }

        $msgIdx = 0;
        try {
            $logStmt = $this->pdo->prepare(
                "INSERT INTO mis_push_messages (from_user, to_user, title, body, click_url, payload, send_status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            $logStmt->execute([
                $fromUserId, $toUserId, mb_substr($title, 0, 200),
                $body, $clickUrl,
                $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $msgIdx = (int)$this->pdo->lastInsertId();
        } catch (\Throwable) {}

        // 활성 구독 가져오기
        $stmt = $this->pdo->prepare(
            "SELECT idx, endpoint, p256dh, auth_key
               FROM mis_push_subscriptions
              WHERE user_id = ? AND is_active = 'Y'"
        );
        $stmt->execute([$toUserId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            $this->pdo->prepare("UPDATE mis_push_messages SET send_status='failed', error_msg='no active subscription' WHERE idx=?")
                ->execute([$msgIdx]);
            return ['ok'=>false,'sent'=>0,'failed'=>0,'error'=>'no subscription'];
        }

        $auth = [
            'VAPID' => [
                'subject'    => $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@xn--or3b27p5mi.com',
                'publicKey'  => $_ENV['VAPID_PUBLIC_KEY'],
                'privateKey' => $_ENV['VAPID_PRIVATE_KEY'],
            ],
        ];
        $webPush = new WebPush($auth);
        $webPush->setDefaultOptions(['TTL' => 86400, 'urgency' => 'normal']);

        $pushPayload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $clickUrl ?: ($_ENV['APP_URL'] ?? '/v7'),
            'tag'   => $payload['tag']  ?? null,
            'data'  => $payload         ?? new \stdClass(),
        ], JSON_UNESCAPED_UNICODE);

        $subMap = [];
        foreach ($rows as $r) {
            $subscription = Subscription::create([
                'endpoint'        => $r['endpoint'],
                'publicKey'       => $r['p256dh'],
                'authToken'       => $r['auth_key'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $subMap[$r['endpoint']] = (int)$r['idx'];
            $webPush->queueNotification($subscription, $pushPayload);
        }

        $sent = 0; $failed = 0; $errors = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $subId    = $subMap[$endpoint] ?? 0;
            if ($report->isSuccess()) {
                $sent++;
                $this->pdo->prepare("UPDATE mis_push_subscriptions SET last_used_at=NOW(), fail_count=0 WHERE idx=?")->execute([$subId]);
            } else {
                $failed++;
                $code = $report->getResponse()?->getStatusCode() ?? 0;
                $errors[] = "ep#{$subId} HTTP {$code} " . $report->getReason();
                if (in_array($code, [403, 404, 410], true)) {
                    // 403=VAPID 키 불일치 (다른 키로 구독), 404/410=endpoint 만료 — 모두 비활성
                    $this->pdo->prepare("UPDATE mis_push_subscriptions SET is_active='N' WHERE idx=?")->execute([$subId]);
                } else {
                    $this->pdo->prepare("UPDATE mis_push_subscriptions SET fail_count=fail_count+1 WHERE idx=?")->execute([$subId]);
                }
            }
        }

        $status = $sent > 0 ? ($failed > 0 ? 'partial' : 'sent') : 'failed';
        $this->pdo->prepare(
            "UPDATE mis_push_messages SET send_status=?, sent_count=?, fail_count=?, error_msg=? WHERE idx=?"
        )->execute([$status, $sent, $failed, $errors ? mb_substr(implode(' / ', $errors), 0, 1000) : null, $msgIdx]);

        return ['ok'=>$sent>0,'sent'=>$sent,'failed'=>$failed,'error'=>implode(' / ', $errors)];
    }

    // ─── CHAT 룸/메시지 ────────────────────────────────────────────────────

    /** 사용자별 system 룸 idx (없으면 생성) — 인라인 SQL (mysql/pg/sqlsrv 호환) */
    public function ensureSystemRoom(string $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT r.idx FROM mis_chat_rooms r
               JOIN mis_chat_room_members m ON m.room_idx = r.idx AND m.user_id = ?
              WHERE r.room_type = 'system'"
        );
        $st->execute([$userId]);
        $idx = (int)($st->fetchColumn() ?: 0);
        if ($idx > 0) return $idx;

        $this->pdo->prepare(
            "INSERT INTO mis_chat_rooms (room_type, title, owner_user_id) VALUES ('system', ?, ?)"
        )->execute(['🔔 자동알림', $userId]);
        $idx = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO mis_chat_room_members (room_idx, user_id, role) VALUES (?, ?, 'member')"
        )->execute([$idx, $userId]);
        return $idx;
    }

    /** A↔B 1:1 dm 룸 idx (없으면 생성) — 인라인 SQL.
     * 기존 룸이 있으면 퇴장(left_at)한 멤버를 자동 재합류시켜 다시 대화할 수 있게 함.
     */
    public function ensureDmRoom(string $userA, string $userB): int
    {
        $st = $this->pdo->prepare(
            "SELECT r.idx FROM mis_chat_rooms r
               JOIN mis_chat_room_members ma ON ma.room_idx=r.idx AND ma.user_id=?
               JOIN mis_chat_room_members mb ON mb.room_idx=r.idx AND mb.user_id=?
              WHERE r.room_type='dm'"
        );
        $st->execute([$userA, $userB]);
        $idx = (int)($st->fetchColumn() ?: 0);
        if ($idx > 0) {
            // 기존 룸 — 퇴장(left_at IS NOT NULL) 상태면 재합류 처리.
            // ★ joined_at=NOW() 도 함께 갱신해야 [[과거 대화 안 보이기]] 가 작동.
            //   (예전엔 SET 좌→우 평가로 IF(left_at IS NOT NULL,...) 가 항상 false 였던 버그)
            //   WHERE 가 이미 left_at IS NOT NULL 로 필터하니 분기 없이 무조건 NOW() 가 안전.
            $this->pdo->prepare(
                "UPDATE mis_chat_room_members
                    SET left_at = NULL, joined_at = NOW()
                  WHERE room_idx = ? AND user_id IN (?, ?) AND left_at IS NOT NULL"
            )->execute([$idx, $userA, $userB]);
            return $idx;
        }

        $this->pdo->prepare(
            "INSERT INTO mis_chat_rooms (room_type, owner_user_id) VALUES ('dm', ?)"
        )->execute([$userA]);
        $idx = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO mis_chat_room_members (room_idx, user_id, role) VALUES (?, ?, 'member')"
        )->execute([$idx, $userA]);
        $this->pdo->prepare(
            "INSERT INTO mis_chat_room_members (room_idx, user_id, role) VALUES (?, ?, 'member')"
        )->execute([$idx, $userB]);
        return $idx;
    }

    /**
     * 동일 멤버셋으로 이미 존재하는 그룹 룸 idx 검색 (없으면 0)
     * 정확히 같은 멤버 집합 (no extras, no missing) 만 매칭.
     */
    public function findGroupRoomByMembers(array $userIds): int
    {
        $userIds = array_values(array_unique(array_filter($userIds, fn($u) => $u !== '')));
        $cnt = count($userIds);
        if ($cnt < 2) return 0;
        $ph = implode(',', array_fill(0, $cnt, '?'));
        // 퇴장한 멤버(left_at <> NULL)도 포함해서 매칭 — 동일 멤버셋의 휴면 룸 재활용 가능
        $sql = "
          SELECT r.idx
          FROM mis_chat_rooms r
          WHERE r.room_type = 'group'
            AND (SELECT COUNT(*) FROM mis_chat_room_members m
                  WHERE m.room_idx = r.idx) = ?
            AND (SELECT COUNT(*) FROM mis_chat_room_members m
                  WHERE m.room_idx = r.idx AND m.user_id IN ($ph)) = ?
          ORDER BY r.last_message_at DESC, r.idx DESC
          LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $args = array_merge([$cnt], $userIds, [$cnt]);
        $stmt->execute($args);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * 그룹/DM 룸 보장:
     *   - 멤버 = 본인 + 1명 → ensureDmRoom 으로 위임
     *   - 그 이상     → 같은 멤버셋의 group 룸 검색, 없으면 새로 생성
     */
    public function ensureGroupRoom(string $ownerId, array $memberUserIds, string $title = ''): int
    {
        $allMembers = array_values(array_unique(array_merge([$ownerId], $memberUserIds)));
        if (count($allMembers) === 2) {
            $other = $allMembers[0] === $ownerId ? $allMembers[1] : $allMembers[0];
            return $this->ensureDmRoom($ownerId, $other);
        }
        // 카톡식: 그룹 채팅 시작은 매번 새 방 생성 — 동일 멤버셋 재활용 안 함.
        // (3인 이상 그룹 채팅방은 채팅방ID 단위로 멤버가 변할 수 있는 구조)
        $others = array_values(array_diff($allMembers, [$ownerId]));
        return $this->createGroupRoom($ownerId, $others, $title);
    }

    /**
     * 조직도 데이터 — 119(전사원 연락처) 와 동일 필터.
     * @return array ['stations' => [...], 'users' => [...]]
     */
    public function orgTree(): array
    {
        $stations = $this->pdo->query("
          SELECT idx, station_name, autogubun, IFNULL(depth, 99) AS depth, IFNULL(upidx, 0) AS upidx
            FROM mis_stations
           WHERE useflag='1' AND IFNULL(autogubun,'') <> ''
           ORDER BY autogubun
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $users = $this->pdo->query("
          SELECT u.user_id, u.user_name, u.station_idx,
                 u.position_code, IFNULL(u.sortnum, 0) AS sortnum,
                 COALESCE(u.hand_phone, '') AS hand_phone,
                 COALESCE(pc.kname, '') AS position_name,
                 COALESCE(s.autogubun, '') AS station_autogubun
            FROM mis_users u
            LEFT JOIN mis_common_data pc
              ON pc.kcode = u.position_code AND pc.gcode='speedmis000188'
            LEFT JOIN mis_stations s ON s.idx = u.station_idx
           WHERE u.useflag='1'
             AND (DATEDIFF(IFNULL(u.toisa_date,'9999-12-31'), CURDATE()) <= 0
                  OR LTRIM(IFNULL(u.toisa_date,''))='')
             AND IFNULL(u.is_rest,'') <> 'Y'
             AND u.user_id NOT IN ('gadmin','admin')
             AND u.position_code < 98
           ORDER BY s.autogubun, u.position_code, u.sortnum, u.user_id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return ['stations' => $stations, 'users' => $users];
    }

    /** 그룹 룸 생성 */
    public function createGroupRoom(string $ownerUserId, array $memberUserIds, string $title = ''): int
    {
        $this->pdo->beginTransaction();
        try {
            // title 빈값이면 NULL — 클라이언트에서 멤버 성씨 도트 라벨로 표시되도록
            $this->pdo->prepare(
                "INSERT INTO mis_chat_rooms (room_type, title, owner_user_id) VALUES ('group', ?, ?)"
            )->execute([$title !== '' ? $title : null, $ownerUserId]);
            $roomIdx = (int)$this->pdo->lastInsertId();

            $members = array_unique(array_merge([$ownerUserId], $memberUserIds));
            $ins = $this->pdo->prepare(
                "INSERT IGNORE INTO mis_chat_room_members (room_idx, user_id, role) VALUES (?, ?, ?)"
            );
            foreach ($members as $u) {
                $ins->execute([$roomIdx, $u, $u === $ownerUserId ? 'admin' : 'member']);
            }
            $this->pdo->commit();
            return $roomIdx;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 카톡식 초대 — 현재 방에 사람 추가.
     *   - room_type='dm'    → 새 group 방 생성 (기존 DM 2명 + 신규 멤버). DM 은 그대로 보존 (별개 방)
     *   - room_type='group' → 기존 방에 멤버 ADD (joined_at=NOW, left_at=NULL). 시스템 메시지로 안내
     *   - room_type='system'→ 거부
     * @return int 이동할 방 idx (DM 케이스는 새 그룹 idx)
     */
    public function inviteToRoom(int $roomIdx, string $inviterUserId, array $newMemberIds): int
    {
        $newMemberIds = array_values(array_unique(array_filter($newMemberIds,
            fn($u) => $u !== '' && $u !== $inviterUserId)));
        if (!$newMemberIds) throw new \InvalidArgumentException('초대할 멤버가 없습니다.');

        $room = $this->pdo->prepare("SELECT room_type FROM mis_chat_rooms WHERE idx=? LIMIT 1");
        $room->execute([$roomIdx]);
        $roomType = (string)$room->fetchColumn();
        if ($roomType === '')       throw new \RuntimeException('대상 방을 찾을 수 없습니다.');
        if ($roomType === 'system') throw new \RuntimeException('자동알림 방에는 초대할 수 없습니다.');

        // 호출자가 활성 멤버인지 확인
        $this->assertMember($roomIdx, $inviterUserId);

        // ── DM 케이스: 새 그룹 방 생성 ──
        if ($roomType === 'dm') {
            $otherIds = $this->pdo->prepare(
                "SELECT user_id FROM mis_chat_room_members WHERE room_idx=? AND user_id<>? AND left_at IS NULL"
            );
            $otherIds->execute([$roomIdx, $inviterUserId]);
            $existingDmMembers = $otherIds->fetchAll(\PDO::FETCH_COLUMN);
            $groupMembers = array_values(array_unique(array_merge($existingDmMembers, $newMemberIds)));
            return $this->createGroupRoom($inviterUserId, $groupMembers);
        }

        // ── group 케이스: 기존 방에 멤버 ADD ──
        $this->pdo->beginTransaction();
        try {
            $addedNames = [];
            $sel = $this->pdo->prepare(
                "SELECT idx, left_at FROM mis_chat_room_members WHERE room_idx=? AND user_id=? LIMIT 1"
            );
            $ins = $this->pdo->prepare(
                "INSERT INTO mis_chat_room_members (room_idx, user_id, role) VALUES (?, ?, 'member')"
            );
            // 재합류는 joined_at=NOW() 로 갱신 — [[과거 대화내역 안 보임]] 규칙 유지
            $rejoin = $this->pdo->prepare(
                "UPDATE mis_chat_room_members SET joined_at=NOW(), left_at=NULL
                  WHERE room_idx=? AND user_id=?"
            );
            foreach ($newMemberIds as $uid) {
                $sel->execute([$roomIdx, $uid]);
                $existing = $sel->fetch(\PDO::FETCH_ASSOC);
                if (!$existing) {
                    $ins->execute([$roomIdx, $uid]);
                    $addedNames[] = $this->userName($uid);
                } elseif ($existing['left_at']) {
                    $rejoin->execute([$roomIdx, $uid]);
                    $addedNames[] = $this->userName($uid);
                }
                // 이미 활성 멤버면 skip (중복 초대 무시)
            }
            $this->pdo->commit();
            if ($addedNames) {
                $inviterName = $this->userName($inviterUserId);
                $msg = "{$inviterName}님이 " . implode(', ', $addedNames) . "님을 초대했습니다.";
                $this->pdo->prepare(
                    "INSERT INTO mis_chat_messages (room_idx, from_type, body) VALUES (?, 'system', ?)"
                )->execute([$roomIdx, $msg]);
                $this->pdo->prepare(
                    "UPDATE mis_chat_rooms SET last_message_at=NOW(), last_message_preview=? WHERE idx=?"
                )->execute([mb_substr($msg, 0, 120), $roomIdx]);
            }
            return $roomIdx;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 비활성 N일 경과 방 자동 삭제. system 룸은 제외.
     * CHAT_RETENTION_DAYS=7 이면 마지막 메시지(없으면 wdate) 가 7일 이전인 방 전체 DELETE (메시지/멤버 포함).
     */
    /** rooms() 호출 시 24시간 1회 cleanup 트리거. APCu 가용 시 그 안에 마커. */
    private function maybeCleanupOldRooms(): void
    {
        $days = (int)($_ENV['CHAT_RETENTION_DAYS'] ?? 0);
        if ($days <= 0) return;
        $key = 'mis_chat_cleanup_last';
        if (function_exists('apcu_fetch')) {
            $last = (int)apcu_fetch($key);
            if ($last && (time() - $last) < 86400) return;
            @apcu_store($key, time(), 86400);
        }
        try { $this->cleanupOldRooms($days); } catch (\Throwable) {}
    }

    public function cleanupOldRooms(int $days): int
    {
        if ($days <= 0) return 0;
        $st = $this->pdo->prepare(
            "SELECT idx FROM mis_chat_rooms
              WHERE room_type <> 'system'
                AND COALESCE(last_message_at, wdate) < (NOW() - INTERVAL ? DAY)"
        );
        $st->execute([$days]);
        $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
        if (!$ids) return 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM mis_chat_messages     WHERE room_idx IN ($ph)")->execute($ids);
        $this->pdo->prepare("DELETE FROM mis_chat_room_members WHERE room_idx IN ($ph)")->execute($ids);
        $this->pdo->prepare("DELETE FROM mis_chat_rooms        WHERE idx IN ($ph)")->execute($ids);
        return count($ids);
    }

    /** 사용자가 속한 룸 목록 + 미읽음 카운트 + 상대방 표시명
     *  GROUP_CONCAT (mariadb) ↔ STRING_AGG (pg/sqlsrv) — driver 분기.
     */
    public function rooms(string $userId): array
    {
        // 24시간 1회 retention cleanup — CHAT_RETENTION_DAYS 일 이상 대화없는 방 자동삭제(system 제외)
        $this->maybeCleanupOldRooms();
        $isPg     = \App\Config\Database::isPg();
        $isMssql  = \App\Config\Database::isMssql();
        $aggExpr  = ($isPg || $isMssql)
            ? "STRING_AGG(u.user_name, ', ')"
            : "GROUP_CONCAT(DISTINCT u.user_name SEPARATOR ', ')";
        $orderSysFirst = ($isPg || $isMssql)
            ? "CASE WHEN r.room_type='system' THEN 0 ELSE 1 END"
            : "(r.room_type='system') DESC";
        $coalesceLastMsg = ($isPg || $isMssql)
            ? "COALESCE(r.last_message_at, r.wdate)"
            : "IFNULL(r.last_message_at, r.wdate)";
        $coalesceLastRead = ($isPg || $isMssql)
            ? "COALESCE(m.last_read_message_idx,0)"
            : "IFNULL(m.last_read_message_idx,0)";

        // PG/MSSQL: ORDER BY 에 ASC/DESC 필요, "boolean DESC" 안됨 → CASE 로 변환
        $orderClause = ($isPg || $isMssql)
            ? "ORDER BY {$orderSysFirst} ASC, {$coalesceLastMsg} DESC"
            : "ORDER BY {$orderSysFirst}, {$coalesceLastMsg} DESC";

        $sql = "
            SELECT
                r.idx, r.room_type, r.title, r.owner_user_id,
                r.last_message_at, r.last_message_preview,
                m.last_read_message_idx, m.notification,
                (SELECT COUNT(*) FROM mis_chat_messages msg
                  WHERE msg.room_idx = r.idx
                    AND msg.idx > {$coalesceLastRead}
                    AND msg.wdate >= m.joined_at
                    AND COALESCE(msg.from_user_id, '') <> ?
                    AND msg.deleted_at IS NULL) AS unread_count,
                (SELECT {$aggExpr}
                   FROM mis_chat_room_members rm
                   JOIN mis_users u ON u.user_id = rm.user_id
                  WHERE rm.room_idx = r.idx AND rm.user_id <> ?
                    AND (r.room_type = 'dm' OR rm.left_at IS NULL)) AS other_names
            FROM mis_chat_rooms r
            JOIN mis_chat_room_members m ON m.room_idx = r.idx AND m.user_id = ? AND m.left_at IS NULL
            {$orderClause}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** 룸 메시지 페이지 (idx 기준 backwards)
     *  재입장한 멤버는 (이전 joined_at) 이전 메시지를 보지 못함 — rm.joined_at 필터 적용.
     */
    public function history(int $roomIdx, string $userId, ?int $beforeIdx = null, int $limit = 50): array
    {
        $this->assertMember($roomIdx, $userId);
        $limit = max(1, min(200, $limit));

        // MSSQL 에선 SQL 문자열 리터럴이 기본 VARCHAR 라 한글이 ??? 로 깎임 → N'...' Unicode 리터럴 필요.
        // MariaDB/PG 는 N 접두사를 무시하거나 NATIONAL CHARACTER 로 동일하게 받아들임 — 모든 드라이버에서 안전.
        $nPrefix = \App\Config\Database::isMssql() ? 'N' : '';
        $sql = "
            SELECT
                msg.idx, msg.room_idx, msg.from_type, msg.from_user_id,
                msg.body, msg.meta, msg.wdate,
                CASE WHEN msg.from_type='system' THEN {$nPrefix}'자동알림'
                     ELSE COALESCE(u.user_name, msg.from_user_id) END AS from_name
              FROM mis_chat_messages msg
              JOIN mis_chat_room_members rm
                ON rm.room_idx = msg.room_idx AND rm.user_id = ?
              LEFT JOIN mis_users u ON u.user_id = msg.from_user_id
             WHERE msg.room_idx = ? AND msg.deleted_at IS NULL
               AND msg.wdate >= rm.joined_at
        ";
        $args = [$userId, $roomIdx];
        if ($beforeIdx) { $sql .= " AND msg.idx < ?"; $args[] = $beforeIdx; }
        $sql .= " ORDER BY msg.idx DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        $rows = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        foreach ($rows as &$r) {
            if (!empty($r['meta']) && is_string($r['meta'])) {
                $r['meta'] = json_decode($r['meta'], true) ?: null;
            }
        }
        return $rows;
    }

    /** 메시지 송신 (사용자) — DB INSERT + 룸 갱신 + 다른 멤버에게 푸시 */
    public function send(int $roomIdx, string $fromUserId, string $body): array
    {
        $body = trim($body);
        if ($body === '') throw new \InvalidArgumentException('빈 메시지');
        $this->assertMember($roomIdx, $fromUserId);
        return $this->insertMessage($roomIdx, 'user', $fromUserId, $body, null);
    }

    /**
     * 시스템 메시지 발송 — 사용자 system 룸에 INSERT + 푸시 알림.
     * 훅(댓글/결재/스케줄 등)에서 호출.
     */
    public function systemPost(string $toUserId, string $body, ?string $clickUrl = null, ?string $title = null): array
    {
        $roomIdx = $this->ensureSystemRoom($toUserId);
        $meta = $clickUrl ? ['url' => $clickUrl] : null;
        $msg = $this->insertMessage($roomIdx, 'system', null, $body, $meta);
        // 시스템 룸은 본인만 멤버 — 자기 자신에게 푸시
        try {
            $this->sendPush($toUserId, $title ?: '🔔 자동알림', mb_substr($body, 0, 120), $clickUrl, ['tag' => "sys-{$roomIdx}"]);
        } catch (\Throwable $e) {
            error_log("[Messenger::systemPost] sendPush 실패 — 메시지는 정상 저장됨. " . $e->getMessage());
        }
        return $msg;
    }

    /**
     * 룸 퇴장 — 본인 멤버를 left_at=NOW() 처리.
     * 시스템 메시지 "{name}님이 퇴장하였습니다." 추가.
     * 단, 개설자가 user 메시지 0건인 빈 방을 떠나면 시스템 메시지 없이 통째 삭제.
     * 멤버 행 자체는 유지(나중에 자동 재합류 가능).
     */
    public function leaveRoom(int $roomIdx, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.room_type, r.owner_user_id, rm.left_at, COALESCE(u.user_name, rm.user_id) AS user_name
               FROM mis_chat_room_members rm
               JOIN mis_chat_rooms r ON r.idx = rm.room_idx
          LEFT JOIN mis_users u ON u.user_id = rm.user_id
              WHERE rm.room_idx = ? AND rm.user_id = ?
              LIMIT 1"
        );
        $stmt->execute([$roomIdx, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \RuntimeException('해당 룸의 멤버가 아닙니다.');
        if ($row['room_type'] === 'system') throw new \RuntimeException('자동알림 룸은 퇴장할 수 없습니다.');
        if ($row['left_at']) return; // 이미 퇴장 상태

        // ── 개설자가 대화 없이 떠난 빈 방 → 통째로 사라짐 (시스템 메시지 X) ──
        // user 메시지 0건이면 시스템 알림류만 있던 빈 방. 이걸 만든 사람이 떠나면 의미 없음.
        if ((string)$row['owner_user_id'] === $userId) {
            $userMsgCount = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM mis_chat_messages WHERE room_idx = " . (int)$roomIdx . " AND from_type='user'"
            )->fetchColumn();
            if ($userMsgCount === 0) {
                $this->pdo->prepare("DELETE FROM mis_chat_messages     WHERE room_idx = ?")->execute([$roomIdx]);
                $this->pdo->prepare("DELETE FROM mis_chat_room_members WHERE room_idx = ?")->execute([$roomIdx]);
                $this->pdo->prepare("DELETE FROM mis_chat_rooms        WHERE idx = ?")->execute([$roomIdx]);
                return;
            }
        }

        $this->pdo->prepare(
            "UPDATE mis_chat_room_members SET left_at = NOW() WHERE room_idx = ? AND user_id = ?"
        )->execute([$roomIdx, $userId]);

        // 활성 멤버가 모두 사라졌다면(=마지막 퇴장자) 채팅방 + 메시지 + 멤버 모두 DELETE.
        // 자동알림(system) 룸은 위에서 throw 로 차단됐으니 도달 불가.
        $activeCount = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM mis_chat_room_members WHERE room_idx = " . (int)$roomIdx . " AND left_at IS NULL"
        )->fetchColumn();
        if ($activeCount === 0) {
            $this->pdo->prepare("DELETE FROM mis_chat_messages       WHERE room_idx = ?")->execute([$roomIdx]);
            $this->pdo->prepare("DELETE FROM mis_chat_room_members   WHERE room_idx = ?")->execute([$roomIdx]);
            $this->pdo->prepare("DELETE FROM mis_chat_rooms          WHERE idx = ?")->execute([$roomIdx]);
            return; // 룸이 사라졌으니 system 메시지/last_message 갱신 skip
        }

        // 시스템 메시지 (insertMessage 의 외로움 체크 회피 위해 직접 INSERT)
        $msg = "{$row['user_name']}님이 퇴장하였습니다.";
        $this->pdo->prepare(
            "INSERT INTO mis_chat_messages (room_idx, from_type, body) VALUES (?, 'system', ?)"
        )->execute([$roomIdx, $msg]);
        $this->pdo->prepare(
            "UPDATE mis_chat_rooms SET last_message_at=NOW(), last_message_preview=? WHERE idx=?"
        )->execute([mb_substr($msg, 0, 120), $roomIdx]);
    }

    /** 읽음 위치 갱신 */
    public function markRead(int $roomIdx, string $userId, ?int $messageIdx = null): void
    {
        $this->assertMember($roomIdx, $userId);
        if ($messageIdx === null) {
            $messageIdx = (int)$this->pdo->query(
                "SELECT IFNULL(MAX(idx),0) FROM mis_chat_messages WHERE room_idx = " . (int)$roomIdx
            )->fetchColumn();
        }
        $this->pdo->prepare(
            "UPDATE mis_chat_room_members SET last_read_message_idx = GREATEST(last_read_message_idx, ?)
              WHERE room_idx = ? AND user_id = ?"
        )->execute([$messageIdx, $roomIdx, $userId]);
    }

    // ─── 내부 ────────────────────────────────────────────────────────────

    private function assertMember(int $roomIdx, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM mis_chat_room_members
              WHERE room_idx = ? AND user_id = ? AND left_at IS NULL LIMIT 1"
        );
        $stmt->execute([$roomIdx, $userId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('해당 룸의 멤버가 아닙니다.');
        }
    }

    /** XSS 방지용 간단 화이트리스트 — script/iframe/style/on* 등 제거 */
    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is',  '', $html) ?? $html;
        $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is',  '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is',    '', $html) ?? $html;
        $html = preg_replace('#<(object|embed|form|input|textarea|button|select|link|meta|base)\b[^>]*>#is', '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i',  '', $html) ?? $html;
        $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i",  '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*[^\s>]+#i',  '', $html) ?? $html;
        $html = preg_replace('#\s(href|src)\s*=\s*"\s*javascript:[^"]*"#i', ' $1="#"', $html) ?? $html;
        $html = preg_replace("#\s(href|src)\s*=\s*'\s*javascript:[^']*'#i", ' $1="#"', $html) ?? $html;
        return trim($html);
    }

    /** HTML → 일반 텍스트 (push body / 미리보기용) */
    public static function plainText(string $html): string
    {
        $t = strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    private function insertMessage(int $roomIdx, string $fromType, ?string $fromUserId, string $body, ?array $meta): array
    {
        // user 발송분만 sanitize (system 은 서버 내부 호출)
        if ($fromType === 'user') $body = self::sanitizeHtml($body);

        // ── 외로움 체크 + 자동 재합류 ─────────────────────────────────
        // 발신자가 활성 멤버 1명뿐이면 퇴장한 멤버들을 다시 합류시킴.
        //  - group: 퇴장자를 다시 끌어들임 (기존 동작)
        //  - dm   : 2인 대화방은 항상 유니크 — 한쪽이 떠났어도 다른쪽이 메시지 보내면 재합류 (그 사람의
        //           joined_at 은 NOW 로 갱신돼 퇴장 전 대화는 안 보이고, 새 메시지부터 받음)
        //  - system: 발신자 본인뿐이므로 해당 없음
        if ($fromType === 'user') {
            $rt = $this->pdo->query("SELECT room_type FROM mis_chat_rooms WHERE idx = " . (int)$roomIdx)->fetchColumn();
            if ($rt === 'group' || $rt === 'dm') {
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM mis_chat_room_members WHERE room_idx = ? AND left_at IS NULL"
                );
                $st->execute([$roomIdx]);
                $activeCount = (int)$st->fetchColumn();
                if ($activeCount === 1) {
                    $leftQ = $this->pdo->prepare(
                        "SELECT user_id FROM mis_chat_room_members
                          WHERE room_idx = ? AND left_at IS NOT NULL"
                    );
                    $leftQ->execute([$roomIdx]);
                    $leftUsers = $leftQ->fetchAll(\PDO::FETCH_COLUMN);
                    if ($leftUsers) {
                        // 재합류 — joined_at 도 NOW() 로 갱신해서 퇴장 이전 메시지는 본인에게 안 보이게.
                        $this->pdo->prepare(
                            "UPDATE mis_chat_room_members SET left_at = NULL, joined_at = NOW()
                              WHERE room_idx = ? AND left_at IS NOT NULL"
                        )->execute([$roomIdx]);

                        $names = array_map(fn($u) => $this->userName((string)$u), $leftUsers);
                        $rejoinMsg = implode(', ', $names) . '님이 다시 합류했습니다.';
                        $this->pdo->prepare(
                            "INSERT INTO mis_chat_messages (room_idx, from_type, body)
                             VALUES (?, 'system', ?)"
                        )->execute([$roomIdx, $rejoinMsg]);
                    }
                }
            }
        }

        $this->pdo->prepare(
            "INSERT INTO mis_chat_messages (room_idx, from_type, from_user_id, body, meta)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $roomIdx, $fromType, $fromUserId, $body,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $msgIdx = (int)$this->pdo->lastInsertId();

        $previewSrc = $fromType === 'user' ? self::plainText($body) : $body;
        $preview    = mb_substr($previewSrc, 0, 120);
        $this->pdo->prepare(
            "UPDATE mis_chat_rooms SET last_message_at=NOW(), last_message_preview=? WHERE idx=?"
        )->execute([$preview, $roomIdx]);

        // 본인 읽음 처리
        if ($fromUserId !== null) {
            $this->pdo->prepare(
                "UPDATE mis_chat_room_members SET last_read_message_idx = ?
                  WHERE room_idx = ? AND user_id = ?"
            )->execute([$msgIdx, $roomIdx, $fromUserId]);
        }

        // 다른 멤버에게 푸시 (시스템 룸은 본인만이므로 systemPost 에서 별도 처리)
        if ($fromType === 'user') {
            $stmt = $this->pdo->prepare(
                "SELECT m.user_id FROM mis_chat_room_members m
                  WHERE m.room_idx = ? AND m.user_id <> ? AND m.left_at IS NULL
                    AND m.notification = 'on'"
            );
            $stmt->execute([$roomIdx, $fromUserId]);
            $others = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $sender = $this->userName($fromUserId);
            $pushBody = mb_substr(self::plainText($body), 0, 120);
            if ($pushBody === '') $pushBody = '(첨부)';
            foreach ($others as $u) {
                // 푸시 실패가 메시지 전송을 막지 않도록 격리.
                // msgo 등 일부 사이트는 mis_push_subscriptions 스키마가 옛버전(is_active 등 누락)
                // 이라 sendPush 에서 SQL 예외 가능. push 는 best-effort.
                try {
                    $this->sendPush(
                        (string)$u,
                        "💬 {$sender}",
                        $pushBody,
                        ($_ENV['APP_URL'] ?? '/v7') . '/?gubun=__chat__&room=' . $roomIdx,
                        ['tag' => "chat-{$roomIdx}", 'roomIdx' => $roomIdx],
                        $fromUserId
                    );
                } catch (\Throwable $e) {
                    error_log("[Messenger::insertMessage] sendPush 실패 — 메시지는 정상 저장됨. " . $e->getMessage());
                }
            }
        }

        return [
            'idx'      => $msgIdx,
            'room_idx' => $roomIdx,
            'from_type'=> $fromType,
            'from_user_id' => $fromUserId,
            'from_name' => $fromType === 'system' ? '자동알림' : $this->userName($fromUserId ?? ''),
            'body'     => $body,
            'meta'     => $meta,
        ];
    }

    private function userName(string $userId): string
    {
        if ($userId === '') return '';
        static $cache = [];
        if (isset($cache[$userId])) return $cache[$userId];
        $stmt = $this->pdo->prepare("SELECT user_name FROM mis_users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $cache[$userId] = (string)($stmt->fetchColumn() ?: $userId);
    }
}
