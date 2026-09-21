<?php
/** Exercise the shipped content, not just isolated primitives. No database writes. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\ContentPack;
use Imaginary\Rules;
use Imaginary\Engine;
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);});
$checks=0;
function contentCheck($ok,string $why): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function contentCards(array $g): array {
    $cards=array_merge($g['deck'],$g['discard'],$g['draft']??[]);
    foreach($g['players'] as $p)foreach(['hand','mind','spent','equipment','delayed'] as $z)$cards=array_merge($cards,$p[$z]);
    foreach($g['queue'] as $e)if(($e['effect']??'')==='delayed')$cards[]=$e['card'];
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    return $cards;
}
function contentInvariant(array $g,int $count): void {
    $cards=contentCards($g);$uids=array_column($cards,'uid');
    contentCheck(count($cards)===$count,'physical count at turn '.$g['turnNumber']);
    contentCheck(count(array_unique($uids))===$count,'unique physical IDs');
    foreach($g['players'] as $p){contentCheck($p['maxHp']>=0&&$p['shield']>=0&&$p['shield']<=6,'bounded resources');}
    foreach($cards as $card)contentCheck($card['origin']!=='mind'||isset($g['players'][$card['owner']]),'mind owner retained');
    if($g['status']==='playing'){
        $actor=$g['pending']['player']??$g['turn'];contentCheck($g['players'][$actor]['alive'],'living actor');
        $before=$g;$view=Engine::view($g,$actor);contentCheck($g===$before,'view has no side effects');
        foreach($view['players'] as $p)contentCheck(!isset($p['hand'])&&!isset($p['mind']),'private zones omitted');
        if(($g['pending']['kind']??'')==='scry')foreach($g['order'] as $other)if($other!==$actor)contentCheck(!isset(Engine::view($g,$other)['pending']['cards']),'scry private');
    }
}
$pack=ContentPack::data();$all=Catalog::presets();
contentCheck(count($all)===42&&count($pack['presets'])===38,'42 presets including 38 friends');
contentCheck(count($pack['mindTemplates'])===76&&count($pack['skillTemplates'])===35,'76 cards and 35 templates');
$bound=0;$ids=[];
foreach($all as $b){$n=Rules::validateBuild($b);contentCheck(Rules::validateBuild($n)===$n,'normalization idempotent');contentCheck(count($n['deck'])===13,'13 slots');$ids[]=$n['character']['id'];foreach($n['character']['skills'] as $s)contentCheck(Rules::describeSkill($s)!=='','generated skill prose');}
contentCheck(count(array_unique($ids))===42,'distinct character identities');
foreach($pack['arts'] as $art){$file=__DIR__.'/../public/'.$art['url'];contentCheck(is_file($file),'missing artwork '.$art['id']);$size=getimagesize($file);contentCheck($size&&$size[0]>=512&&$size[1]>$size[0],'usable portrait '.$art['id']);}
$base=$all[0];unset($base['id']);$base['character']['hp']=4;$base['character']['flipColor']=null;$base['character']['skills']=[];
foreach($pack['skillTemplates'] as $t){$b=$base;$b['character']['skills']=[$t['skill']];Rules::validateBuild($b);contentCheck(!empty($t['description'])&&!empty($t['reference']),'template adaptation documented');}
foreach($pack['mindTemplates'] as $t){$b=$base;$b['deck'][4]=['type'=>'custom','rank'=>5,'custom'=>$t['card']];Rules::validateBuild($b);if(isset($t['card']['characterId'])){$bound++;contentCheck(in_array($t['card']['characterId'],$ids,true),'known bound identity');}}
contentCheck($bound===8,'eight optional character-bound cards');
// The four guardians ship together; the added two retain both an IP-shared and a bound signature.
$friends=[];foreach($pack['presets'] as $b)$friends[$b['character']['id']]=$b;
$mindTemplates=array_column($pack['mindTemplates'],null,'id');$arts=array_column($pack['arts'],null,'id');
contentCheck(count($arts)===38,'one distinct portrait per bundled friend');
foreach(['kf3_0098'=>'玄武','kf3_0099'=>'青龙','kf3_0100'=>'白虎','kf3_0101'=>'朱雀'] as $id=>$name){
    contentCheck(isset($friends[$id])&&$friends[$id]['character']['name']===$name,'four guardians include '.$name);
    contentCheck(isset($arts[$id],$pack['characterNotes'][$id]),'guardian has portrait and creator notes: '.$name);
}
foreach(['kf3_0100','kf3_0101'] as $id){
    $b=$friends[$id];$character=$b['character'];
    contentCheck(count($character['skills'])===2,'two character skills for '.$id);
    $customs=array_values(array_filter($b['deck'],function($card){return $card['type']==='custom';}));
    contentCheck(count($customs)===2,'two themed mind cards for '.$id);
    contentCheck($customs[0]['custom']['series']===$character['series']&&!isset($customs[0]['custom']['characterId']),'first signature shared within IP: '.$id);
    contentCheck($customs[1]['custom']['series']===$character['series']&&($customs[1]['custom']['characterId']??null)===$id,'second signature bound to its character: '.$id);
    foreach($customs as $index=>$card){
        $templateId=$id.'_mind_'.($index+1);
        contentCheck(isset($mindTemplates[$templateId])&&$mindTemplates[$templateId]['card']===$card['custom'],'signature template matches equipped card: '.$templateId);
    }
}
// Every new character leads a full game against both an IP ally and other IPs.
$steps=0;$games=0;$seen=[];
foreach($pack['presets'] as $i=>$build){
    $opposite=null;foreach($pack['presets'] as $p)if($p['character']['color']!==$build['character']['color']){$opposite=$p;break;}
    $builds=[$build,$all[0],$opposite,$all[1]];$players=[];
    foreach($builds as $seat=>$b)$players[]=['id'=>'p'.$seat,'name'=>$b['character']['name'],'build'=>$b,'bot'=>true];
    $g=Engine::create($players,$i%2?'series':'color');$initial=104+13*count($players);contentInvariant($g,$initial);
    for($n=0;$n<3500&&!Engine::finished($g);$n++){
        $actor=$g['pending']['player']??$g['turn'];$kind=$g['pending']['kind']??$g['phase'];$seen[$kind]=true;
        contentCheck(Engine::botStep($g),'bot progresses '.$build['character']['name'].' / '.$kind);
        contentInvariant($g,$initial);$steps++;
    }
    contentCheck(Engine::finished($g),'game ended '.$build['character']['name']);$games++;
}
echo "Content pack: $games complete games, $steps actions, $checks assertions. States: ".implode(', ',array_keys($seen))."\n";
