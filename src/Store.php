<?php
declare(strict_types=1);
final class Store {
    public function __construct(public readonly PDO $db,public readonly string $actor){}
    public function all():array {return array_map(fn($r)=>$this->unpack($r),$this->db->query('SELECT id,code,version,data FROM rgia_risks ORDER BY code')->fetchAll());}
    private function unpack(array $r):array {return ['id'=>(int)$r['id'],'code'=>$r['code'],'version'=>(int)$r['version'],'data'=>json_decode($r['data'],true,64,JSON_THROW_ON_ERROR)];}
    public function one(int $id,bool $lock=false):array {
        $q=$this->db->prepare('SELECT id,code,version,data FROM rgia_risks WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$r=$q->fetch();if(!$r)throw new InputError('Risque introuvable.');return $this->unpack($r);
    }
    public function detail(int $id):array {
        $r=$this->one($id);
        foreach(['actions','assessments','decisions','drafts','events']as $type){
            $q=$this->db->prepare('SELECT * FROM rgia_'.$type.' WHERE risk_id=? ORDER BY id DESC');$q->execute([$id]);$r[$type]=$q->fetchAll();
            foreach($r[$type] as &$row){$row['id']=(int)$row['id'];if(isset($row['data']))$row['data']=json_decode($row['data'],true,64,JSON_THROW_ON_ERROR);if($type==='events'){unset($row['before_json'],$row['after_json']);}}
            unset($row);
        } return $r;
    }
    public function event(int $id,string $type,string $reason,?array $before,array $after):void {
        $this->db->prepare('INSERT INTO rgia_events(risk_id,actor,event_type,reason,before_json,after_json) VALUES(?,?,?,?,?,?)')->execute([$id,$this->actor,$type,$reason,$before===null?null:encode($before),encode($after)]);
    }
    private function update(int $id,array $data):void {$this->db->prepare('UPDATE rgia_risks SET data=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?')->execute([encode($data),$id]);}
    public function mutate(int $id,int $version,string $reason,callable $fn):array {
        if($reason==='')throw new InputError('Un motif est requis pour le journal.');
        $this->db->beginTransaction();try{
            $old=$this->one($id,true);if($old['version']!==$version)throw new InputError('La fiche a changé. Rechargez-la avant de poursuivre.');
            $fn($old,$reason);$this->db->commit();return $this->detail($id);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function save(array $in):array {
        $reason=text($in,'reason',1500,true);$id=$in['id']??0;
        if($id===0){$d=riskData($in['data']??[]);$code=text($in,'code',24,true);if(!preg_match('/^[A-Z][A-Z0-9_-]{1,23}$/D',$code))throw new InputError('Identifiant : 2 à 24 lettres, chiffres, tirets ou soulignés.');
            $this->db->beginTransaction();try{$this->db->prepare('INSERT INTO rgia_risks(code,data) VALUES(?,?)')->execute([$code,encode($d)]);$id=(int)$this->db->lastInsertId();$this->event($id,'creation',$reason,null,$d);$this->db->commit();return $this->detail($id);}catch(Throwable $e){$this->db->rollBack();throw $e;}}
        $id=integer($in,'id',1,PHP_INT_MAX);$v=integer($in,'version',1,PHP_INT_MAX);
        return $this->mutate($id,$v,$reason,function($old,$why)use($in,$id){$d=riskData($in['data']??[],$old['data']);$this->update($id,$d);$this->event($id,'modification',$why,$old['data'],$d);});
    }
    public function action(array $in):array {
        $id=integer($in,'id',1,PHP_INT_MAX);return $this->mutate($id,integer($in,'version',1,PHP_INT_MAX),text($in,'reason',1500,true),function($old,$why)use($in,$id){
            $a=$in['action']??[];$d=['title'=>text($a,'title',500,true),'owner'=>text($a,'owner',180,true),'due'=>dateField($a,'due',true),'status'=>choice($a,'status',['planifiee','en_cours','preuve_soumise']),'proof'=>text($a,'proof',2500)];
            if($d['status']==='preuve_soumise'&&$d['proof']==='')throw new InputError('La référence de preuve est requise.');
            $aid=$in['action_id']??0;$before=null;
            if($aid!==0){$aid=integer($in,'action_id',1,PHP_INT_MAX);$q=$this->db->prepare('SELECT data FROM rgia_actions WHERE id=? AND risk_id=? FOR UPDATE');$q->execute([$aid,$id]);$row=$q->fetch();if(!$row)throw new InputError('Mesure introuvable.');$before=json_decode($row['data'],true,64,JSON_THROW_ON_ERROR);if(($before['status']??'')==='verifiee')throw new InputError('Mesure vérifiée figée. Créez une mesure de suivi.');$this->db->prepare('UPDATE rgia_actions SET data=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND risk_id=?')->execute([encode($d),$aid,$id]);}
            else{$this->db->prepare('INSERT INTO rgia_actions(risk_id,data,created_by) VALUES(?,?,?)')->execute([$id,encode($d),$this->actor]);$aid=(int)$this->db->lastInsertId();}
            $this->update($id,$old['data']);$this->event($id,'mesure',$why,$before,['action_id'=>$aid]+$d);
        });
    }
    public function verify(array $in):array {
        $id=integer($in,'id',1,PHP_INT_MAX);return $this->mutate($id,integer($in,'version',1,PHP_INT_MAX),text($in,'reason',1500,true),function($old,$why)use($in,$id){
            $aid=integer($in,'action_id',1,PHP_INT_MAX);$q=$this->db->prepare('SELECT data FROM rgia_actions WHERE id=? AND risk_id=? FOR UPDATE');$q->execute([$aid,$id]);$r=$q->fetch();if(!$r)throw new InputError('Mesure introuvable.');$before=json_decode($r['data'],true,64,JSON_THROW_ON_ERROR);
            if(($before['status']??'')!=='preuve_soumise')throw new InputError('Une preuve doit être soumise avant la vérification.');
            $d=$before;$d['status']='verifiee';$d['verified_by']=$this->actor;$d['verified_at']=gmdate('c');$d['verification']=text($in,'verification',2500,true);
            $this->db->prepare('UPDATE rgia_actions SET data=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?')->execute([encode($d),$aid]);$this->update($id,$old['data']);$this->event($id,'verification_mesure',$why,$before,['action_id'=>$aid]+$d);
        });
    }
    public function assess(array $in):array {
        $id=integer($in,'id',1,PHP_INT_MAX);return $this->mutate($id,integer($in,'version',1,PHP_INT_MAX),text($in,'reason',1500,true),function($old,$why)use($in,$id){
            $a=$in['assessment']??[];$d=['p'=>integer($a,'p',1,4),'gp'=>integer($a,'gp',1,4),'go'=>integer($a,'go',1,4),'evidence'=>text($a,'evidence',4000,true),'justification'=>text($a,'justification',4000,true),'confidence'=>choice($a,'confidence',['faible','moyenne','elevee'])];
            $d['g']=max($d['gp'],$d['go']);$d['score']=$d['p']*$d['g'];$d['assessed_at']=gmdate('c');$d['assessed_by']=$this->actor;
            $this->db->prepare('INSERT INTO rgia_assessments(risk_id,data,actor) VALUES(?,?,?)')->execute([$id,encode($d),$this->actor]);$r=$old['data'];$r['current']=$d;$this->update($id,$r);$this->event($id,'reevaluation',$why,$old['data']['current'],$d);
        });
    }
    public function decide(array $in):array {
        $id=integer($in,'id',1,PHP_INT_MAX);return $this->mutate($id,integer($in,'version',1,PHP_INT_MAX),text($in,'reason',1500,true),function($old,$why)use($in,$id){
            $a=$in['decision']??[];$d=['type'=>choice($a,'type',['instruire','traiter','differer','suspendre','accepter_sous_conditions','clore']),'authority'=>text($a,'authority',250,true),'mandate'=>text($a,'mandate',1000,true),'conditions'=>text($a,'conditions',4000,true),'valid_until'=>dateField($a,'valid_until',true),'evidence'=>text($a,'evidence',4000,true)];
            if($d['valid_until']<gmdate('Y-m-d'))throw new InputError('La revue de décision ne peut être dans le passé.');
            if(in_array($d['type'],['accepter_sous_conditions','clore'],true)&&$old['data']['current']===null)throw new InputError('Une réévaluation sur preuves est requise avant acceptation ou clôture.');
            $d['assessment_at_decision']=$old['data']['current'];
            $this->db->prepare('INSERT INTO rgia_decisions(risk_id,data,actor) VALUES(?,?,?)')->execute([$id,encode($d),$this->actor]);
            $r=$old['data'];$r['status']=match($d['type']){'clore'=>'archive','traiter'=>'en_traitement','accepter_sous_conditions'=>'sous_surveillance',default=>'a_instruire'};$r['next_review']=$d['valid_until'];$this->update($id,$r);$this->event($id,'decision',$why,null,$d);
        });
    }
    public function eventDetail(int $id,int $eventId):array {$q=$this->db->prepare('SELECT id,actor,event_type,reason,before_json,after_json,created_at FROM rgia_events WHERE id=? AND risk_id=?');$q->execute([$eventId,$id]);$r=$q->fetch();if(!$r)throw new InputError('Événement introuvable.');return $r;}
}
