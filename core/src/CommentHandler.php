<?php

namespace App;

/**
 * 댓글(네이버 스타일) 처리 — mis_comments / mis_comments_like.
 *
 * 댓글은 부모 레코드(real_pid + midx)에 종속:
 *   - real_pid = 부모 프로그램(예: speedmis000314), midx = 부모 레코드 idx
 *   - refidx   = 스레드 루트. 원댓글은 idx==refidx, 답글은 refidx=원댓글 idx
 *   - sel_like/sel_hate = 집계 캐시, 개인별 공감/비공감은 mis_comments_like
 *
 * DBMS 무관: NOW()/GETDATE() 같은 함수 대신 PHP datetime 을 바인딩한다(6사이트 공유 core).
 */
class CommentHandler
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** (real_pid, midx) 의 useflag=1 댓글을 스레드(원댓글 + 답글) 구조로 반환. */
    public function listComments(string $realPid, string $midx, string $userId): array
    {
        if ($realPid === '' || $midx === '') {
            return ['success' => true, 'comments' => [], 'total' => 0];
        }
        try {
            $sql = "SELECT c.idx, c.midx, c.contents, c.refidx,
                           c.sel_like, c.sel_hate, c.wdate, c.wdater, c.lastupdate,
                           u.user_name AS author_name, s.station_name AS station_name,
                           (SELECT lk.like_or_hate FROM mis_comments_like lk
                             WHERE lk.comments_idx = c.idx AND lk.wdater = ? LIMIT 1) AS my_lh
                    FROM mis_comments c
                    LEFT JOIN mis_users u ON u.user_id = c.wdater
                    LEFT JOIN mis_stations s ON s.idx = u.station_idx
                    WHERE c.real_pid = ? AND c.midx = ? AND c.useflag = '1'
                    ORDER BY c.refidx, c.idx";
            $st = $this->pdo->prepare($sql);
            $st->execute([$userId, $realPid, (string)$midx]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return ['success' => true, 'comments' => [], 'total' => 0];
        }

        $tops = [];
        $replies = [];
        foreach ($rows as $r) {
            $r['idx']      = (int)$r['idx'];
            $r['refidx']   = (int)$r['refidx'];
            $r['sel_like'] = (int)$r['sel_like'];
            $r['sel_hate'] = (int)$r['sel_hate'];
            $r['isMine']   = ((string)$r['wdater'] === $userId && $userId !== '');
            $r['author_name'] = (string)($r['author_name'] ?? '') ?: (string)$r['wdater'];
            if ($r['idx'] === $r['refidx']) {
                $r['replies'] = [];
                $tops[$r['idx']] = $r;
            } else {
                $replies[$r['refidx']][] = $r;
            }
        }
        foreach ($replies as $rid => $reps) {
            if (isset($tops[$rid])) {
                $tops[$rid]['replies'] = $reps;          // 답글은 작성순(idx asc)
            }
        }
        $tops = array_values($tops);
        usort($tops, fn($a, $b) => $b['idx'] <=> $a['idx']); // 원댓글 최신순

        $total = count($tops);
        foreach ($tops as $t) {
            $total += count($t['replies']);
        }
        return ['success' => true, 'comments' => $tops, 'total' => $total];
    }

    /** 댓글/답글 작성. refidx<=0 → 원댓글(새 idx 로 refidx 채움), refidx>0 → 답글. */
    public function write(string $realPid, string $midx, string $contents, int $refidx, string $userId): array
    {
        $contents = trim($contents);
        if ($realPid === '' || $midx === '' || $contents === '') {
            return ['success' => false, 'message' => '내용을 입력하세요.'];
        }
        if ($userId === '') {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        try {
            // 부모 프로그램의 실제 테이블명 (mis_comments.table_m 기록용)
            $tm = $this->pdo->prepare("SELECT table_name FROM mis_menus WHERE real_pid = ? LIMIT 1");
            $tm->execute([$realPid]);
            $tableM = (string)($tm->fetchColumn() ?: '');

            $now = $this->now();
            $ins = $this->pdo->prepare(
                "INSERT INTO mis_comments
                   (table_m, excel_idxname, midx, real_pid, contents, refidx, useflag,
                    wdate, wdater, lastupdate, lastupdater, sel_like, sel_hate)
                 VALUES (?, 'idx', ?, ?, ?, ?, '1', ?, ?, ?, ?, 0, 0)"
            );
            $ins->execute([$tableM, (string)$midx, $realPid, $contents, max(0, $refidx), $now, $userId, $now, $userId]);
            $newIdx = (int)$this->pdo->lastInsertId();

            if ($refidx <= 0 && $newIdx > 0) {
                // 원댓글 → refidx = 자기 idx
                $this->pdo->prepare("UPDATE mis_comments SET refidx = ? WHERE idx = ?")->execute([$newIdx, $newIdx]);
            }
            return ['success' => true, 'newIdx' => $newIdx];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '저장 실패: ' . $e->getMessage()];
        }
    }

    /** soft-delete. 원댓글이면 그 답글까지 함께 숨김. 본인 또는 admin 만. */
    public function delete(int $idx, string $userId, bool $isAdmin): array
    {
        if ($idx <= 0) {
            return ['success' => false, 'message' => '잘못된 요청입니다.'];
        }
        try {
            $st = $this->pdo->prepare("SELECT wdater, refidx FROM mis_comments WHERE idx = ? LIMIT 1");
            $st->execute([$idx]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return ['success' => false, 'message' => '존재하지 않는 댓글입니다.'];
            }
            if (!$isAdmin && (string)$row['wdater'] !== $userId) {
                return ['success' => false, 'message' => '본인 댓글만 삭제할 수 있습니다.'];
            }
            if ((int)$row['refidx'] === $idx) {
                $this->pdo->prepare("UPDATE mis_comments SET useflag = '0' WHERE refidx = ?")->execute([$idx]);
            } else {
                $this->pdo->prepare("UPDATE mis_comments SET useflag = '0' WHERE idx = ?")->execute([$idx]);
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '삭제 실패: ' . $e->getMessage()];
        }
    }

    /** 내용 수정 — 본인 또는 admin. 내용은 raw 저장(렌더 시 '<' 시작이면 HTML 로 해석). */
    public function update(int $idx, string $contents, string $userId, bool $isAdmin): array
    {
        $contents = trim($contents);
        if ($idx <= 0 || $contents === '') {
            return ['success' => false, 'message' => '내용을 입력하세요.'];
        }
        try {
            $st = $this->pdo->prepare("SELECT wdater FROM mis_comments WHERE idx = ? LIMIT 1");
            $st->execute([$idx]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return ['success' => false, 'message' => '존재하지 않는 댓글입니다.'];
            }
            if (!$isAdmin && (string)$row['wdater'] !== $userId) {
                return ['success' => false, 'message' => '본인 댓글만 수정할 수 있습니다.'];
            }
            $this->pdo->prepare("UPDATE mis_comments SET contents = ?, lastupdate = ?, lastupdater = ? WHERE idx = ?")
                      ->execute([$contents, $this->now(), $userId, $idx]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '수정 실패: ' . $e->getMessage()];
        }
    }

    /** 공감(L)/비공감(H) 토글. 같은 걸 다시 누르면 해제, 반대편이 이미 있으면 안내 후 무변경(v6 동일). */
    public function toggleLike(int $commentsIdx, string $lh, string $userId): array
    {
        if ($commentsIdx <= 0 || !in_array($lh, ['L', 'H'], true)) {
            return ['success' => false, 'message' => '잘못된 요청입니다.'];
        }
        if ($userId === '') {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        try {
            $st = $this->pdo->prepare("SELECT like_or_hate FROM mis_comments_like WHERE comments_idx = ? AND wdater = ? LIMIT 1");
            $st->execute([$commentsIdx, $userId]);
            $cur = (string)($st->fetchColumn() ?: '');

            $msg = '';
            $result = '';
            if ($cur === '') {
                $this->pdo->prepare("INSERT INTO mis_comments_like (comments_idx, like_or_hate, wdate, wdater) VALUES (?, ?, ?, ?)")
                          ->execute([$commentsIdx, $lh, $this->now(), $userId]);
                $result = $lh;
            } elseif ($cur === $lh) {
                $this->pdo->prepare("DELETE FROM mis_comments_like WHERE comments_idx = ? AND wdater = ?")
                          ->execute([$commentsIdx, $userId]);
                $result = '';
            } else {
                $msg = $cur === 'L' ? '이미 공감한 글입니다.' : '이미 비공감한 글입니다.';
                $result = $cur;
            }

            $cl = $this->pdo->prepare("SELECT COUNT(*) FROM mis_comments_like WHERE comments_idx = ? AND like_or_hate = 'L'");
            $cl->execute([$commentsIdx]);
            $cntL = (int)$cl->fetchColumn();
            $ch = $this->pdo->prepare("SELECT COUNT(*) FROM mis_comments_like WHERE comments_idx = ? AND like_or_hate = 'H'");
            $ch->execute([$commentsIdx]);
            $cntH = (int)$ch->fetchColumn();
            $this->pdo->prepare("UPDATE mis_comments SET sel_like = ?, sel_hate = ? WHERE idx = ?")
                      ->execute([$cntL, $cntH, $commentsIdx]);

            return ['success' => true, 'resultLH' => $result, 'cntL' => $cntL, 'cntH' => $cntH, 'msg' => $msg];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '처리 실패: ' . $e->getMessage()];
        }
    }

    /** 목록 뱃지용 — real_pid 의 여러 midx 에 대한 원댓글(스레드) 갯수 맵 {midx: count}. */
    public function countsByMidx(string $realPid, array $midxs): array
    {
        $midxs = array_values(array_unique(array_filter(array_map('strval', $midxs), fn($v) => $v !== '')));
        if ($realPid === '' || !$midxs) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($midxs), '?'));
            $sql = "SELECT midx, COUNT(*) c FROM mis_comments
                    WHERE real_pid = ? AND useflag = '1' AND idx = refidx AND midx IN ($ph)
                    GROUP BY midx";
            $st = $this->pdo->prepare($sql);
            $st->execute(array_merge([$realPid], $midxs));
            $out = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(string)$r['midx']] = (int)$r['c'];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
