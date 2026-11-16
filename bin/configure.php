<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/prompts.php';
try {
 $target=dirname(__DIR__).'/config/config.local.php';if(file_exists($target))throw new RuntimeException('Configuration existante : aucun remplacement.');
 $origin=ask('Origine HTTPS, sans chemin ni slash final');$url=parse_url($origin);
 if(!$url||($url['scheme']??'')!=='https'||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!empty($url['path']))throw new RuntimeException('Origine HTTPS invalide.');
 $host=ask('Serveur MariaDB');$name=ask('Nom de la base dédiée');$dbuser=ask('Utilisateur MariaDB dédié');
 if(!preg_match('/^[a-zA-Z0-9._-]+$/D',$host)||!preg_match('/^[a-zA-Z0-9_]+$/D',$name)||$dbuser==='')throw new RuntimeException('Paramètres de base invalides.');
 $dbpassword=secret('Mot de passe MariaDB');$password=secret('Mot de passe du compte admin (14 caractères minimum)');$confirmation=secret('Confirmer le mot de passe admin');
 if(mb_strlen($password)<14||strlen($password)>72||!hash_equals($password,$confirmation))throw new RuntimeException('Mot de passe trop court, trop long ou confirmation différente.');
 $config=require dirname(__DIR__).'/config/config.example.php';$config['origin']=$origin;$config['db']=['host'=>$host,'port'=>3306,'name'=>$name,'user'=>$dbuser,'password'=>$dbpassword];$config['users']=['admin'=>['name'=>'Administrateur','role'=>'decideur','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]];
 $db=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$dbuser,$dbpassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 foreach(['risks','actions','events','assessments','decisions','drafts'] as $table)$db->query('SELECT id FROM rgia_'.$table.' LIMIT 0');
 umask(0077);$h=fopen($target,'x');if(!$h)throw new RuntimeException('Création impossible.');$raw="<?php\nreturn ".var_export($config,true).";\n";$ok=fwrite($h,$raw);fclose($h);if($ok!==strlen($raw)){unlink($target);throw new RuntimeException('Écriture incomplète.');}
 echo "Configuration créée. Identifiant : admin. Registre vide ; IA désactivée.\n";
} catch(Throwable $e){fwrite(STDERR,$e instanceof PDOException?"Base ou tables indisponibles. Vérifier la configuration et les droits.\n":$e->getMessage()."\n");exit(1);}
