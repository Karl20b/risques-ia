<?php
return [
 'environment'=>'validation',
 'origin'=>'https://risques.example.org',
 'base_path'=>'/',
 'private_dir'=>dirname(__DIR__).'/var',
 'db'=>['host'=>'localhost','port'=>3306,'name'=>'risques_demo','user'=>'risques_app','password'=>''],
 'users'=>[],
 'ai'=>['enabled'=>false,'endpoint'=>'','allowed_host'=>'','api_key'=>'','model'=>'','reasoning_effort'=>'none','max_input_chars'=>10000],
];
