<?php

namespace App;

use Psr\Log\LoggerInterface;

/**
 * mis_menus 테이블 기반 메뉴 트리 / 권한 확인
 */
class MenuRouter
{
    public function __construct(
        private \PDO            $pdo,
        private LoggerInterface $logger
    ) {}

    // -------------------------------------------------------------------------
    // 메뉴 트리 (사이드바용)
    // -------------------------------------------------------------------------
    public function getMenuTree(object $user): array
    {
        $uid = $user->uid ?? '';
        $rows = $this->fetchMenusByAuth($uid);
        if (empty($rows)) return [];

        return $this->buildTree(array_values($rows));
    }

    // -------------------------------------------------------------------------
    // 단일 메뉴 조회 (gubun 기준)
    // -------------------------------------------------------------------------
    public function getMenu(int $gubun): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, f.db_table
                   FROM mis_menus m
                   LEFT JOIN (
                       SELECT real_pid, MAX(db_table) as db_table
                       FROM mis_menu_fields WHERE db_table != \'\' GROUP BY real_pid
                   ) f ON f.real_pid = m.real_pid
                  WHERE m.idx = ? LIMIT 1'
            );
            $stmt->execute([$gubun]);
            $menu = $stmt->fetch() ?: [];
            return $this->applyMisJoinInheritance($menu);
        } catch (\Throwable $e) {
            $this->logger->warning('getMenu failed', ['gubun' => $gubun]);
            return [];
        }
    }

    public function getMenuByRealPid(string $realPid): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, f.db_table
                   FROM mis_menus m
                   LEFT JOIN (
                       SELECT real_pid, MAX(db_table) as db_table
                       FROM mis_menu_fields WHERE db_table != \'\' GROUP BY real_pid
                   ) f ON f.real_pid = m.real_pid
                  WHERE m.real_pid = ? LIMIT 1'
            );
            $stmt->execute([$realPid]);
            $menu = $stmt->fetch() ?: [];
            return $this->applyMisJoinInheritance($menu);
        } catch (\Throwable $e) {
            $this->logger->warning('getMenuByRealPid failed', ['real_pid' => $realPid]);
            return [];
        }
    }

    /**
     * menu_type='06' (MIS Join) 메뉴: mis_join_pid 메뉴에서 빈 속성 상속.
     * 상속 대상: table_name / base_filter / g01 / g02 / g03 / g07
     * 예) 6140 = MisJoin of 631 → 631 의 g03='Y' 상속하여 클라이언트가 recently=OFF 로 시작.
     */
    private function applyMisJoinInheritance(array $menu): array
    {
        if (!$menu || ($menu['menu_type'] ?? '') !== '06') return $menu;
        $joinRealPid = trim((string)($menu['mis_join_pid'] ?? ''));
        if ($joinRealPid === '') return $menu;

        try {
            $js = $this->pdo->prepare(
                'SELECT table_name, base_filter, g01, g02, g03, g07
                   FROM mis_menus WHERE real_pid = ? LIMIT 1'
            );
            $js->execute([$joinRealPid]);
            $parent = $js->fetch() ?: [];
        } catch (\Throwable) {
            return $menu;
        }

        foreach (['table_name', 'base_filter', 'g01', 'g02', 'g03', 'g07'] as $col) {
            $cur = trim((string)($menu[$col] ?? ''));
            $par = trim((string)($parent[$col] ?? ''));
            if ($cur === '' && $par !== '') {
                $menu[$col] = $parent[$col];
            }
        }
        return $menu;
    }

    // -------------------------------------------------------------------------
    // 접근 권한 확인
    // -------------------------------------------------------------------------
    public function canAccess(int $gubun, object $user): bool
    {
        if (($user->is_admin ?? '') === 'Y') return true;

        $menu = $this->getMenu($gubun);
        if (empty($menu)) return false;
        if (($menu['auth_code'] ?? '') === '') return true;

        $authCodes = $this->getUserAuthCodes($user);
        return in_array($menu['auth_code'], $authCodes, true);
    }

    // -------------------------------------------------------------------------
    // 사용자 권한 코드 목록
    // -------------------------------------------------------------------------
    public function getUserAuthCodes(object $user): array
    {
        if (($user->is_admin ?? '') === 'Y') return ['*'];

        $uid = $user->uid ?? '';
        if ($uid === '') return [];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT r.auth_code
                   FROM mis_group_members gm
                   JOIN mis_group_rules r ON r.group_idx = gm.group_idx
                  WHERE gm.user_id = ? AND r.useflag = \'1\''
            );
            $stmt->execute([$uid]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // 내부 헬퍼
    // -------------------------------------------------------------------------
    private function fetchMenusByAuth(string $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT
                    table_m.idx, table_m.real_pid, table_m.menu_name, table_m.brief_title,
                    table_m.menu_type, table_m.up_real_pid, table_m.sort_g2 AS sort_order,
                    table_m.auth_code, table_m.useflag, table_m.is_menu_hidden,
                    table_m.add_url, table_m.autogubun
                FROM mis_menus table_m
                LEFT JOIN mis_menus table_g2 ON table_g2.autogubun = LEFT(table_m.autogubun, 2)
                LEFT JOIN mis_menu_auth table_member ON table_member.real_pid = table_m.real_pid
                WHERE table_m.useflag = '1'
                  AND table_m.idx <> 1
                  AND IFNULL(table_m.g12, '') <> 'Y'
                  AND (IFNULL(table_m.auth_code, '') <> '02' OR table_member.user_id = ?)
                  AND IFNULL(table_member.authority_level, 0) <> 9
                  -- 상위메뉴가 히든이면 하위도 안 보임
                  AND LEFT(table_m.autogubun, 2) IN (
                      SELECT autogubun FROM mis_menus
                      WHERE useflag = '1' AND LENGTH(autogubun) = 2 AND IFNULL(is_menu_hidden, '') <> 'Y')
                  AND (LENGTH(table_m.autogubun) >= 4
                       AND LEFT(table_m.autogubun, 4) IN (
                           SELECT autogubun FROM mis_menus
                           WHERE useflag = '1' AND LENGTH(autogubun) = 4 AND IFNULL(is_menu_hidden, '') <> 'Y')
                       OR LENGTH(table_m.autogubun) < 4)
                  AND (LENGTH(table_m.autogubun) >= 6
                       AND LEFT(table_m.autogubun, 6) IN (
                           SELECT autogubun FROM mis_menus
                           WHERE useflag = '1' AND LENGTH(autogubun) = 6 AND IFNULL(is_menu_hidden, '') <> 'Y')
                       OR LENGTH(table_m.autogubun) < 6)
                  -- 최상위메뉴 권한 체크
                  AND (table_g2.auth_code = '' OR table_g2.auth_code = '01'
                       OR (SELECT COUNT(*) FROM mis_menu_auth WHERE real_pid = table_g2.real_pid AND user_id = ?) > 0)
                ORDER BY table_m.autogubun ASC, table_m.idx ASC"
            );
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            $this->logger->warning('fetchMenusByAuth failed', ['err' => $e->getMessage()]);
            return [];
        }
    }

    private function buildTree(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['real_pid']] = $row + ['children' => []];
        }

        $tree = [];
        foreach ($indexed as $pid => &$node) {
            $autoGubun = $node['autogubun'] ?? '';
            // autogubun 길이 2 = 최상위 메뉴 (예: '08', '09', '13')
            if (strlen($autoGubun) === 2) {
                $tree[] = &$node;
            } else {
                $upPid = $node['up_real_pid'] ?? '';
                if ($upPid !== '' && isset($indexed[$upPid])) {
                    $indexed[$upPid]['children'][] = &$node;
                }
                // 부모 없는 고아 노드는 무시
            }
        }
        unset($node);

        return $tree;
    }
}
