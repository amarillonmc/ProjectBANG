<?php
/** Review the installed pack and play every character without writing a database. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\ContentPack;
use Imaginary\Rules;
use Imaginary\Engine;
use Imaginary\SkillBlocks;
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);});
$checks=0;
function mrCheck($ok,string $why): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function mrCards(array $g): array {
    $cards=array_merge($g['deck'],$g['discard'],$g['draft']??[]);
    foreach($g['players'] as $p){
        foreach(['hand','mind','spent','equipment','delayed'] as $z)$cards=array_merge($cards,$p[$z]);
        foreach($p['sequestered']??[] as $entry)$cards[]=$entry['card'];
    }
    foreach($g['queue'] as $e)if(($e['effect']??'')==='delayed')$cards[]=$e['card'];
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    return $cards;
}
function mrInvariant(array $g,int $count): void {
    $cards=mrCards($g);$uids=array_column($cards,'uid');
    mrCheck(count($cards)===$count,'physical count at turn '.$g['turnNumber']);
    mrCheck(count(array_unique($uids))===$count,'unique physical card IDs');
    foreach($g['players'] as $p){
        mrCheck($p['shield']>=0&&$p['shield']<=6,'shield bounded');
        mrCheck(($p['damageGuard']??0)>=0&&($p['damageGuard']??0)<=2,'damage guard bounded');
    }
    foreach($cards as $c)mrCheck($c['origin']!=='mind'||isset($g['players'][$c['owner']]),'mind owner retained');
    if($g['status']!=='playing')return;
    $actor=$g['pending']['player']??$g['turn'];mrCheck($g['players'][$actor]['alive'],'living active actor');
    $before=$g;$view=Engine::view($g,$actor);mrCheck($g===$before,'view is read-only');
    foreach($view['players'] as $p){
        mrCheck(!isset($p['hand'])&&!isset($p['mind'])&&!isset($p['sequestered']),'opponent zones stay private');
        mrCheck(isset($p['sequesteredCount'],$p['damageGuard'],$p['handLocked']),'public status counts exist');
    }
    if(($g['pending']['kind']??'')==='scry')foreach($g['order'] as $id)if($id!==$actor)mrCheck(!isset(Engine::view($g,$id)['pending']['cards']),'scry remains private');
}
$pack=ContentPack::data('magireco-expansions');$all=Catalog::presets();$old=ContentPack::data();$catalog=Catalog::all();
mrCheck(count($pack['presets'])===36&&count($pack['mindTemplates'])===72&&count($pack['skillTemplates'])===30,'36 characters, 72 cards and 30 templates');
mrCheck(count($all)===78&&count($old['presets'])===38,'both packs coexist with four originals');
mrCheck(array_column(array_slice($all,4,38),'id')===array_column($old['presets'],'id'),'legacy catalog order preserved');
mrCheck(count($catalog['contentPacks'])===2,'both content packs published');
$templates=array_column($pack['skillTemplates'],null,'id');$minds=array_column($pack['mindTemplates'],null,'id');$art=array_column($pack['arts'],null,'id');
$pairs=[];$used=[];$families=[];$bound=0;$ops=[];$triggers=[];$conditions=[];
$base=$all[0];unset($base['id']);$base['character']['hp']=4;$base['character']['flipColor']=null;$base['character']['skills']=[];
foreach($templates as $t){
    $b=$base;$b['character']['skills']=[$t['skill']];Rules::validateBuild($b);
    mrCheck(strpos($t['sourceUrl'],'https://')===0&&!empty($t['sourceVersion'])&&!empty($t['originalSummary'])&&!empty($t['adaptation']),'source and bounded adaptation: '.$t['id']);
    $families[$t['expansion']]=true;$triggers[$t['skill']['trigger']]=true;$conditions[$t['skill']['condition']]=true;
    foreach($t['skill']['effects'] as $e)$ops[$e['op']]=true;
}
foreach(['一将成名','SP','阴','雷'] as $f)mrCheck(isset($families[$f]),'required source family '.$f);
foreach(['sequester_hand','discard_equipment','damage_guard','hand_lock'] as $op)mrCheck(isset($ops[$op]),'pack uses new effect '.$op);
foreach(['on_targeted','ally_targeted','after_play_card','after_lose_equipment'] as $trigger)mrCheck(isset($triggers[$trigger]),'pack uses new trigger '.$trigger);
foreach(['played_two','played_hp','first_card','same_rank_or_suit','first_damage','has_equipment','hand_same_color'] as $condition)mrCheck(isset($conditions[$condition]),'pack uses condition '.$condition);
foreach($pack['presets'] as $i=>$build){
    $id='mr_'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT);$n=Rules::validateBuild($build);$c=$n['character'];$note=$pack['characterNotes'][$id];
    mrCheck($c['id']===$id&&$c['series']==='魔法纪录'&&$c['art']===$id,'ordered identity and art '.$id);
    mrCheck(count($c['skills'])===2&&count($n['deck'])===13,'two skills and thirteen slots '.$id);
    mrCheck(Rules::validateBuild($n)===$n,'idempotent canonical build '.$id);
    mrCheck(strlen($note['reason'])>100&&strlen($note['summary'])>20&&strpos($note['sourceUrl'],'https://')===0,'individual source and fit '.$id);
    $pair=$note['skillTemplateIds'];sort($pair);$key=implode('/',$pair);mrCheck(!isset($pairs[$key]),'unique pair '.$id);$pairs[$key]=true;
    foreach($note['skillTemplateIds'] as $j=>$tid){$actual=$c['skills'][$j];$expected=$templates[$tid]['skill'];unset($actual['name'],$expected['name']);mrCheck($actual==$expected,'source structure retained '.$id.'/'.$tid);$used[$tid]=true;}
    $customs=array_values(array_filter($n['deck'],function($d){return $d['type']==='custom';}));mrCheck(count($customs)===2,'two themed cards '.$id);
    foreach($customs as $j=>$slot){$t=$minds[$id.'_mind_'.($j+1)];mrCheck($slot['custom']==$t['card'],'equipped template matches '.$t['id']);mrCheck($t['card']['series']==='魔法纪录','shared exact IP');if(isset($t['card']['characterId'])){$bound++;mrCheck($j===1&&$t['card']['characterId']===$id,'optional second-card binding');}mrCheck(!empty($t['sourceTemplateIds'])&&!empty($t['adaptation']),'card mechanism sources recorded');foreach($t['sourceTemplateIds'] as $tid)mrCheck(isset($templates[$tid]),'card references known source');}
    mrCheck(isset($art[$id]),'portrait metadata '.$id);
    if(!in_array('--skip-art',$argv,true)){$file=__DIR__.'/../public/'.$art[$id]['url'];mrCheck(is_file($file),'portrait file '.$id);$size=getimagesize($file);mrCheck($size&&$size[0]>=512&&$size[1]>$size[0],'usable portrait '.$id);}
}
mrCheck(count($used)===30&&$bound===10,'all 30 templates used; ten optional bound cards');
// Every character leads one complete four-player game, alternating color and series.
// Partners share an IP, opponents include legacy content, and all physical zones count.
$games=0;$steps=0;$states=[];$activated=[];
foreach($pack['presets'] as $i=>$build){
    $partner=$pack['presets'][($i+13)%36];$foe=$old['presets'][$i%38];
    if($i%2===0)foreach($old['presets'] as $candidate)if($candidate['character']['color']!==$build['character']['color']){$foe=$candidate;break;}
    $builds=[$build,$foe,$partner,$all[$i%4]];$players=[];
    foreach($builds as $seat=>$b)$players[]=['id'=>'p'.$seat,'name'=>$b['character']['name'],'build'=>$b,'bot'=>true];
    $g=Engine::create($players,$i%2?'series':'color');$count=104+13*count($players);mrInvariant($g,$count);
    for($n=0;$n<3500&&!Engine::finished($g);$n++){
        $kind=$g['pending']['kind']??$g['phase'];$states[$kind]=true;
        mrCheck(Engine::botStep($g),'bot progresses '.$build['character']['name'].'/'.$kind);
        mrInvariant($g,$count);$steps++;
        foreach($g['players'] as $p)foreach($p['usedSkills']??[] as $index=>$usage)if(($usage['count']??0)>0){$skill=$p['character']['skills'][$index]??null;if($skill)$activated[$skill['trigger']]=true;}
    }
    mrCheck(Engine::finished($g),'game finished for '.$build['character']['name']);$games++;
}
echo "Magireco: $games complete games, $steps actions, $checks assertions. States: ".implode(', ',array_keys($states)).". Activated: ".implode(', ',array_keys($activated))."\n";
