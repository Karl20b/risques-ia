<?php
declare(strict_types=1);
function helpContent():array {
    $path=dirname(__DIR__).'/help/guide.json';$raw=file_get_contents($path,false,null,0,150000);if($raw===false)throw new RuntimeException('Mode d’emploi indisponible.');$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('Mode d’emploi invalide.');return $data;
}
function helpAsset(string $asset):array {
    return match($asset){'capsule01'=>['capsule-01.mp4','video/mp4'],'poster01'=>['capsule-01.jpg','image/jpeg'],'captions01'=>['capsule-01.vtt','text/vtt; charset=utf-8'],'capsule02'=>['capsule-02.mp4','video/mp4'],'poster02'=>['capsule-02.jpg','image/jpeg'],'captions02'=>['capsule-02.vtt','text/vtt; charset=utf-8'],'capsule03'=>['capsule-03.mp4','video/mp4'],'poster03'=>['capsule-03.jpg','image/jpeg'],'captions03'=>['capsule-03.vtt','text/vtt; charset=utf-8'],'capsule04'=>['capsule-04.mp4','video/mp4'],'poster04'=>['capsule-04.jpg','image/jpeg'],'captions04'=>['capsule-04.vtt','text/vtt; charset=utf-8'],'capsule05'=>['capsule-05.mp4','video/mp4'],'poster05'=>['capsule-05.jpg','image/jpeg'],'captions05'=>['capsule-05.vtt','text/vtt; charset=utf-8'],default=>throw new InputError('Support d’aide inconnu.')};
}
function mediaRange(?string $header,int $size):?array {
    if($size<1)throw new InputError('Support vide.');if($header===null||$header==='')return null;
    if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$header,$m)||($m[1]===''&&$m[2]===''))throw new InputError('Plage de lecture invalide.');
    if($m[1]===''){$suffix=filter_var($m[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($suffix===false)throw new InputError('Plage invalide.');return [max(0,$size-$suffix),$size-1];}
    $start=filter_var($m[1],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$end=$m[2]===''?$size-1:filter_var($m[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($start===false||$end===false||$start>=$size||$end<$start)throw new InputError('Plage invalide.');return [$start,min($end,$size-1)];
}
function sendHelpMedia(string $asset):never {
    [$filename,$mime]=helpAsset($asset);$path=dirname(__DIR__).'/help/'.$filename;
    if(!is_file($path)||!is_readable($path))throw new InputError('Cette capsule n’est pas encore disponible.');$size=filesize($path);
    try{$range=mediaRange($_SERVER['HTTP_RANGE']??null,$size);}catch(InputError){http_response_code(416);header('Content-Range: bytes */'.$size);header('Content-Length: 0');session_write_close();exit;}
    header('Content-Type: '.$mime);header('Accept-Ranges: bytes');header('Content-Disposition: inline; filename="'.$filename.'"');header('Cache-Control: no-store, private');
    $start=$range[0]??0;$end=$range[1]??($size-1);$length=$end-$start+1;
    if($range){http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}header('Content-Length: '.$length);session_write_close();
    $stream=fopen($path,'rb');if(!$stream)exit;fseek($stream,$start);while($length>0&&!feof($stream)&&!connection_aborted()){$chunk=fread($stream,min(65536,$length));if($chunk===false||$chunk==='')break;echo $chunk;$length-=strlen($chunk);}fclose($stream);exit;
}
