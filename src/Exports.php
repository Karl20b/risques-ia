<?php
declare(strict_types=1);
function exportStatus(string $value):string {
    return ['a_instruire'=>'À instruire','en_traitement'=>'En traitement','sous_surveillance'=>'Sous surveillance','archive'=>'Archivé','planifiee'=>'Planifiée','en_cours'=>'En cours','preuve_soumise'=>'Preuve soumise','verifiee'=>'Vérifiée','en_attente'=>'À relire','ecartee'=>'Écartée','utilisee_apres_relecture'=>'Reprise après relecture'][$value]??$value;
}
function csvTable(Store $store,string $table):array {
    $risks=$store->all();$rows=[];
    if($table==='risques'){
        $headers=['ID','Risque','Périmètre','Responsable proposé','Statut','Scénario','Conséquences','Fondement et limites','Contrôles connus','Mesures proposées','Preuves attendues','P initiale proposée','G personnes initiale','G mission initiale','Score initial proposé','Score cible conditionnel','Score actuel sur preuves','Prochaine revue','Sources'];
        foreach($risks as $r){$d=$r['data'];$rows[]=[$r['code'],$d['title'],$d['perimeter'],$d['owner'],exportStatus($d['status']),$d['scenario'],$d['impact'],$d['basis'],$d['controls'],$d['measures'],$d['proof'],$d['p'],$d['gp'],$d['go'],$d['score'],$d['target_score'],$d['current']['score']??'Non évalué',$d['next_review'],$d['mapping']];}
    }elseif($table==='matrices'){
        $headers=['Cotation','Gravité','Vraisemblance','Score','Risques dans la case','Sens de la vue'];
        foreach(['initiale','cible','actuelle'] as $view){$cells=[];$unknown=[];foreach($risks as $r){$d=$r['data'];if($view==='actuelle'&&$d['current']===null){$unknown[]=$r['code'];continue;}$g=$view==='actuelle'?$d['current']['g']:$d['g'];$p=$view==='actuelle'?$d['current']['p']:($view==='cible'?$d['target']:$d['p']);$cells[$g.'-'.$p][]=$r['code'];}for($g=4;$g>=1;$g--)for($p=1;$p<=4;$p++)$rows[]=[$view,$g,$p,$g*$p,implode(', ',$cells[$g.'-'.$p]??[]),$view==='cible'?'Objectif conditionnel, efficacité à démontrer':($view==='initiale'?'Proposition de travail':'Réévaluation sur preuves uniquement')];if($unknown)$rows[]=['actuelle','','','','Non évalués : '.implode(', ',$unknown),'Une case vide ne prouve pas une absence de risque'];}
    }else{
        $headers=match($table){'mesures'=>['Risque','ID mesure','Action et résultat attendu','Responsable proposé','Échéance proposée','État','Preuves attendues ou références soumises','Résultat de vérification','Vérifié par'],'decisions'=>['Risque','Date UTC','Auteur de saisie','Décision','Autorité','Mandat','Conditions','Référence et preuves','Revue'],'journal'=>['Risque','Date UTC','Auteur','Événement','Motif'],default=>throw new InputError('Tableau inconnu.')};
        foreach($risks as $r){$detail=$store->detail($r['id']);$type=match($table){'mesures'=>'actions','decisions'=>'decisions','journal'=>'events'};foreach($detail[$type] as $entry){$d=$entry['data']??[];$rows[]=match($table){'mesures'=>[$r['code'],$entry['id'],$d['title'],$d['owner'],$d['due'],exportStatus($d['status']),$d['proof'],$d['verification']??'Non vérifiée',$d['verified_by']??''],'decisions'=>[$r['code'],$entry['created_at'],$entry['actor'],$d['type'],$d['authority'],$d['mandate'],$d['conditions'],$d['evidence'],$d['valid_until']],'journal'=>[$r['code'],$entry['created_at'],$entry['actor'],$entry['event_type'],$entry['reason']]};}}
    }
    return ['headers'=>$headers,'rows'=>$rows];
}
function sendCsv(Store $store,string $table):never {
    $data=csvTable($store,$table);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="risques-ia-'.$table.'-'.gmdate('Y-m-d').'.csv"');
    $f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");foreach([$data['headers'],...$data['rows']] as $row)fputcsv($f,array_map(fn($v)=>csvSafe((string)$v),$row),';','"','');fclose($f);exit;
}
function presentationDraft(string $raw):array {
    try{$json=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new InputError('Le plan PowerPoint n’est pas structuré correctement. Réessayez avec une demande plus courte.');}
    if(!is_array($json)||!isset($json['slides'])||!is_array($json['slides'])||count($json['slides'])!==6)throw new InputError('Le plan doit contenir six diapositives.');
    $slides=[];foreach($json['slides'] as $slide){if(!is_array($slide)||!isset($slide['bullets'])||!is_array($slide['bullets'])||count($slide['bullets'])<1||count($slide['bullets'])>3)throw new InputError('Une diapositive doit contenir un à trois points.');$title=text($slide,'title',100,true);$notes=text($slide,'notes',1800,true);$bullets=[];foreach($slide['bullets'] as $bullet){if(!is_string($bullet)||trim($bullet)===''||mb_strlen($bullet)>240)throw new InputError('Point de présentation trop long.');$bullets[]=trim($bullet);}$slides[]=['title'=>$title,'bullets'=>$bullets,'notes'=>$notes];}return $slides;
}
function pptShort(string $text,int $max):string {
    $text=trim(preg_replace('/\s+/u',' ',$text)??$text);if(mb_strlen($text)<=$max)return $text;$short=mb_substr($text,0,$max-1);$space=mb_strrpos($short,' ');return ($space!==false&&$space>$max/2?mb_substr($short,0,$space):$short).'…';
}
function riskSlides(array $risk,?array $draft):array {
    if($draft){try{return presentationDraft($draft['output_text']);}catch(InputError){/* Un brouillon de mesures reste une source à relire, pas un plan imposé. */}}
    $d=$risk['data'];$actions=$risk['actions'];$measureLines=array_map(fn($a)=>$a['data']['title'].' — '.$a['data']['owner'].' — '.$a['data']['due'].' — '.exportStatus($a['data']['status']),$actions);
    $allMeasures=$measureLines?implode("\n",$measureLines):$d['measures'];
    return [
        ['title'=>'Gouvernance IA — '.$risk['code'],'bullets'=>[$d['title'],'Synthèse de validation — propositions à examiner'],'notes'=>'Export du registre à la date UTC '.gmdate('c').'. Version '.$risk['version'].'. Aucun export ne constitue une décision ou une autorisation.'],
        ['title'=>'Le risque et ses conséquences','bullets'=>[$d['scenario'],$d['impact']],'notes'=>'Fondement et limites : '.$d['basis']."\nPérimètre : ".$d['perimeter']],
        ['title'=>'Cotation et niveau de preuve','bullets'=>['Initial proposé : '.$d['score'].' ; vraisemblance '.$d['p'].' ; gravité '.$d['g'],'Cible conditionnelle : '.$d['target_score'].' ; efficacité des mesures à démontrer','Actuel sur preuves : '.($d['current']['score']??'non évalué')],'notes'=>'La cotation initiale est conservée. La cible n’est pas un résultat atteint. Gravité personnes '.$d['gp'].' ; mission '.$d['go'].'. Sources : '.$d['mapping']],
        ['title'=>'Mesures et responsables proposés','bullets'=>$measureLines?array_slice($measureLines,0,3):[$d['measures']],'notes'=>$allMeasures."\n\nPreuves attendues : ".$d['proof']."\n\n".implode("\n",array_map(fn($a)=>$a['data']['proof'],$actions))],
        ['title'=>$draft?'Pistes de l’IA à relire':'Preuves et points à vérifier','bullets'=>[$draft?pptShort($draft['output_text'],240):$d['proof'],'Aucune mesure ni cotation validée automatiquement'],'notes'=>$draft?'Brouillon Assistant IA ; état '.exportStatus($draft['disposition']).'. Texte intégral : '."\n".$draft['output_text']:'Contrôles connus : '.$d['controls']."\nLimites : ".$d['basis']],
        ['title'=>'Décisions et prochaines étapes','bullets'=>['Responsable du risque : '.$d['owner'],'Prochaine revue : '.$d['next_review'],'Décisions enregistrées : '.count($risk['decisions']).' ; statut : '.exportStatus($d['status'])],'notes'=>'Les responsables et échéances proposés doivent être confirmés. L’autorité compétente décide avec les métiers, le juridique et l’IT. '.implode("\n",array_map(fn($x)=>$x['data']['authority'].' : '.$x['data']['conditions'],$risk['decisions']))],
    ];
}
function pptDom(string $xml):DOMDocument {
    $dom=new DOMDocument();$dom->preserveWhiteSpace=true;if(!$dom->loadXML($xml,LIBXML_NONET))throw new RuntimeException('Modèle PowerPoint invalide.');return $dom;
}
function pptSetText(DOMDocument $dom,string $shapeName,array $lines):void {
    $xp=new DOMXPath($dom);$xp->registerNamespace('p','http://schemas.openxmlformats.org/presentationml/2006/main');$xp->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');
    $body=null;foreach($xp->query('//p:sp') as $shape){$name=$xp->query('p:nvSpPr/p:cNvPr',$shape)->item(0);if($name&&$name->getAttribute('name')===$shapeName){$body=$xp->query('p:txBody',$shape)->item(0);break;}}
    if(!$body)throw new RuntimeException('Champ Risques IA absent : '.$shapeName);
    $proto=$xp->query('a:p',$body)->item(0);$pPr=$proto?$xp->query('a:pPr',$proto)->item(0):null;$rPr=$proto?$xp->query('a:r/a:rPr',$proto)->item(0):null;
    foreach(iterator_to_array($xp->query('a:p',$body)) as $p)$body->removeChild($p);
    $a='http://schemas.openxmlformats.org/drawingml/2006/main';foreach($lines as $line){$p=$dom->createElementNS($a,'a:p');if($pPr)$p->appendChild($pPr->cloneNode(true));$r=$dom->createElementNS($a,'a:r');if($rPr)$r->appendChild($rPr->cloneNode(true));$t=$dom->createElementNS($a,'a:t');$t->appendChild($dom->createTextNode((string)$line));$r->appendChild($t);$p->appendChild($r);$body->appendChild($p);}
}
function pptNotes(DOMDocument $dom,string $text):void {
    $xp=new DOMXPath($dom);$xp->registerNamespace('p','http://schemas.openxmlformats.org/presentationml/2006/main');
    foreach($xp->query('//p:sp') as $shape){$ph=$xp->query('p:nvSpPr/p:nvPr/p:ph',$shape)->item(0);if($ph&&$ph->getAttribute('type')==='body'){$name=$xp->query('p:nvSpPr/p:cNvPr',$shape)->item(0)->getAttribute('name');pptSetText($dom,$name,explode("\n",$text));return;}}
    throw new RuntimeException('Notes orales absentes du modèle.');
}
function makePptx(array $risk,?array $draft,string $output):void {
    if(!class_exists(ZipArchive::class))throw new RuntimeException('Extension ZIP requise pour le PowerPoint.');
    $template=dirname(__DIR__).'/templates/generic-reference.pptx';if(!copy($template,$output))throw new RuntimeException('Modèle Risques IA indisponible.');
    $zip=new ZipArchive();if($zip->open($output)!==true)throw new RuntimeException('PowerPoint indisponible.');
    try{$slides=riskSlides($risk,$draft);foreach($slides as $index=>$slide){$i=$index+1;$part='ppt/slides/slide'.$i.'.xml';$dom=pptDom($zip->getFromName($part)?:'');
        if($i===1){pptSetText($dom,'Text 1',[gmdate('d.m.Y')]);pptSetText($dom,'Text 2',[pptShort($slide['title'],40)]);pptSetText($dom,'Text 3',['Brouillon de validation — '.$risk['code']]);}
        else{pptSetText($dom,'Text 2',[(string)$i]);pptSetText($dom,'Titre',[pptShort($slide['title'],48)]);pptSetText($dom,'Points de discussion',array_map(fn($s)=>pptShort($s,110),array_slice($slide['bullets'],0,3)));pptSetText($dom,'Validation attendue',['Propositions à relire — aucune validation automatique']);}
        $zip->addFromString($part,$dom->saveXML());$notes='ppt/notesSlides/notesSlide'.$i.'.xml';$n=pptDom($zip->getFromName($notes)?:'');pptNotes($n,$slide['title']."\n\n".implode("\n",$slide['bullets'])."\n\n".$slide['notes']."\n\nExport ".gmdate('c').' — '.$risk['code'].' version '.$risk['version'].' — Statut '.exportStatus($risk['data']['status']));$zip->addFromString($notes,$n->saveXML());
    }}finally{$zip->close();}
}
function sendPptx(Store $store,int $id,int $draftId,array $config):never {
    $risk=$store->detail($id);$draft=null;if($draftId){foreach($risk['drafts'] as $d)if($d['id']===$draftId){$draft=$d;break;}if(!$draft)throw new InputError('Brouillon introuvable pour ce risque.');}
    $path=tempnam($config['private_dir'],'risques-export-');if($path===false)throw new RuntimeException('Export indisponible.');
    try{makeEmPptx([$risk],true,true,$path,$draft);header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');header('Content-Disposition: attachment; filename="Risques IA-'.$risk['code'].'-'.gmdate('Y-m-d').'.pptx"');header('Content-Length: '.filesize($path));readfile($path);}finally{unlink($path);}exit;
}

require_once __DIR__.'/PresentationEM.php';
