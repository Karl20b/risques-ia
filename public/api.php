<?php
declare(strict_types=1);
// Garder les diagnostics dans les logs, jamais dans les réponses JSON.
ini_set('display_errors','0');
ini_set('log_errors','1');
require_once dirname(__DIR__).'/src/Bootstrap.php';
securityHeaders();
try {
    $c=config();startSession($c);$method=$_SERVER['REQUEST_METHOD']??'GET';$op=$_GET['op']??'session';
    if(!is_string($op))throw new InputError('Action invalide.');
    $in=[];
    if($method==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>65536)result(['error'=>'Demande trop volumineuse.'],413);
        if(($_SERVER['HTTP_ORIGIN']??'')!==$c['origin'])result(['error'=>'Origine refusée.'],403);
        if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))result(['error'=>'JSON requis.'],415);
        $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536)result(['error'=>'Demande trop volumineuse.'],413);
        $obj=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$obj instanceof stdClass)throw new InputError('Objet JSON requis.');$in=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))result(['error'=>'Session de formulaire expirée. Rechargez la page.'],403);
    }elseif($method!=='GET'){result(['error'=>'Méthode refusée.'],405);}
    $postOps=['login','logout','risk_save','action_save','action_verify','assess','decide','ai','draft_disposition','user_create','password_change','rag_preview','rag_generate','rag_review','rag_formulation'];
    if(in_array($op,$postOps,true)&&$method!=='POST')result(['error'=>'POST requis.'],405);
    if(!in_array($op,$postOps,true)&&$method!=='GET')result(['error'=>'GET requis.'],405);
    if($op==='session'){
        $uid=$_SESSION['user_id']??'';$u=$c['users'][$uid]??null;if($u&&(!credentialCurrent($u,$_SESSION)||temporaryExpired($u)))$u=null;
        result(['csrf'=>$_SESSION['csrf'],'user'=>$u?['id'=>$uid,'name'=>$u['name'],'role'=>$u['role'],'must_change_password'=>(bool)($u['must_change_password']??false)]:null,'ai_enabled'=>(bool)($c['ai']['enabled']??false)]);
    }
    if($op==='login'){
        $login=text($in,'login',80,true);rateLimit($c,'login-ip:'.($_SERVER['REMOTE_ADDR']??''),20,900);rateLimit($c,'login-user:'.strtolower($login),10,900);
        $u=$c['users'][$login]??null;
        // Toujours exécuter une vérification coûteuse, y compris pour un compte inconnu.
        $hash=$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=password_verify(passwordValue($in,'password'),$hash);
        if(!$u||!$valid||temporaryExpired($u))result(['error'=>'Identifiants incorrects.'],401);
        session_regenerate_id(true);$_SESSION['user_id']=$login;$_SESSION['credential_version']=(int)($u['credential_version']??0);$_SESSION['csrf']=bin2hex(random_bytes(32));result(['ok'=>true]);
    }
    $u=requireUser($c);
    if(!passwordOperationAllowed($u,$op))result(['error'=>'Le mot de passe temporaire doit être remplacé avant d’accéder au registre.'],403);
    if($op==='password_change'){
        rateLimit($c,'password-change:'.$u['id'],6,3600);$changed=changeOwnPassword($c,$u,$in);session_regenerate_id(true);$_SESSION['credential_version']=$changed['credential_version'];$_SESSION['csrf']=bin2hex(random_bytes(32));result(['ok'=>true]);
    }
    if($op==='logout'){$_SESSION=[];session_regenerate_id(true);result(['ok'=>true]);}
    if(in_array($op,['users','user_create'],true)&&$u['id']!=='admin')result(['error'=>'Gestion des accès réservée à l’administrateur.'],403);
    if($op==='users')result(accessOverview($c,$u));
    if($op==='user_create'){requireAccessAdmin($u);rateLimit($c,'create-user:'.$u['id'],6,3600);result(createAccess($c,$u,$in));}
    if($op==='help')result(helpContent());
    if($op==='help_media')sendHelpMedia(choice($_GET,'asset',['capsule01','poster01','captions01','capsule02','poster02','captions02','capsule03','poster03','captions03','capsule04','poster04','captions04','capsule05','poster05','captions05']));
    if($op==='rag')result(ragOverview($c));
    if($op==='rag_draft')result(ragPresentDraft(ragReadDraft($c,text($_GET,'draft',32,true))));
    if($op==='rag_formulation'){requireRole($u,'redacteur');ragFormulationInput($in);rateLimit($c,'ai-user:'.$u['id'],10,3600);result(ragFormulation($c,$in));}
    if($op==='rag_preview'){requireRole($u,'redacteur');rateLimit($c,'rag-preview:'.$u['id'],60,3600);result(ragPreview($in));}
    if($op==='rag_generate'){requireRole($u,'redacteur');rateLimit($c,'ai-user:'.$u['id'],10,3600);result(ragPresentDraft(ragGenerate($c,$u,$in)));}
    if($op==='rag_review'){requireRole($u,'decideur');result(ragPresentDraft(ragReviewDraft($c,$u,$in)));}
    $s=new Store(connectDb($c),$u['id']);
    if($op==='csv'){sendCsv($s,choice($_GET,'table',['risques','mesures','matrices','decisions','journal']));}
    if(in_array($op,['pptx_em','pptx_plan'],true)){
        $raw=$_GET['ids']??'';if(!is_string($raw)||strlen($raw)>400||!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*){0,39}$/D',$raw))throw new InputError('Sélection de risques invalide.');
        $ids=array_values(array_unique(array_map('intval',explode(',',$raw))));
        $mode=choice($_GET,'mode',['em','focus']);$annexes=choice($_GET,'annexes',['0','1']);
        if($mode==='focus'&&count($ids)!==1)throw new InputError('Choisissez un seul risque pour le zoom.');
        if($op==='pptx_plan'){$risks=array_map(fn($id)=>$s->detail($id),$ids);$slides=emPlan($risks,$annexes==='1',$mode==='focus');result(['slides'=>array_map(fn($slide)=>['title'=>$slide['title'],'annexe'=>$slide['kind']==='annex'],$slides),'risk_count'=>count($risks),'sans_appel_ia'=>true]);}
        sendEmPptx($s,$ids,$annexes==='1',$mode==='focus',$c);
    }
    if($op==='pptx'){
        $riskId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $draftId=isset($_GET['draft_id'])?filter_var($_GET['draft_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):0;
        if($riskId===false||$riskId===null||$draftId===false)throw new InputError('Identifiant de présentation invalide.');
        sendPptx($s,$riskId,$draftId,$c);
    }
    if($op==='list')result(['risks'=>$s->all()]);
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
    if($op==='detail'){if(!$id)throw new InputError('Identifiant requis.');result($s->detail($id));}
    if($op==='event'){$eid=filter_var($_GET['event_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;if(!$id||!$eid)throw new InputError('Événement requis.');result($s->eventDetail($id,$eid));}
    if($op==='export'){
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="registre-risques-'.gmdate('Y-m-d').'.csv"');
        $f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['Identifiant','Titre','Responsable','Statut','P initial','G initial','Score initial','Cible conditionnelle','Score actuel vérifié','Prochaine revue'],';', '"','');
        foreach($s->all() as $r){$d=$r['data'];$row=[$r['code'],$d['title'],$d['owner'],$d['status'],$d['p'],$d['g'],$d['score'],$d['target_score'],$d['current']['score']??'Non évalué',$d['next_review']];fputcsv($f,array_map(fn($v)=>csvSafe((string)$v),$row),';','"','');}fclose($f);exit;
    }
    requireRole($u,'redacteur');
    if($op==='risk_save'){if(($in['id']??0)!==0){$old=$s->one(integer($in,'id',1,PHP_INT_MAX));$in['data']['status']=$old['data']['status'];}else{$in['data']['status']='a_instruire';}result($s->save($in));}
    if($op==='action_save')result($s->action($in));
    if($op==='action_verify'){requireRole($u,'decideur');result($s->verify($in));}
    if($op==='assess'){requireRole($u,'decideur');result($s->assess($in));}
    if($op==='decide'){requireRole($u,'decideur');result($s->decide($in));}
    if($op==='ai'){rateLimit($c,'ai-user:'.$u['id'],10,3600);result(requestAi($c,$in,$s));}
    if($op==='draft_disposition'){
        $id=integer($in,'id',1,PHP_INT_MAX);result($s->mutate($id,integer($in,'version',1,PHP_INT_MAX),text($in,'reason',1500,true),function($old,$why)use($s,$in,$id){
            $draft=integer($in,'draft_id',1,PHP_INT_MAX);$state=choice($in,'disposition',['utilisee_apres_relecture','ecartee']);
            $q=$s->db->prepare('SELECT disposition FROM rgia_drafts WHERE id=? AND risk_id=? FOR UPDATE');$q->execute([$draft,$id]);$before=$q->fetch();if(!$before)throw new InputError('Proposition introuvable.');
            $s->db->prepare('UPDATE rgia_drafts SET disposition=? WHERE id=? AND risk_id=?')->execute([$state,$draft,$id]);$s->db->prepare('UPDATE rgia_risks SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?')->execute([$id]);$s->event($id,'revue_proposition_ia',$why,$before,['draft_id'=>$draft,'disposition'=>$state]);
        }));
    }
    result(['error'=>'Action inconnue.'],404);
}catch(InputError|JsonException $e){result(['error'=>$e instanceof JsonException?'JSON invalide.':$e->getMessage()],422);}
catch(Throwable $e){error_log('RGIA '.get_class($e).' code '.(string)$e->getCode());result(['error'=>'Service indisponible. Vérifiez la configuration ou sollicitez le responsable. Aucune modification partielle n’est conservée.'],503);}
