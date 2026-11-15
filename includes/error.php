<?php
declare(strict_types=1);

function logApplicationError(Throwable $error, string $context = 'application'): string
{
    try { $requestId = bin2hex(random_bytes(6)); }
    catch (Throwable) { $requestId = str_replace('.', '', uniqid('', true)); }
    $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $error->getMessage()) ?: 'Unknown error';
    error_log(sprintf('[news-portal] request=%s context=%s type=%s message=%s',
        $requestId,
        preg_replace('/[^a-z0-9_.-]/i', '_', $context),
        get_class($error),
        mb_substr($message, 0, 1000)
    ));
    return $requestId;
}

function errorHomeUrl(): string
{
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $normalize = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');
    $documentRoot = $normalize($documentRoot);
    $projectRoot = $normalize($projectRoot);
    if ($documentRoot !== '' && str_starts_with(strtolower($projectRoot . '/'), strtolower($documentRoot . '/'))) {
        $relative = substr($projectRoot, strlen($documentRoot));
        return ($relative === '' ? '' : '/' . trim($relative, '/')) . '/';
    }
    return '/';
}

function errorResponse(int $status, ?string $message = null, ?string $requestId = null): never
{
    $titles = [403 => 'غير مصرح', 404 => 'الصفحة غير موجودة', 500 => 'خطأ في الخادم', 503 => 'الخدمة غير متاحة'];
    $messages = [403 => 'ليس لديك صلاحية للوصول إلى هذه الصفحة.', 404 => 'تعذر العثور على الصفحة أو العنصر المطلوب.', 500 => 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى لاحقًا.', 503 => 'الخدمة غير متاحة مؤقتًا. يرجى المحاولة بعد قليل.'];
    $title = $titles[$status] ?? 'حدث خطأ';
    $message ??= $messages[$status] ?? 'تعذر إكمال الطلب.';
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $title . ': ' . $message . PHP_EOL); exit(1); }
    if (!headers_sent()) { http_response_code($status); header('Content-Type: text/html; charset=UTF-8'); }
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $reference = $requestId ? '<p class="reference">رقم المرجع: ' . $escape($requestId) . '</p>' : '';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $status . ' | ' . $escape($title) . '</title><style>body{margin:0;background:#f4f7fb;color:#172033;font-family:Arial,sans-serif}.shell{min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(620px,100%);box-sizing:border-box;background:#fff;border-radius:18px;padding:48px;text-align:center;box-shadow:0 12px 35px #17203318}.code{font-size:64px;font-weight:800;color:#8b1538}.message{color:#64748b;line-height:1.8}.reference{font-size:13px;color:#94a3b8}.button{display:inline-block;margin-top:16px;padding:10px 22px;border-radius:9px;background:#8b1538;color:#fff;text-decoration:none}</style></head><body><main class="shell"><section class="card"><div class="code">' . $status . '</div><h1>' . $escape($title) . '</h1><p class="message">' . $escape($message) . '</p>' . $reference . '<a class="button" href="' . $escape(errorHomeUrl()) . '">العودة للرئيسية</a></section></main></body></html>';
    exit;
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $error): never { $requestId=logApplicationError($error); errorResponse(500,null,$requestId); });
    register_shutdown_function(static function (): void { $last=error_get_last(); if(!$last||!in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))return; $error=new ErrorException($last['message'],0,$last['type'],$last['file'],$last['line']); $requestId=logApplicationError($error,'fatal'); if(!headers_sent())errorResponse(500,null,$requestId); });
}
