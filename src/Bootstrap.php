<?php
declare(strict_types=1);
require_once __DIR__.'/Domain.php';
require_once __DIR__.'/Store.php';
require_once __DIR__.'/Ai.php';
require_once __DIR__.'/Users.php';
require_once __DIR__.'/Exports.php';
require_once __DIR__.'/Help.php';
require_once __DIR__.'/Rag.php';
function config():array {
    $file=dirname(__DIR__).'/config/config.local.php';
    if(!is_file($file))throw new RuntimeException('Configuration manquante.');
    $c=require $file;if(!is_array($c)||($c['environment']??'')!=='validation')throw new RuntimeException('Espace de validation requis.');
    if(!filter_var($c['origin']??'',FILTER_VALIDATE_URL)||parse_url($c['origin'],PHP_URL_SCHEME)!=='https')throw new RuntimeException('Origine HTTPS requise.');
    $dir=$c['private_dir']??'';if(!is_string($dir)||$dir===''||str_contains($dir,'://'))throw new RuntimeException('Répertoire privé requis.');
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Stockage indisponible.');
    $real=realpath($dir);$public=realpath(dirname(__DIR__).'/public');if(!$real||!$public||$real===$public||str_starts_with($real,$public.DIRECTORY_SEPARATOR))throw new RuntimeException('Stockage hors du dossier public requis.');
    return $c;
}
function connectDb(array $c):PDO {
    $d=$c['db'];$name=(string)$d['name'];$host=(string)$d['host'];
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name)||!preg_match('/^[a-zA-Z0-9.:-]+$/D',$host))throw new RuntimeException('Base invalide.');
    $pdo=new PDO('mysql:host='.$host.';port='.(int)$d['port'].';dbname='.$name.';charset=utf8mb4',$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = '+00:00'");return $pdo;
}
function securityHeaders():void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
}
function result(array $data,int $status=200):never {http_response_code($status);echo encode($data);exit;}
function startSession(array $c):void {
    if(($_SERVER['HTTPS']??'')!=='on')throw new RuntimeException('HTTPS requis.');
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('risques_ia_validation');session_set_cookie_params(['lifetime'=>0,'path'=>$c['base_path']?:'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);}
    $_SESSION['last_seen']=time();$_SESSION['csrf']??=bin2hex(random_bytes(32));
}
function requireUser(array $c):array {
    $id=$_SESSION['user_id']??'';$u=$c['users'][$id]??null;
    if(!$u||!credentialCurrent($u,$_SESSION)||temporaryExpired($u)||!in_array($u['role']??'',['lecteur','redacteur','decideur'],true))result(['error'=>'Connexion nécessaire.'],401);
    return ['id'=>$id,'name'=>$u['name'],'role'=>$u['role'],'must_change_password'=>(bool)($u['must_change_password']??false),'credential_version'=>(int)($u['credential_version']??0)];
}
function requireRole(array $u,string $needed):void {
    $rank=['lecteur'=>0,'redacteur'=>1,'decideur'=>2];if(($rank[$u['role']]??-1)<$rank[$needed])result(['error'=>'Droit insuffisant.'],403);
}
function rateLimit(array $c,string $key,int $max,int $seconds):void {
    $file=$c['private_dir'].'/limit-'.hash('sha256',$key).'.json';$f=fopen($file,'c+');if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Contrôle de fréquence indisponible.');
    try{$raw=stream_get_contents($f);$d=json_decode($raw?:'{}',true)?:[];if(($d['until']??0)<time())$d=['until'=>time()+$seconds,'count'=>0];
        if($d['count']>=$max)result(['error'=>'Trop de demandes. Réessayez plus tard.'],429);
        $d['count']++;rewind($f);ftruncate($f,0);fwrite($f,encode($d));fflush($f);
    }finally{flock($f,LOCK_UN);fclose($f);}
}
