<?php
require __DIR__.'/support/vtuber.php';
use Imaginary\{Catalog,ContentPack,Rules,Engine,RuleConfig};
$pack=ContentPack::data('vtuber-summons');$catalog=Catalog::all();
vtCheck(count($pack['presets'])===36&&count($pack['skillTemplates'])===72&&count($pack['mindTemplates'])===72,'36 / 72 / 72 published');
vtCheck(count($catalog['presets'])===144&&count($catalog['contentPacks'])===4,'four packs coexist');
vtCheck($pack['presets'][0]['character']['name']==='绊爱'&&$pack['presets'][6]['character']['name']==='Azuma Lim','requested opening and Azuma Lim');
$skills=array_column($pack['skillTemplates'],null,'id');$minds=array_column($pack['mindTemplates'],null,'id');$pairs=[];$bound=0;
foreach($pack['presets'] as $b){
    $valid=Rules::validateBuild($b);vtCheck(Rules::validateBuild($valid)===$valid,'canonical build roundtrip');$c=$b['character'];$n=$pack['characterNotes'][$c['id']];$budget=Rules::budget($valid);
    vtCheck($c['series']==='电拟神召'&&$budget['character']<=18&&array_sum($budget['cards'])<=24&&max($budget['cards'])<=12,'series and default budgets');
    foreach($n['skillTemplateIds'] as $i=>$id)vtCheck($skills[$id]['skill']===$c['skills'][$i],'reusable skill equals preset');
    $pair=implode('/',$n['skillTemplateIds']);vtCheck(!isset($pairs[$pair]),'unique skill pair');$pairs[$pair]=true;
    foreach([0=>4,1=>8] as $i=>$slot)vtCheck($minds[$n['mindTemplateIds'][$i]]['card']===$b['deck'][$slot]['custom'],'mind template equals preset');
    vtCheck(!empty($n['aliases'])&&!empty($n['selectionReason'])&&!empty($n['designReason'])&&strpos($n['sourceUrl'],'https://')===0,'selection, identity, source and design documented');
}
foreach($minds as $t)if(isset($t['card']['characterId']))$bound++;
vtCheck($bound===36,'one bound card per virtual identity');
if(!in_array('--skip-art',$argv,true)){
    $manifest=array_column(json_decode(file_get_contents(__DIR__.'/../docs/content/vtuber-art-manifest.json'),true),null,'id');$hashes=[];
    foreach($pack['arts'] as $art){$f=__DIR__.'/../public/'.$art['url'];vtCheck(is_file($f),'portrait exists '.$art['id']);$size=getimagesize($f);vtCheck($size&&$size[0]>=512&&$size[1]>$size[0],'portrait dimensions');$hash=hash_file('sha256',$f);vtCheck(!isset($hashes[$hash])&&$manifest[$art['id']]['sha256']===$hash,'unique unmodified generated portrait');$hashes[$hash]=true;}
}

// Play every authored mind card through public actions, including actual equipment and bound identities.
$shell=Catalog::presets()[0];$shell['character']['skills']=[];$shell['character']['hp']=7;$shell['character']['flipColor']=null;
foreach($minds as $t){
    $a=$shell;$a['character']['id']=$t['card']['characterId']??'vt_review';$a['character']['series']='电拟神召';$a['deck'][4]=['rank'=>5,'type'=>'custom','custom'=>$t['card']];$b=$shell;$b['character']['series']='外部';$b['character']['color']=$a['character']['color']==='warm'?'cool':'warm';$c=$shell;$c['character']['series']='电拟神召';
    $g=Engine::create([['id'=>'a','name'=>'卡牌验收','build'=>$a,'bot'=>true],['id'=>'b','name'=>'对手','build'=>$b,'bot'=>true],['id'=>'c','name'=>'同伴','build'=>$c,'bot'=>true]],'series');$g['phase']=$g['resumePhase']='play';
    $card=$g['players']['a']['mind'][4];array_splice($g['players']['a']['mind'],4,1);$g['players']['a']['hand'][]=$card;
    $legal=array_values(array_filter(Engine::legalActions($g,'a'),function($o)use($card){return ($o['action']['type']??'')==='play'&&($o['action']['card']??'')===$card['uid'];}));
    vtCheck((bool)$legal,'card playable '.$t['name']);vtAct($g,'a',$legal[0]['action']);vtInvariant($g,143);vtSettle($g);vtInvariant($g,143);
}

// Exercise all packaged builds in complete 2–6-seat games, with both victory modes and old packs.
$steps=0;$states=[];$games=0;
foreach($pack['presets'] as $i=>$build){
    $players=[['id'=>'a','name'=>$build['character']['name'],'build'=>$build,'bot'=>true]];$seats=2+$i%5;
    for($j=1;$j<$seats;$j++){$other=$j===1?Catalog::presets()[$build['character']['color']==='warm'?0:1]:($j%2?Catalog::presets()[$j%4]:$pack['presets'][($i+$j)%36]);$players[]=['id'=>chr(97+$j),'name'=>'测试'.$j,'build'=>$other,'bot'=>true];}
    $g=Engine::create($players,$i%2?'series':'color');$total=104+13*$seats;$limit=(RuleConfig::get('maxTurns')+1)*(RuleConfig::get('maxActionsPerTurn')+1)*8;
    for($n=0;$n<$limit&&$g['status']==='playing';$n++){
        $states[$g['pending']['kind']??$g['phase']]=true;vtInvariant($g,$total);
        try{vtCheck(Engine::botStep($g),'bot progresses');}catch(Throwable $e){file_put_contents(__DIR__.'/../var/vtuber-failed-game.json',json_encode($g,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));throw $e;}
        if($n%37===0)$g=json_decode(json_encode($g),true);$steps++;
    }
    vtInvariant($g,$total);vtCheck($g['status']==='finished','complete game '.$build['character']['name']);$games++;
}
echo "VTuber: $games complete games, $steps actions, $vtChecks assertions; states: ".implode(', ',array_keys($states)).PHP_EOL;
