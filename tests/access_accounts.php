<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Domain.php';require dirname(__DIR__).'/src/Users.php';
$n=0;function testAccess(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new RuntimeException($label);}
function deniedAccess(callable $fn):bool{try{$fn();return false;}catch(InputError){return true;}}
$dir=dirname(__DIR__,2).'/build/rgia-users-'.bin2hex(random_bytes(5));mkdir($dir,0700,true);$file=$dir.'/config.php';
$config=['environment'=>'validation','origin'=>'https://risques.example.org','users'=>['admin'=>['name'=>'l’administrateur','role'=>'decideur','password_hash'=>'fixture']],'ai'=>['api_key'=>'FIXTURE_NOT_A_REAL_TOKEN','enabled'=>true],'db'=>['name'=>'FIXTURE_NO_DATABASE']];file_put_contents($file,"<?php return ".var_export($config,true).';');
$input=['login'=>'responsable1','password'=>'Fictif-pour-test-2026!','password_confirm'=>'Fictif-pour-test-2026!'];$admin=['id'=>'admin'];
try{
 testAccess(deniedAccess(fn()=>createAccess($config,['id'=>'responsable1'],$input,$file)),'Un décideur ne peut pas créer des comptes');
 testAccess(deniedAccess(fn()=>createAccess($config,$admin,$input+['role'=>'admin'],$file))===false,'Le rôle arbitraire n’est pas utilisé');
 $saved=require $file;testAccess($saved['users']['responsable1']['role']==='decideur','Rôle fixé par la fonction');
 testAccess(password_verify($input['password'],$saved['users']['responsable1']['password_hash']),'Hash utilisable');
 testAccess(!str_contains(file_get_contents($file),$input['password']),'Aucun mot de passe en clair');
 testAccess($saved['ai']===$config['ai']&&$saved['db']===$config['db'],'IA et base préservées');
 testAccess(count($saved['access_audit'])===1&&$saved['access_audit'][0]['actor']==='admin','Audit sans secret');
 testAccess(deniedAccess(fn()=>createAccess($config,$admin,$input,$file)),'Pas de remplacement d’un compte');
 $short=['login'=>'responsable2','password'=>'court','password_confirm'=>'court'];testAccess(deniedAccess(fn()=>createAccess($config,$admin,$short,$file)),'Mot de passe court refusé');
 $bad=$input;$bad['login']='responsable2';$bad['password_confirm']='different';testAccess(deniedAccess(fn()=>createAccess($config,$admin,$bad,$file)),'Confirmation obligatoire');
 $bad=$input;$bad['login']='inconnu';testAccess(deniedAccess(fn()=>createAccess($config,$admin,$bad,$file)),'Compte hors périmètre refusé');
 $overview=accessOverview($saved,$admin);testAccess(!str_contains(encode($overview),'password_hash')&&!str_contains(encode($overview),'FIXTURE'),'Aucune fuite dans la vue des accès');
 testAccess($saved['users']['responsable1']['must_change_password']===true,'Compte créé temporaire');
 testAccess(!temporaryExpired($saved['users']['responsable1']),'Mot de passe temporaire valide');
 testAccess(temporaryExpired($saved['users']['responsable1'],time()+8*86400),'Mot de passe temporaire expiré après sept jours');
 foreach(['list','detail','event','csv','pptx','export','ai','risk_save','action_save','action_verify','assess','decide','users','user_create','draft_disposition'] as $op)testAccess(!passwordOperationAllowed($saved['users']['responsable1'],$op),'Accès refusé avant remplacement : '.$op);
 testAccess(passwordOperationAllowed($saved['users']['responsable1'],'password_change'),'Changement autorisé');
 testAccess(passwordOperationAllowed($saved['users']['responsable1'],'logout'),'Déconnexion autorisée');
 $member=['id'=>'responsable1','credential_version'=>1];$change=['current_password'=>$input['password'],'password'=>'Personnel-test-uniquement!','password_confirm'=>'Personnel-test-uniquement!'];
 $bad=$change;$bad['current_password']='incorrect';testAccess(deniedAccess(fn()=>changeOwnPassword($saved,$member,$bad,$file)),'Mot de passe actuel requis');
 $bad=$change;$bad['password_confirm']='autre';testAccess(deniedAccess(fn()=>changeOwnPassword($saved,$member,$bad,$file)),'Confirmation requise pour le changement');
 $bad=$change;$bad['password']=$bad['password_confirm']=$input['password'];testAccess(deniedAccess(fn()=>changeOwnPassword($saved,$member,$bad,$file)),'Réutilisation du temporaire refusée');
 $changed=changeOwnPassword($saved,$member,$change,$file);$personal=require $file;
 testAccess($personal['users']['responsable1']['must_change_password']===false,'Obligation levée après remplacement');
 testAccess($personal['users']['responsable1']['temporary_expires_at']===null,'Fin de validité temporaire retirée');
 testAccess(!password_verify($input['password'],$personal['users']['responsable1']['password_hash']),'Ancien temporaire inutilisable');
 testAccess(password_verify($change['password'],$personal['users']['responsable1']['password_hash']),'Mot de passe personnel utilisable');
 testAccess(!credentialCurrent($personal['users']['responsable1'],$member),'Autres sessions initiales invalidées');
 testAccess(credentialCurrent($personal['users']['responsable1'],['credential_version'=>$changed['credential_version']]),'Nouvelle session reconnue');
 testAccess(passwordOperationAllowed($personal['users']['responsable1'],'list'),'Accès au registre après remplacement');
 testAccess($personal['ai']===$config['ai']&&$personal['db']===$config['db'],'Secrets et base préservés après changement');
 testAccess(!str_contains(file_get_contents($file),$change['password']),'Aucun mot de passe personnel en clair');
 testAccess(deniedAccess(fn()=>changeOwnPassword($saved,$member,$change,$file)),'Ancienne session ne peut plus changer le compte');
 $bad=$input;$bad['password']=$bad['password_confirm']=str_repeat('x',73);testAccess(deniedAccess(fn()=>newPassword($bad)),'Borne bcrypt explicite');
 echo "OK $n contrôles des comptes sur configuration fictive, sans base ni API.\n";
}finally{foreach(glob($dir.'/*') as $p)unlink($p);rmdir($dir);}
