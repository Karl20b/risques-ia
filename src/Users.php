<?php
declare(strict_types=1);
function accessSlots():array {
    return ['responsable1'=>['name'=>'Responsable de validation','role'=>'decideur'],'responsable2'=>['name'=>'Second responsable','role'=>'decideur']];
}
function requireAccessAdmin(array $user):void {
    if(($user['id']??'')!=='admin')throw new InputError('La gestion des accès est réservée à l’administrateur.');
}
function accessOverview(array $config,array $user):array {
    requireAccessAdmin($user);$out=[];
    foreach(accessSlots() as $id=>$slot){$u=$config['users'][$id]??null;$out[]=['login'=>$id]+$slot+['created'=>$u!==null,'must_change_password'=>(bool)($u['must_change_password']??false),'temporary_expires_at'=>$u['temporary_expires_at']??null];}
    return ['members'=>$out];
}
function passwordValue(array $input,string $key):string {
    $v=$input[$key]??null;if(!is_string($v)||$v===''||strlen($v)>500||str_contains($v,"\0"))throw new InputError('Mot de passe invalide.');return $v;
}
function newPassword(array $input):string {
    $p=passwordValue($input,'password');$confirm=passwordValue($input,'password_confirm');
    if(mb_strlen($p)<14||strlen($p)>72)throw new InputError('Choisir au moins 14 caractères, sans dépasser 72 octets (les accents occupent plusieurs octets).');
    if(!hash_equals($p,$confirm))throw new InputError('Les deux mots de passe ne correspondent pas.');return $p;
}
function credentialCurrent(array $user,array $session):bool {
    return (int)($session['credential_version']??0)===(int)($user['credential_version']??0);
}
function temporaryExpired(array $user,?int $now=null):bool {
    if(!($user['must_change_password']??false))return false;
    $expires=$user['temporary_expires_at']??null;if($expires===null)return false;
    $time=is_string($expires)?strtotime($expires):false;return $time===false||$time<=($now??time());
}
function passwordOperationAllowed(array $user,string $op):bool {
    return !($user['must_change_password']??false)||in_array($op,['session','logout','password_change'],true);
}
function writeAccessConfig(array $config,callable $change,?string $testPath=null):array {
    $path=$testPath??dirname(__DIR__).'/config/config.local.php';
    $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Gestion des accès indisponible.');chmod($path.'.lock',0600);
    try {
        clearstatcache(true,$path);if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
        $current=require $path;
        if(!is_array($current)||($current['origin']??'')!==$config['origin']||($current['environment']??'')!=='validation')throw new RuntimeException('Configuration inattendue.');
        $result=$change($current);
        $backup=$path.'.before-access-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.bak';if(!copy($path,$backup))throw new RuntimeException('Sauvegarde des accès impossible.');chmod($backup,0600);
        $text="<?php\nreturn ".var_export($current,true).";\n";$tmp=tempnam(dirname($path),'.users-');if($tmp===false)throw new RuntimeException('Écriture impossible.');chmod($tmp,0600);
        try{if(file_put_contents($tmp,$text)!==strlen($text)||!rename($tmp,$path))throw new RuntimeException('Enregistrement des accès impossible.');}finally{if(is_file($tmp))unlink($tmp);}
        if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function createAccess(array $config,array $user,array $input,?string $testPath=null):array {
    requireAccessAdmin($user);$id=choice($input,'login',array_keys(accessSlots()));$password=newPassword($input);
    return writeAccessConfig($config,function(&$current)use($id,$password,$user){
        if(isset($current['users'][$id]))throw new InputError('Ce compte existe déjà. Aucun mot de passe remplacé.');
        $slot=accessSlots()[$id];$slot['password_hash']=password_hash($password,PASSWORD_DEFAULT);$slot['must_change_password']=true;$slot['credential_version']=1;$slot['temporary_expires_at']=gmdate('c',time()+7*86400);
        $current['users'][$id]=$slot;$current['access_audit'][]=['at'=>gmdate('c'),'actor'=>$user['id'],'login'=>$id,'role'=>'decideur','event'=>'creation_temporaire','must_change_password'=>true];
        return ['ok'=>true,'login'=>$id,'name'=>$slot['name'],'role'=>$slot['role'],'must_change_password'=>true];
    },$testPath);
}
function changeOwnPassword(array $config,array $user,array $input,?string $testPath=null):array {
    $id=$user['id']??'';if($id==='')throw new InputError('Connexion nécessaire.');$old=passwordValue($input,'current_password');$password=newPassword($input);
    return writeAccessConfig($config,function(&$current)use($id,$old,$password,$user){
        $account=$current['users'][$id]??null;
        if(!$account||!credentialCurrent($account,$user)||temporaryExpired($account)||!password_verify($old,$account['password_hash']))throw new InputError('Mot de passe actuel incorrect ou session expirée.');
        if(password_verify($password,$account['password_hash']))throw new InputError('Le nouveau mot de passe doit être différent du mot de passe temporaire ou actuel.');
        $account['password_hash']=password_hash($password,PASSWORD_DEFAULT);$account['must_change_password']=false;$account['temporary_expires_at']=null;$account['credential_version']=(int)($account['credential_version']??0)+1;$current['users'][$id]=$account;
        $current['access_audit'][]=['at'=>gmdate('c'),'actor'=>$id,'login'=>$id,'event'=>'changement_mot_de_passe'];
        return ['ok'=>true,'credential_version'=>$account['credential_version']];
    },$testPath);
}
