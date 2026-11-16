<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Domain.php';require dirname(__DIR__).'/src/Rag.php';
if(ragCorpus()['chunks']!==[]||ragCorpus()['sources']!==[])throw new RuntimeException('Le corpus de partage doit être vide.');
if(ragRetrieve('Une équipe fictive cherche une preuve de formation.','risques','mesures')!==[])throw new RuntimeException('Source inattendue.');
try{ragPrepare(['path'=>'risques','step'=>'mesures','content'=>'Une équipe fictive cherche une preuve de formation.']);throw new RuntimeException('Une génération documentaire vide doit être refusée.');}catch(InputError $e){}
$out=ragFormulationAnswer(json_encode(['question'=>'Comment examiner le risque d’une erreur non relue ?','scenario'=>'Si une erreur est reprise, un écrit pourrait être inexact.','a_preciser'=>['Qui vérifie le résultat avant utilisation ?']]));
if(count($out['a_preciser'])!==1)throw new RuntimeException('Piste invalide.');
echo "OK corpus vide, refus documentaire et piste française structurée. Aucun appel IA.\n";
