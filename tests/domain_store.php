<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Domain.php';require dirname(__DIR__).'/src/Store.php';require dirname(__DIR__).'/src/Ai.php';
// Test transactionnel local SQLite uniquement. Ne se connecte pas à MariaDB.
// Le dialecte est adapté pour cet essai ; il ne valide pas les verrous MariaDB.
final class TestPDO extends Pdo\Sqlite {
 public function __construct(){parent::__construct('sqlite::memory:');$this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$this->createFunction('UTC_TIMESTAMP',fn($precision=0)=>gmdate('Y-m-d H:i:s'));}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);}
}
$n=0;function check(bool $ok,string $msg):void{global $n;if(!$ok)throw new RuntimeException('ÉCHEC '.$msg);$n++;}
function rejects(callable $fn,string $msg):void{try{$fn();}catch(InputError){check(true,$msg);return;}throw new RuntimeException('ÉCHEC refus attendu '.$msg);}
$db=new TestPDO();$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE rgia_risks(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,version INTEGER DEFAULT 1,data TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
foreach(['actions','assessments','decisions','drafts','events']as $table){
 $extra=match($table){'actions'=>'data TEXT,created_by TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP','assessments','decisions'=>'data TEXT,actor TEXT','drafts'=>'actor TEXT,model TEXT,input_text TEXT,output_text TEXT,disposition TEXT DEFAULT "en_attente"','events'=>'actor TEXT,event_type TEXT,reason TEXT,before_json TEXT,after_json TEXT'};
 $db->exec('CREATE TABLE rgia_'.$table.'(id INTEGER PRIMARY KEY AUTOINCREMENT,risk_id INTEGER REFERENCES rgia_risks(id),'.$extra.',created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}
$s=new Store($db,'validateur-test');$items=json_decode(file_get_contents(__DIR__.'/fixtures/risks.json'),true,64,JSON_THROW_ON_ERROR);
foreach($items as $item){$r=$s->save(['id'=>0,'code'=>$item['code'],'data'=>$item['data'],'reason'=>'Initialisation du test isolé.']);check($r['data']['current']===null,'Aucune cotation actuelle inventée');}
check(count($s->all())===16,'16 scénarios');$r=$s->detail(1);check(count($r['events'])===1,'Création journalisée');$base=['id'=>1,'version'=>$r['version'],'reason'=>'Contrôle du scénario.'];
$bad=$r['data'];$bad['title']='';rejects(fn()=>$s->save($base+['data'=>$bad]),'Titre obligatoire');check($s->one(1)['version']===1,'Pas de mutation sur échec');
$d=$r['data'];$d['title']='Scénario relu';$d['p']=1;$r=$s->save($base+['data'=>$d]);check($r['version']===2,'Version incrémentée');check($r['data']['p']===3,'Cotation initiale préservée');check(count($r['events'])===2,'Historique conservé');
rejects(fn()=>$s->save($base+['data'=>$d]),'Conflit de version');check(count($s->detail(1)['events'])===2,'Conflit sans écriture');
$decision=['type'=>'accepter_sous_conditions','authority'=>'Autorité fictive','mandate'=>'Mandat fictif de test','conditions'=>'Maintien des contrôles','valid_until'=>'2099-12-01','evidence'=>'Référence fictive'];
rejects(fn()=>$s->decide(['id'=>1,'version'=>2,'reason'=>'Test','decision'=>$decision]),'Pas acceptation sans réévaluation');
$a=['title'=>'Former les utilisateurs à vérifier une négation','owner'=>'Responsable fictif','due'=>'2099-11-30','status'=>'planifiee','proof'=>''];$r=$s->action(['id'=>1,'version'=>2,'reason'=>'Plan de contrôle','action'=>$a]);$aid=$r['actions'][0]['id'];
rejects(fn()=>$s->verify(['id'=>1,'version'=>3,'reason'=>'Test','action_id'=>$aid,'verification'=>'Contrôle fictif']),'Pas vérification sans preuve');
$a['status']='preuve_soumise';$a['proof']='Exercice fictif, trois erreurs détectées';$r=$s->action(['id'=>1,'version'=>3,'reason'=>'Exercice réalisé','action_id'=>$aid,'action'=>$a]);
$r=$s->verify(['id'=>1,'version'=>4,'reason'=>'Contrôle de compétence','action_id'=>$aid,'verification'=>'Reprise humaine correcte lors de l’exercice']);check($r['actions'][0]['data']['status']==='verifiee','Mesure vérifiée');check($r['data']['current']===null,'Vérification ne recote pas automatiquement');
$ass=['p'=>2,'gp'=>4,'go'=>3,'evidence'=>'Exercice fictif et référence de contrôle','justification'=>'Hypothèse de test après contrôle','confidence'=>'faible'];$r=$s->assess(['id'=>1,'version'=>5,'reason'=>'Revue du test','assessment'=>$ass]);check($r['data']['current']['score']===8,'Gravité maximum et score');check($r['data']['target_score']===8,'Cible distincte conservée');
$r=$s->decide(['id'=>1,'version'=>6,'reason'=>'Décision fictive du test','decision'=>$decision]);check($r['data']['status']==='sous_surveillance','Acceptation conditionnelle');check(count($r['decisions'])===1,'Décision ajoutée');check(count($r['assessments'])===1,'Évaluation conservée');
$bad=$s->one(1)['data'];$bad['next_review']='2026-02-30';rejects(fn()=>$s->save(['id'=>1,'version'=>7,'reason'=>'Test','data'=>$bad]),'Date invalide');
$bad=$s->one(1)['data'];$bad['target']=4;rejects(fn()=>$s->save(['id'=>1,'version'=>7,'reason'=>'Test','data'=>$bad]),'Cible incohérente');
rejects(fn()=>requestAi(['ai'=>['enabled'=>false]],[],$s),'IA désactivée');check(csvSafe(' =HYPERLINK("x")')[0]==="'",'Neutralisation formule CSV');check(csvSafe('Description')==='Description','Texte CSV conservé');
$before=count($s->detail(1)['events']);try{$s->mutate(1,7,'Essai rollback',function($old,$why)use($s){$s->event(1,'test','Écriture à annuler',null,['test'=>true]);throw new InputError('Annulation volontaire');});}catch(InputError){}check(count($s->detail(1)['events'])===$before,'Transaction et journal annulés ensemble');
$event=$s->eventDetail(1,1);check($event['before_json']===null&&json_decode($event['after_json'],true)['score']===12,'Valeurs historiques conservées');
echo "OK $n contrôles PHP 8.5 et transactions SQLite adaptées. MariaDB et fournisseur IA non testés.\n";
