<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);
    if ($value === false) {
        throw new RuntimeException('No input was received.');
    }
    return trim($value);
}

function hiddenPrompt(string $label): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $escaped = str_replace("'", "''", $label);
        $command = 'powershell.exe -NoProfile -Command "'
            . '$secure = Read-Host -Prompt \'' . $escaped . '\' -AsSecureString; '
            . '$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }"';
        $value = shell_exec($command);
        if ($value === null) {
            throw new RuntimeException('Unable to read a hidden password from PowerShell.');
        }
        return rtrim($value, "\r\n");
    }

    fwrite(STDOUT, $label);
    $stty = shell_exec('stty -g 2>/dev/null');
    if ($stty === null || trim($stty) === '') {
        throw new RuntimeException('A terminal is required for hidden password input.');
    }
    shell_exec('stty -echo');
    try {
        $value = fgets(STDIN);
    } finally {
        shell_exec('stty ' . escapeshellarg(trim($stty)));
        fwrite(STDOUT, PHP_EOL);
    }
    if ($value === false) {
        throw new RuntimeException('No password was received.');
    }
    return rtrim($value, "\r\n");
}

function validPassword(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

try {
    $pdo = db();
    $admins = $pdo->query(
        "SELECT id, name, email, status FROM users WHERE role = 'admin' ORDER BY id"
    )->fetchAll();

    if (!$admins) {
        throw new RuntimeException(
            'No existing administrator was found. Use the protected setup-admin.php page locally instead.'
        );
    }

    fwrite(STDOUT, "Existing administrators:" . PHP_EOL);
    foreach ($admins as $admin) {
        printf(
            "  [%d] %s <%s> (%s)%s",
            $admin['id'],
            $admin['name'],
            $admin['email'],
            $admin['status'],
            PHP_EOL
        );
    }

    if (count($admins) === 1) {
        $selected = $admins[0];
        fwrite(STDOUT, "Using administrator ID {$selected['id']}." . PHP_EOL);
    } else {
        $adminId = (int)prompt('Administrator ID to reset: ');
        $selected = null;
        foreach ($admins as $admin) {
            if ((int)$admin['id'] === $adminId) {
                $selected = $admin;
                break;
            }
        }
        if ($selected === null) {
            throw new RuntimeException('The selected account is not an administrator.');
        }
    }

    $email = mb_strtolower(prompt('New administrator email: '));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The email address is not valid.');
    }

    $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $duplicate->execute([$email, $selected['id']]);
    if ($duplicate->fetch()) {
        throw new RuntimeException('That email address belongs to another user.');
    }

    $password = hiddenPrompt('New password');
    $confirmation = hiddenPrompt('Confirm new password');
    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException('The passwords do not match.');
    }
    if (!validPassword($password)) {
        throw new RuntimeException(
            'Use at least 12 characters with an uppercase letter, lowercase letter, and number.'
        );
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false || !password_verify($password, $hash)) {
        throw new RuntimeException('PHP could not create and verify the password hash.');
    }

    $pdo->beginTransaction();
    $update = $pdo->prepare(
        "UPDATE users SET email = ?, password = ?, status = 'active', updated_at = NOW() "
        . "WHERE id = ? AND role = 'admin'"
    );
    $update->execute([$email, $hash, $selected['id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The administrator was not updated.');
    }

    $verify = $pdo->prepare(
        "SELECT email, password, status FROM users WHERE id = ? AND role = 'admin' LIMIT 1"
    );
    $verify->execute([$selected['id']]);
    $saved = $verify->fetch();
    if (!$saved
        || $saved['email'] !== $email
        || $saved['status'] !== 'active'
        || !password_verify($password, $saved['password'])) {
        throw new RuntimeException('Post-update credential verification failed.');
    }
    $pdo->commit();

    fwrite(STDOUT, PHP_EOL . "Administrator credentials reset successfully." . PHP_EOL);
    fwrite(STDOUT, "Email: {$email}" . PHP_EOL);
    fwrite(STDOUT, "The account is active and the stored password hash was verified." . PHP_EOL);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
