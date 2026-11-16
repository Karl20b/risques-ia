<?php
declare(strict_types=1);
final class InputError extends RuntimeException {}
function encode(array $a): string { return json_encode($a, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function text(array $in, string $key, int $max=5000, bool $required=false): string {
    $v = $in[$key] ?? ''; if (!is_string($v)) throw new InputError("Champ invalide : $key");
    $v=trim($v); if (!preg_match('//u', $v) || strlen($v)>$max*4 || mb_strlen($v,'UTF-8')>$max) throw new InputError("Texte trop long ou invalide : $key");
    if ($required && $v==='') throw new InputError("Champ requis : $key"); return $v;
}
function integer(array $in,string $key,int $min,int $max): int {
    $v=$in[$key]??null; if (!is_int($v) || $v<$min || $v>$max) throw new InputError("Valeur invalide : $key"); return $v;
}
function choice(array $in,string $key,array $values): string { $v=text($in,$key,80,true); if(!in_array($v,$values,true))throw new InputError("Choix invalide : $key");return $v; }
function dateField(array $in,string $key,bool $required=false): string {
    $s=text($in,$key,10,$required); if($s==='')return '';
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);
    if(!$d || $d->format('Y-m-d')!==$s)throw new InputError("Date invalide : $key");return $s;
}
function riskData(array $in,?array $old=null): array {
    $out=[];
    foreach(['title'=>180,'scenario'=>5000,'impact'=>4000,'basis'=>5000,'controls'=>4000,'measures'=>5000,'proof'=>4000,'owner'=>180,'mapping'=>2000,'when'=>500,'scope'=>1000] as $k=>$max) {
        $out[$k]=text($in,$k,$max,in_array($k,['title','scenario','owner'],true));
    }
    $out['perimeter']=choice($in,'perimeter',['Strategique','POC','Transversal']);
    $out['status']=choice($in,'status',['a_instruire','en_traitement','sous_surveillance','archive']);
    $out['next_review']=dateField($in,'next_review',true);
    $out['target']=integer($in,'target',1,4);
    $out['p']=$old['p']??integer($in,'p',1,4);
    $out['gp']=$old['gp']??integer($in,'gp',1,4);
    $out['go']=$old['go']??integer($in,'go',1,4);
    $out['g']=max($out['gp'],$out['go']);$out['score']=$out['p']*$out['g'];$out['target_score']=$out['target']*$out['g'];
    if($out['target']>$out['p'])throw new InputError('La cible conditionnelle ne peut dépasser la vraisemblance initiale dans cette version.');
    $out['current']=$old['current']??null;
    return $out;
}
function csvSafe(string $v):string { return preg_match('/^[\s]*[=+@\-]/u',$v)?"'".$v:$v; }
