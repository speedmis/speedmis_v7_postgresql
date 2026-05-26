<?php
/**
 * alias_name 전수조사 재생성
 * v6 aliasN_updateQuery_RealPid() 동일 규칙
 * mbstring 없이 동작
 */

require_once __DIR__ . '/../vendor/autoload.php';
// dotenv 는 필요할 때만 — 이미 호출측에서 로드돼 있으면 재로드 안 함
if (empty($_ENV['DB_HOST'])) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

// ── mbstring 폴리필 ──
if (!function_exists('mb_ord')) {
    function mb_ord(string $c, string $enc = 'UTF-8'): int {
        $b = unpack('C*', $c);
        $n = count($b);
        if ($n === 1) return $b[1];
        if ($n === 2) return (($b[1] & 0x1F) << 6) | ($b[2] & 0x3F);
        if ($n === 3) return (($b[1] & 0x0F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F);
        if ($n === 4) return (($b[1] & 0x07) << 18) | (($b[2] & 0x3F) << 12) | (($b[3] & 0x3F) << 6) | ($b[4] & 0x3F);
        return 0;
    }
}
if (!function_exists('mb_chr')) {
    function mb_chr(int $cp, string $enc = 'UTF-8'): string {
        if ($cp < 0x80) return chr($cp);
        if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        if ($cp < 0x10000) return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, string $enc = 'UTF-8'): int {
        return preg_match_all('/./us', $s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null, string $enc = 'UTF-8'): string {
        preg_match_all('/./us', $s, $m);
        $chars = $m[0];
        $slice = $len === null ? array_slice($chars, $start) : array_slice($chars, $start, $len);
        return implode('', $slice);
    }
}

// ── 한글 로마자 변환 ──
function romanizeKorean(string $char): string {
    $cp = mb_ord($char, 'UTF-8');
    $s = $cp - 0xAC00;
    $ii = intdiv($s, 21 * 28);
    $mi = intdiv($s % (21 * 28), 28);
    $fi = $s % 28;
    $initials = ['g','kk','n','d','tt','r','m','b','pp','s','ss','','j','jj','ch','k','t','p','h'];
    $medials  = ['a','ae','ya','yae','eo','e','yeo','ye','o','wa','wae','oe','yo','u','wo','we','wi','yu','eu','ui','i'];
    $finals   = ['','k','kk','ks','n','nj','nh','t','l','lk','lm','lb','ls','lt','lp','lh','m','p','ps','s','ss','ng','j','ch','k','t','p','h'];
    return ($initials[$ii] ?? '') . ($medials[$mi] ?? '') . ($finals[$fi] ?? '');
}

function romanizeHiragana(string $char): string {
    static $map = ['あ'=>'a','い'=>'i','う'=>'u','え'=>'e','お'=>'o','か'=>'ka','き'=>'ki','く'=>'ku','け'=>'ke','こ'=>'ko','さ'=>'sa','し'=>'shi','す'=>'su','せ'=>'se','そ'=>'so','た'=>'ta','ち'=>'chi','つ'=>'tsu','て'=>'te','と'=>'to','な'=>'na','に'=>'ni','ぬ'=>'nu','ね'=>'ne','の'=>'no','は'=>'ha','ひ'=>'hi','ふ'=>'fu','へ'=>'he','ほ'=>'ho','ま'=>'ma','み'=>'mi','む'=>'mu','め'=>'me','も'=>'mo','や'=>'ya','ゆ'=>'yu','よ'=>'yo','ら'=>'ra','り'=>'ri','る'=>'ru','れ'=>'re','ろ'=>'ro','わ'=>'wa','を'=>'wo','ん'=>'n'];
    return $map[$char] ?? '';
}

function newAliasName(string $text): string {
    $text = mb_substr($text, 0, 50, 'UTF-8');
    if (trim($text) === '') return '';
    $result = '';
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        $cp = mb_ord($char, 'UTF-8');
        if ($cp < 128) { $result .= $char; continue; }
        if ($cp >= 0xAC00 && $cp <= 0xD7A3) { $result .= romanizeKorean($char); continue; }
        if ($cp >= 0x3041 && $cp <= 0x3096) { $result .= romanizeHiragana($char); continue; }
        if ($cp >= 0x30A1 && $cp <= 0x30FA) { $result .= romanizeHiragana(mb_chr($cp - 0x60, 'UTF-8')); continue; }
        if (($cp >= 0x4E00 && $cp <= 0x9FFF) || ($cp >= 0x3400 && $cp <= 0x4DBF)) { $result .= 'z'; continue; }
        // 기타 문자 건너뜀
    }
    return trim(preg_replace('/\s+/', ' ', $result));
}

function aliasN(string $han): string {
    if (strpos($han, ',') !== false) {
        $parts = explode(',', $han);
        $han = $parts[1] ?? '';
    }
    $remove = [' ',',','*',"'",'-',':','[',']','+','(',')','/','|','.','~','!','@','#','$','^','&','\\','=','`','}','{','"',';','?','<','>'];
    $alias = str_replace($remove, '', $han);
    if ($alias !== '' && ctype_digit($alias[0])) $alias = 'numQ' . $alias;
    $alias = newAliasName($alias);
    $alias = str_replace('%', '', $alias);
    // 아직 멀티바이트 남아있으면 urlencode 후 % 제거
    if (mb_strlen($alias, 'UTF-8') !== strlen($alias)) {
        $alias = str_replace('%', '', urlencode($alias));
    }
    return $alias;
}

// ── 메인: 주어진 real_pid 들의 alias_name 재생성. 변경된 행 수 리턴. ──
function aliasFixForRealPids(PDO $pdo, array $realPids): int {
    $upd = $pdo->prepare('UPDATE mis_menu_fields SET alias_name=? WHERE idx=?');
    $totalUpdated = 0;

    foreach ($realPids as $realPid) {
        $stmt = $pdo->prepare('SELECT idx, alias_name, db_table, db_field, col_title FROM mis_menu_fields WHERE real_pid=? ORDER BY sort_order ASC, idx ASC');
        $stmt->execute([$realPid]);
        $fields = $stmt->fetchAll();

        $aliasList = ';;';

        foreach ($fields as $f) {
            $idx      = $f['idx'];
            $dbTable  = str_replace("\t", '', trim($f['db_table'] ?? ''));
            $dbField  = str_replace("\t", '', trim($f['db_field'] ?? ''));
            $colTitle = str_replace("\t", '', trim($f['col_title'] ?? ''));
            $curAlias = str_replace("\t", '', trim($f['alias_name'] ?? ''));

            $newAlias = '';

            // 규칙1: qq → 유지
            if (substr($curAlias, 0, 2) === 'qq') {
                $newAlias = $curAlias;
            }
            // 규칙2: table_m → db_field 그대로 (대소문자 존중)
            elseif ($dbTable === 'table_m') {
                $newAlias = $dbField;
            }
            // 규칙3: JOIN → {table}Qn{field} (대소문자 존중)
            elseif ($dbTable !== '') {
                if ($dbField === 'uid')
                    $newAlias = 'eX_' . $dbTable . 'Qn' . $dbField;
                else
                    $newAlias = $dbTable . 'Qn' . $dbField;
            }
            // 규칙4: 단순식 → db_field (.→Qm)
            elseif ($dbField !== '' && strpos($dbField, ' ') === false && strpos($dbField, "'") === false && strpos($dbField, '+') === false && strpos($dbField, '(') === false) {
                $newAlias = str_replace('.', 'Qm', $dbField);
            }
            // 규칙5: 복합 → z + col_title
            else {
                $title = (strpos($colTitle, ',') !== false) ? explode(',', $colTitle)[1] : $colTitle;
                $newAlias = 'z' . $title;
            }

            // aliasN 정규화
            $newAlias = aliasN($newAlias);

            // db_field 비어있으면 alias도 비움
            if ($dbField === '') {
                $newAlias = '';
            } else {
                // 중복: sort_order,idx 순서로 같은 alias 카운트
                $cnt = substr_count($aliasList, ';' . $newAlias . ';');
                $aliasList .= $newAlias . ';;';
                if ($cnt > 0) {
                    $newAlias = $newAlias . 'Q' . $cnt;
                }
            }

            // 50자
            $newAlias = substr($newAlias, 0, 50);

            if ($newAlias !== $curAlias) {
                $upd->execute([$newAlias, $idx]);
                $totalUpdated++;
            }
        }
    }
    return $totalUpdated;
}

// ── CLI 직접 실행 시: 전체 real_pid 처리 ──
if (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath($_SERVER['argv'][0]) === __FILE__) {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
        $_ENV['DB_USER'], $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $menus = $pdo->query("SELECT DISTINCT real_pid FROM mis_menu_fields WHERE real_pid<>'' ORDER BY real_pid")->fetchAll(PDO::FETCH_COLUMN);
    echo "Total updated: " . aliasFixForRealPids($pdo, $menus) . "\n";
}
