<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentUser(): ?array { static $user=false; if(!isLoggedIn())return null; if($user!==false)return $user; $s=db()->prepare('SELECT id,name,email,role,status,created_at FROM users WHERE id=? LIMIT 1'); $s->execute([$_SESSION['user_id']]); $user=$s->fetch() ?: null; if(!$user || $user['status']!=='active'){ logoutUser(); return null; } return $user; }
function requireLogin(): void { if(!currentUser()){ flash('warning','يرجى تسجيل الدخول أولًا.'); redirect(url('login.php')); } }
function requireRole(array|string $roles): void { requireLogin(); if(!in_array(currentUser()['role'],(array)$roles,true)){ http_response_code(403); exit('غير مصرح لك بالوصول إلى هذه الصفحة.'); } }
function isAdmin(): bool { return (currentUser()['role']??'')==='admin'; }
function isEditor(): bool { return (currentUser()['role']??'')==='editor'; }
function canManageNews(): bool { return in_array(currentUser()['role']??'', ['admin','editor','author'],true); }
function canEditArticle(array $article): bool { $u=currentUser(); return $u && (in_array($u['role'],['admin','editor'],true) || ((int)$article['user_id']===(int)$u['id'] && in_array($article['status'],['draft','pending'],true))); }
function loginUser(int $id): void { session_regenerate_id(true); $_SESSION['user_id']=$id; $_SESSION['last_regeneration']=time(); }
function logoutUser(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); }
