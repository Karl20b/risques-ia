<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/domain_store.php';require dirname(__DIR__).'/src/Exports.php';
$table=csvTable($s,'risques');check(count($table['rows'])===16,'Export complet des risques');foreach($table['rows'] as $row)check(count($row)===count($table['headers']),'Colonnes CSV régulières');
$matrix=csvTable($s,'matrices');check(count($matrix['rows'])===49,'Matrices et ligne des non-évalués');$flat=encode($matrix);check(str_contains($flat,'Non évalués')&&str_contains($flat,'R15'),'Pas de confusion entre non évalué et faible');
check(count(csvTable($s,'mesures')['rows'])===1,'Export des mesures');check(count(csvTable($s,'decisions')['rows'])===1,'Export des décisions');check(count(csvTable($s,'journal')['rows'])>=16,'Export du journal');
rejects(fn()=>csvTable($s,'secret'),'Export hors liste refusé');
$slides=[];for($i=1;$i<=6;$i++)$slides[]=['title'=>'Diapositive fictive '.$i,'bullets'=>['Proposition à valider','Aucune mesure déjà réalisée'],'notes'=>'Notes orales fictives complètes, sans conformité présumée.'];
check(count(presentationDraft(encode(['slides'=>$slides])))===6,'Plan structuré accepté');rejects(fn()=>presentationDraft(encode(['slides'=>array_slice($slides,0,5)])),'Nombre de diapositives contrôlé');
$risk=$s->detail(15);check(count(riskSlides($risk,null))===6,'Synthèse enregistrée sans brouillon');$file=sys_get_temp_dir().'/risques-export-test.pptx';if(!is_dir(dirname($file)))mkdir(dirname($file),0700,true);makePptx($risk,['output_text'=>encode(['slides'=>$slides]),'model'=>'MODELE_FICTIF','disposition'=>'en_attente'],$file);
$zip=new ZipArchive();check($zip->open($file)===true,'PPTX lisible');for($i=1;$i<=6;$i++){check($zip->getFromName('ppt/slides/slide'.$i.'.xml')!==false,'Diapositive présente');check(str_contains($zip->getFromName('ppt/notesSlides/notesSlide'.$i.'.xml'),'Notes orales fictives complètes'),'Notes présentes');}
check(!str_contains($zip->getFromName('ppt/slides/slide2.xml'),'Ambition et périmètre proposés'),'Ancien contenu remplacé');$zip->close();echo "OK exports CSV, modèle Risques IA six diapositives et notes.\n";
