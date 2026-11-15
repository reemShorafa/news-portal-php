<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    ini_set('session.cookie_secure', $https ? '1' : '0');
    session_name('NEWS_PORTAL_SESSION');
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function basePath(): string
{
    static $basePath = null;
    if ($basePath !== null) return $basePath;

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $normalize = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');
    $documentRoot = $normalize($documentRoot);
    $projectRoot = $normalize($projectRoot);

    if ($documentRoot !== '' && str_starts_with(strtolower($projectRoot . '/'), strtolower($documentRoot . '/'))) {
        $relative = substr($projectRoot, strlen($documentRoot));
        return $basePath = $relative === '' ? '' : '/' . trim($relative, '/');
    }

    // Fallback for unusual web-server aliases where the filesystem roots do not match.
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    if ($scriptName !== '' && $scriptFile !== '') {
        $relativeScript = ltrim(substr($normalize($scriptFile), strlen($projectRoot)), '/');
        if ($relativeScript !== '' && str_ends_with($scriptName, $relativeScript)) {
            return $basePath = rtrim(substr($scriptName, 0, -strlen($relativeScript)), '/');
        }
    }

    return $basePath = '';
}

function url(string $path = ''): string
{
    return basePath() . '/' . ltrim($path, '/');
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function csrfToken(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">' . submissionField('form'); }
function verifyCsrf(): void { if (!isset($_POST['csrf_token']) || !hash_equals(csrfToken(), (string)$_POST['csrf_token'])) errorResponse(403, 'رمز الحماية غير صالح أو انتهت صلاحيته. حدّث الصفحة وحاول مجددًا.'); verifySubmission('form'); }
function submissionField(string $scope): string { $token=bin2hex(random_bytes(24)); $_SESSION['submission_tokens'][$scope][$token]=time(); foreach($_SESSION['submission_tokens'][$scope] as $value=>$created){if($created<time()-3600)unset($_SESSION['submission_tokens'][$scope][$value]);} return '<input type="hidden" name="submission_token" value="'.e($token).'">'; }
function verifySubmission(string $scope): void { $token=(string)($_POST['submission_token']??''); if($token===''||empty($_SESSION['submission_tokens'][$scope][$token])) errorResponse(403,'تم إرسال هذا النموذج مسبقًا أو انتهت صلاحيته. حدّث الصفحة وحاول مجددًا.'); unset($_SESSION['submission_tokens'][$scope][$token]); }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function getFlash(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function setting(string $key, string $default = ''): string { static $cache = []; if (array_key_exists($key, $cache)) return $cache[$key]; $s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1'); $s->execute([$key]); return $cache[$key]=(string)($s->fetchColumn() ?: $default); }
function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function slugify(string $text): string { $text=trim(mb_strtolower($text)); $text=preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u','-',$text); return trim((string)$text,'-') ?: 'article'; }
function uniqueSlug(string $table, string $title, ?int $ignoreId=null): string { if(!in_array($table,['news','categories'],true)) throw new InvalidArgumentException(); $base=slugify($title); $slug=$base; $i=2; do { $sql="SELECT id FROM {$table} WHERE slug=?".($ignoreId?' AND id<>?':'').' LIMIT 1'; $s=db()->prepare($sql); $args=[$slug]; if($ignoreId)$args[]=$ignoreId; $s->execute($args); if(!$s->fetch()) return $slug; $slug=$base.'-'.$i++; } while(true); }
function formatDate(?string $date): string { if(!$date)return '—'; return date('Y/m/d',strtotime($date)); }
function excerpt(string $html, int $length=140): string { $text=trim(strip_tags($html)); return mb_strlen($text)>$length ? mb_substr($text,0,$length).'…' : $text; }
function sanitizeHtml(string $html): string
{
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><a>');
    $anchors = [];
    $prefix = '__SAFE_ANCHOR_' . bin2hex(random_bytes(8)) . '_';
    $html = preg_replace_callback('/<a\b([^>]*)>/iu', static function (array $match) use (&$anchors, $prefix): string {
        $href = '';
        if (preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/iu', $match[1], $parts)) {
            $href = html_entity_decode($parts[1] ?: ($parts[2] ?: ($parts[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', trim($href));
        $safe = $normalized !== '' && (
            (str_starts_with($normalized, '/') && !str_starts_with($normalized, '//'))
            || str_starts_with($normalized, '#')
            || str_starts_with($normalized, '?')
            || (filter_var($normalized, FILTER_VALIDATE_URL)
                && in_array(strtolower((string)parse_url($normalized, PHP_URL_SCHEME)), ['http', 'https', 'mailto'], true))
        );
        $anchor = $safe ? '<a href="' . e($normalized) . '" rel="noopener noreferrer">' : '<a>';
        $token = $prefix . count($anchors) . '__';
        $anchors[$token] = $anchor;
        return $token;
    }, $html) ?? '';
    $html = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|blockquote|a)\b[^>]*>/iu', '<$1>', $html) ?? '';
    return strtr($html, $anchors);
}
function uploadImage(string $field, ?string $old=null): ?string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $old;
    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($f['tmp_name'] ?? ''))) throw new RuntimeException('تعذر رفع الصورة.');
    $size = (int)($f['size'] ?? 0);
    if ($size < 1 || $size > 5*1024*1024) throw new RuntimeException('حجم الصورة غير صالح أو يتجاوز 5 ميجابايت.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $info = @getimagesize($f['tmp_name']);
    if (!isset($types[$mime]) || !$info || ($info['mime'] ?? '') !== $mime) throw new RuntimeException('ملف الصورة غير صالح أو صيغته غير مدعومة.');
    if ((int)$info[0] < 1 || (int)$info[1] < 1 || (int)$info[0] * (int)$info[1] > 25_000_000) throw new RuntimeException('أبعاد الصورة غير صالحة أو كبيرة جدًا.');
    $name = bin2hex(random_bytes(16)).'.'.$types[$mime];
    if (!move_uploaded_file($f['tmp_name'],__DIR__.'/../uploads/'.$name)) throw new RuntimeException('تعذر حفظ الصورة.');
    return $name;
}
function articleValidationErrors(string $title, string $summary, string $details, int $categoryId, string $seoTitle, string $seoDescription): array
{
    $errors = [];
    $titleLength = mb_strlen($title);
    if ($titleLength < 5 || $titleLength > 200) $errors[] = 'يجب أن يكون عنوان الخبر بين 5 و200 حرف.';
    $summaryLength = mb_strlen($summary);
    if ($summaryLength < 10 || $summaryLength > 5000) $errors[] = 'يجب أن يكون الملخص بين 10 و5000 حرف.';
    $detailsLength = mb_strlen(trim(strip_tags($details)));
    if ($detailsLength < 20 || mb_strlen($details) > 1_000_000) $errors[] = 'تفاصيل الخبر قصيرة جدًا أو تتجاوز الحد المسموح.';
    if (mb_strlen($seoTitle) > 200) $errors[] = 'عنوان SEO يتجاوز 200 حرف.';
    if (mb_strlen($seoDescription) > 255) $errors[] = 'وصف SEO يتجاوز 255 حرف.';
    if ($categoryId < 1) {
        $errors[] = 'اختر تصنيفًا.';
    } else {
        $statement = db()->prepare('SELECT 1 FROM categories WHERE id = ? LIMIT 1');
        $statement->execute([$categoryId]);
        if (!$statement->fetchColumn()) $errors[] = 'التصنيف المحدد غير موجود.';
    }
    return $errors;
}
function imageUrl(?string $name): string { return $name ? url('uploads/'.rawurlencode($name)) : 'data:image/svg+xml;charset=UTF-8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><rect width="100%" height="100%" fill="#e9edf3"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#64748b" font-family="Arial" font-size="32">بوابة الأخبار</text></svg>'); }
function statusLabel(string $s): string { return ['draft'=>'مسودة','pending'=>'قيد المراجعة','published'=>'منشور','archived'=>'مؤرشف'][$s] ?? $s; }
function statusClass(string $s): string { return ['draft'=>'secondary','pending'=>'warning','published'=>'success','archived'=>'dark'][$s] ?? 'secondary'; }
function paginate(int $total,int $perPage=10): array { $pages=max(1,(int)ceil($total/$perPage)); $page=min($pages,max(1,(int)($_GET['page']??1))); return [$page,$pages,($page-1)*$perPage]; }
function paginationLinks(int $page,int $pages): string { if($pages<=1)return ''; $q=$_GET; $out='<nav><ul class="pagination justify-content-center">'; for($i=1;$i<=$pages;$i++){ $q['page']=$i; $out.='<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="?'.e(http_build_query($q)).'">'.$i.'</a></li>'; } return $out.'</ul></nav>'; }
