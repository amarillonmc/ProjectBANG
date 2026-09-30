<?php
/** Build-time bridge: the server validator is the only authority on content budgets. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
use Imaginary\Rules;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$pack=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
$errors=[];$summaries=[];
foreach($pack['presets'] as $i=>$build) {
    try {
        $b=Rules::validateBuild($build);$pack['presets'][$i]=$b;
        $id=$b['character']['id'];$notes=$pack['characterNotes'][$id];
        foreach($notes['skillTemplateIds'] as $j=>$tid) foreach($pack['skillTemplates'] as &$t) if($t['id']===$tid) $t['skill']=$b['character']['skills'][$j];
        unset($t);
        foreach($notes['mindTemplateIds'] as $j=>$tid) foreach($pack['mindTemplates'] as &$t) if($t['id']===$tid) $t['card']=$b['deck'][$j?8:4]['custom'];
        unset($t);
        $summaries[$id]=['budget'=>Rules::budget($b),'skills'=>array_map([Rules::class,'describeSkill'],$b['character']['skills'])];
    } catch (Throwable $e) { $errors[]=$build['character']['name'].': '.$e->getMessage(); }
}
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);}
echo json_encode(['pack'=>$pack,'summaries'=>$summaries],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
