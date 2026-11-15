<?php
require_once __DIR__.'/includes/auth.php';
if (!isPost()) {
    header('Allow: POST');
    http_response_code(405);
    exit('طريقة الطلب غير مسموحة.');
}
verifyCsrf();
logoutUser();
redirect(url('login.php'));
