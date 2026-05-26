<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * 파일 업로드 / 다운로드 / 삭제 (v7 mis_attach_list 기반)
 *
 * 흐름:
 *   1) uploadTemp()   — 파일 선택 즉시 /uploadFiles/_temp/{userId}/{token}/ 에 저장
 *   2) finalize()     — 레코드 저장 시 temp 파일을 /uploadFiles/{table}/{field}/{idx}/ 로 이동
 *                       + mis_attach_list 에 INSERT (같은 묶음은 마지막 삽입된 idx 를 midx 로 공유)
 *                       + {field} = 'file1@AND@file2...' / {field}_midx = midx 반환
 *   3) listByMidx()   — 저장된 파일 목록 (미리보기/다운로드용)
 *   4) deleteByIdx()  — 개별 파일 삭제
 */
class FileManager
{
    private const MAX_SIZE = 20 * 1024 * 1024; // 20 MB

    public function __construct(
        private \PDO            $pdo,
        private LoggerInterface $logger
    ) {}

    // =========================================================================
    // 1) 임시 업로드
    // =========================================================================
    public function uploadTemp(UploadedFileInterface $file, string $userId): array
    {
        $error = $file->getError();
        if ($error !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => "업로드 오류 코드: {$error}"];
        }
        $size = $file->getSize();
        if ($size > self::MAX_SIZE) {
            return ['success' => false, 'message' => '파일 크기가 20MB를 초과합니다.'];
        }

        $origName = $file->getClientFilename() ?? 'unknown';
        $mime     = $file->getClientMediaType() ?? 'application/octet-stream';
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 고유 토큰 폴더
        $token    = bin2hex(random_bytes(12));
        $safeUid  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $userId) ?: 'anon';
        $tempDir  = UPLOAD_TEMP_PATH . "/{$safeUid}/{$token}";
        if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

        // 원본 파일명 그대로 보존 (같은 이름 충돌 방지 위해 디렉터리가 토큰 단위)
        $destPath = $tempDir . '/' . $origName;
        try {
            // moveTo() 는 Windows IIS 환경에서 PHP upload-tmp 의 제한된 ACL 을
            // 그대로 가져와 IIS 가 못 읽는 0-perm 파일이 됨.
            // → 스트림으로 새 파일을 직접 쓰면 부모 디렉터리 ACL 을 상속받음.
            $stream = $file->getStream();
            $stream->rewind();
            $out = @fopen($destPath, 'wb');
            if ($out === false) throw new \RuntimeException("temp 파일 생성 실패: {$destPath}");
            while (!$stream->eof()) {
                fwrite($out, $stream->read(8192));
            }
            fclose($out);
            @chmod($destPath, 0644);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '임시 저장 실패: ' . $e->getMessage()];
        }

        return [
            'success'   => true,
            'token'     => $token,
            'orig_name' => $origName,
            'size'      => $size,
            'mime'      => $mime,
        ];
    }

    // =========================================================================
    // 2) 최종 이동 + mis_attach_list 등록
    //
    // $tempTokens = [token, token, ...]  (같은 순서로 파일이 저장됨)
    // 반환: ['midx' => N, 'file_names' => 'a.jpg@AND@b.jpg', 'count' => k]
    // =========================================================================
    public function finalize(
        string $userId,
        string $tableName,
        string $fieldName,
        string $idxName,
        int|string $idxNum,
        array $tempTokens,
        int $existingMidx = 0,
        ?string $customPathTemplate = null
    ): array {
        if (empty($tempTokens)) {
            return ['success' => true, 'midx' => 0, 'file_names' => '', 'count' => 0];
        }

        $useCustomPath = ($customPathTemplate !== null && $customPathTemplate !== '');
        $isCustomSql   = $useCustomPath && stripos(trim($customPathTemplate), 'select ') === 0;

        $safeUid  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $userId) ?: 'anon';

        // 기본 경로 (customPath 없을 때만 사용)
        $finalDir = UPLOAD_FILES_PATH . "/{$tableName}/{$fieldName}/{$idxNum}";
        $webBase  = "/uploadFiles/{$tableName}/{$fieldName}/{$idxNum}";
        if (!$useCustomPath) {
            if (!is_dir($finalDir)) @mkdir($finalDir, 0755, true);
        }

        $inserted = []; // [['idx', 'orig_name', 'url']]
        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                "INSERT INTO mis_attach_list
                    (midx, table_name, field_name, idx_name, idx_num,
                     attach_url, attach_size, attach_mime, attach_name,
                     download, useflag, wdate, wdater)
                 VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, 0, '1', NOW(), ?)"
            );

            foreach ($tempTokens as $token) {
                $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
                if ($token === '') continue;

                $tempDir = UPLOAD_TEMP_PATH . "/{$safeUid}/{$token}";
                if (!is_dir($tempDir)) continue;

                $entries = @scandir($tempDir) ?: [];
                $files   = array_values(array_filter($entries, fn($e) => $e !== '.' && $e !== '..'));
                if (empty($files)) continue;

                $origName = $files[0];
                $srcPath  = $tempDir . '/' . $origName;

                // ── 커스텀 경로 결정 ──
                if ($useCustomPath) {
                    $customUrl = $customPathTemplate;
                    if ($isCustomSql) {
                        try {
                            $customUrl = (string)$this->pdo->query(trim($customPathTemplate))->fetchColumn();
                        } catch (\Throwable $e) {
                            $this->logger->warning('attach customPath SQL failed', ['sql' => $customPathTemplate, 'err' => $e->getMessage()]);
                            $customUrl = '';
                        }
                    }
                    if ($customUrl === '') continue;

                    // /data/item/   → SHOP_DATA_ROOT (.env) 우선, 없으면 BASE_PATH/public  (영카트 호환)
                    // 그 외 / 시작 → BASE_PATH 하위 절대 웹경로
                    // 그 외          → /uploadFiles/ 하위
                    if (str_starts_with($customUrl, '/data/item/')) {
                        $envRoot  = trim((string)($_ENV['SHOP_DATA_ROOT'] ?? ''));
                        $shopRoot = $envRoot !== '' ? $envRoot : BASE_PATH . '/public';
                        $destPath = $shopRoot . $customUrl;
                        $url      = $customUrl;
                    } elseif (str_starts_with($customUrl, '/')) {
                        $destPath = BASE_PATH . $customUrl;
                        $url      = $customUrl;
                    } else {
                        $destPath = UPLOAD_FILES_PATH . '/' . $customUrl;
                        $url      = '/uploadFiles/' . $customUrl;
                    }
                    $destDir  = dirname($destPath);
                    $saveName = basename($destPath);
                    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                } else {
                    // ── 기본 경로 ──
                    $destPath = $finalDir . '/' . $origName;
                    if (file_exists($destPath)) {
                        $base = pathinfo($origName, PATHINFO_FILENAME);
                        $ext  = pathinfo($origName, PATHINFO_EXTENSION);
                        $n    = 1;
                        do {
                            $altName  = $base . "_{$n}" . ($ext !== '' ? ".{$ext}" : '');
                            $destPath = $finalDir . '/' . $altName;
                            $n++;
                        } while (file_exists($destPath));
                        $origName = basename($destPath);
                    }
                    $saveName = $origName;
                    $url = "{$webBase}/{$origName}";
                }

                if (!@copy($srcPath, $destPath)) {
                    throw new \RuntimeException("임시 파일 복사 실패: {$srcPath}");
                }
                @chmod($destPath, 0644);
                @unlink($srcPath);

                $size = @filesize($destPath) ?: 0;
                $mime = @mime_content_type($destPath) ?: 'application/octet-stream';

                $ins->execute([
                    $tableName, $fieldName, $idxName, (string)$idxNum,
                    $url, $size, $mime, $saveName,
                    $userId,
                ]);
                $attachIdx = (int)$this->pdo->lastInsertId();
                $inserted[] = ['idx' => $attachIdx, 'orig_name' => $saveName, 'url' => $url];

                @rmdir($tempDir);
            }

            if (empty($inserted)) {
                $this->pdo->commit();
                return ['success' => true, 'midx' => 0, 'file_names' => '', 'count' => 0];
            }

            // midx = 항상 새 batch 의 마지막 삽입 idx
            $midx = (int)end($inserted)['idx'];
            $idsCsv = implode(',', array_map(fn($r) => (int)$r['idx'], $inserted));
            $this->pdo->exec("UPDATE mis_attach_list SET midx = {$midx} WHERE idx IN ({$idsCsv})");

            // 기존 midx 그룹 정리
            if ($existingMidx > 0 && $existingMidx !== $midx) {
                // 삭제된(useflag='0') 행 → hard delete
                $this->pdo->prepare(
                    "DELETE FROM mis_attach_list WHERE midx = ? AND useflag = '0'"
                )->execute([$existingMidx]);

                // 아직 활성인(useflag='1') 행 → 새 midx 그룹으로 이전
                $this->pdo->prepare(
                    "UPDATE mis_attach_list SET midx = ? WHERE midx = ? AND useflag = '1'"
                )->execute([$midx, $existingMidx]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('finalize failed', ['err' => $e->getMessage()]);
            return ['success' => false, 'message' => '파일 저장 실패: ' . $e->getMessage()];
        }

        // 커스텀 경로: 필드에 URL 경로를 저장 / 기본: 파일명을 저장
        if ($useCustomPath) {
            $allUrls = array_map(fn($r) => $r['url'], $inserted);
            // 기존 활성 파일의 URL 도 포함
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT attach_url FROM mis_attach_list
                      WHERE midx = ? AND useflag = '1' ORDER BY idx ASC"
                );
                $stmt->execute([$midx]);
                $allUrls = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'attach_url');
            } catch (\Throwable) {}

            return [
                'success'    => true,
                'midx'       => $midx,
                'file_names' => implode('@AND@', $allUrls),
                'count'      => count($inserted),
            ];
        }

        // 기본 모드: 파일명으로 저장
        $allNames = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT attach_name FROM mis_attach_list
                  WHERE midx = ? AND useflag = '1' ORDER BY idx ASC"
            );
            $stmt->execute([$midx]);
            $allNames = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'attach_name');
        } catch (\Throwable) {
            $allNames = array_map(fn($r) => $r['orig_name'], $inserted);
        }

        return [
            'success'    => true,
            'midx'       => $midx,
            'file_names' => implode('@AND@', $allNames),
            'count'      => count($inserted),
        ];
    }

    // =========================================================================
    // 3) midx 기준 파일 목록
    // =========================================================================
    public function listByMidx(int $midx): array
    {
        if ($midx <= 0) return [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT idx, attach_name, attach_url, attach_size, attach_mime, download, wdate
                   FROM mis_attach_list
                  WHERE midx = ? AND useflag = '1'
                  ORDER BY idx ASC"
            );
            $stmt->execute([$midx]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    // =========================================================================
    // 4) 다운로드 경로 획득
    // =========================================================================
    public function getFilePath(int $attachIdx): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT attach_name, attach_url, attach_mime, useflag
                   FROM mis_attach_list WHERE idx = ? LIMIT 1"
            );
            $stmt->execute([$attachIdx]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || $row['useflag'] !== '1') return null;

            // attach_url 은 /uploadFiles/... 로 시작 → BASE_PATH 에서 /uploadFiles 이하 붙여서 실제 경로
            $rel = ltrim((string)$row['attach_url'], '/');
            $full = BASE_PATH . '/' . $rel;
            if (!file_exists($full)) return null;

            // 다운로드 카운트 증가
            $this->pdo->prepare("UPDATE mis_attach_list SET download = IFNULL(download,0)+1 WHERE idx = ?")
                ->execute([$attachIdx]);

            return [
                'path'      => $full,
                'orig_name' => $row['attach_name'] ?? basename($full),
                'mime_type' => $row['attach_mime'] ?? 'application/octet-stream',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================================================================
    // 5) 개별 파일 삭제 (useflag='0' 마킹)
    // =========================================================================
    public function deleteByIdx(int $attachIdx, string $userId, bool $isAdmin = false): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT idx, attach_url, wdater FROM mis_attach_list WHERE idx = ? LIMIT 1"
            );
            $stmt->execute([$attachIdx]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return ['success' => false, 'message' => '파일을 찾을 수 없습니다.'];

            if (!$isAdmin && ($row['wdater'] ?? '') !== $userId) {
                return ['success' => false, 'message' => '삭제 권한이 없습니다.'];
            }

            // 소프트 삭제
            $this->pdo->prepare("UPDATE mis_attach_list SET useflag = '0' WHERE idx = ?")->execute([$attachIdx]);

            // 물리 파일도 제거 (복구 불필요 시)
            $rel = ltrim((string)$row['attach_url'], '/');
            @unlink(BASE_PATH . '/' . $rel);

            return ['success' => true, 'message' => '삭제되었습니다.'];
        } catch (\Throwable $e) {
            $this->logger->error('file delete failed', ['err' => $e->getMessage()]);
            return ['success' => false, 'message' => '삭제 실패'];
        }
    }
}
