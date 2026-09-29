<?php
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\ContentPack;
use Imaginary\Rules;
use Imaginary\Engine;
set_error_handler(function($n,$s,$f,$l){if(error_reporting()&$n)throw new ErrorException($s,0,$n,$f,$l);});
$checks=0;$steps=0;$games=0;$states=[];
function acCheck($ok,$why): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function acCards($g): array {
    $cards=array_merge($g['deck'],$g['discard'],$g['draft']??[]);
    foreach($g['players'] as $p){foreach(['hand','mind','spent','equipment','delayed'] as $z)$cards=array_merge($cards,$p[$z]);foreach($p['sequestered']??[] as $held)$cards[]=$held['card'];}
    foreach($g['queue'] as $e)if(($e['effect']??'')==='delayed')$cards[]=$e['card'];
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    return $cards;
}
function acInvariant($g,$total): void {
    $cards=acCards($g);acCheck(count($cards)===$total,'physical count');acCheck(count(array_unique(array_column($cards,'uid')))===$total,'unique physical IDs');
    foreach($g['players'] as $p){$slots=[];foreach($p['equipment'] as $c)if($c['type']==='custom'&&($c['custom']['kind']??'event')==='equipment'){$slot=$c['custom']['slot'];acCheck(!isset($slots[$slot]),'one real equipment per slot');$slots[$slot]=true;}acCheck($p['shield']>=0&&$p['shield']<=6,'bounded shield');}
    foreach($cards as $c)if($c['origin']==='mind')acCheck(isset($g['players'][$c['owner']]),'mind owner exists');
    if($g['status']!=='playing')return;
    $actor=$g['pending']['player']??$g['turn'];acCheck($g['players'][$actor]['alive'],'actor is alive');
    if(in_array($g['pending']['kind']??'',['scry','scry_mind','inspect_hand'],true))foreach($g['order'] as $other)if($actor!==$other)acCheck(!isset(Engine::view($g,$other)['pending']['cards']),'private choice hides identities');
}
$pack=ContentPack::data('adventure-king');$catalog=Catalog::all();
acCheck(count($pack['presets'])===30&&count($pack['mindTemplates'])===60&&count($pack['skillTemplates'])===42,'30 characters / 60 cards / 42 templates');
acCheck(count($catalog['presets'])===108&&count($catalog['contentPacks'])===3,'all three packs coexist');
acCheck(count(array_unique(array_column($pack['characterNotes'],'branch')))===6,'all six literary branches');
$templates=array_column($pack['skillTemplates'],null,'id');$minds=array_column($pack['mindTemplates'],null,'id');$used=[];$pairs=[];$hashes=[];
foreach($pack['presets'] as $b){
    $n=Rules::validateBuild($b);acCheck(Rules::validateBuild($n)===$n,'idempotent build validation');$notes=$pack['characterNotes'][$b['character']['id']];
    foreach($notes['skillTemplateIds'] as $i=>$tid){$used[$tid]=true;acCheck($templates[$tid]['skill']===$b['character']['skills'][$i],'actual skill equals reusable template');}
    $pair=implode('/',$notes['skillTemplateIds']);acCheck(!isset($pairs[$pair]),'unique skill pair');$pairs[$pair]=true;
    acCheck($b['character']['series']==='冒险王'&&!empty($notes['designReason'])&&strpos($notes['sourceUrl'],'https://')===0,'unified IP, design reason and research source');
}
acCheck(count($used)===42,'all named skill puzzles appear in characters');
$shell=Catalog::presets()[0];unset($shell['id']);$shell['character']['hp']=4;$shell['character']['flipColor']=null;$shell['character']['skills']=[];
foreach($templates as $t){$b=$shell;$b['character']['skills']=[$t['skill']];Rules::validateBuild($b);acCheck(!empty($t['designReason']),'skill reason');}
$gearCount=0;
foreach($minds as $t){$b=$shell;$b['deck'][4]=['rank'=>5,'type'=>'custom','custom'=>$t['card']];Rules::validateBuild($b);$gearCount+=($t['card']['kind']??'event')==='equipment'?1:0;acCheck(!empty($t['designReason']),'card reason');}
acCheck($gearCount===22,'22 real equipment cards');
if(!in_array('--skip-art',$argv,true))foreach($pack['arts'] as $art){$file=__DIR__.'/../public/'.$art['url'];acCheck(is_file($file),'art exists '.$art['id']);$size=getimagesize($file);acCheck($size&&$size[0]>=512&&$size[1]>$size[0],'portrait dimensions');$hash=hash_file('sha256',$file);acCheck(!isset($hashes[$hash]),'unique portrait');$hashes[$hash]=true;}

// Every authored card is physically played, including gear and all bound events.
foreach($minds as $t){
    $a=$shell;$a['character']['hp']=7;$a['character']['id']=$t['card']['characterId']??'review';$a['character']['series']='冒险王';$a['deck'][4]=['rank'=>5,'type'=>'custom','custom'=>$t['card']];
    $b=$shell;$b['character']['series']='其他系列';$b['character']['color']='warm';$b['character']['hp']=7;
    $g=Engine::create([['id'=>'a','name'=>'设计测试','build'=>$a,'bot'=>true],['id'=>'b','name'=>'目标','build'=>$b,'bot'=>true]],'series');
    $g['phase']='play';$g['resumePhase']='play';
    $card=$g['players']['a']['mind'][4];array_splice($g['players']['a']['mind'],4,1);$g['players']['a']['hand'][]=$card;
    // Supply an actual ordinary equipment for recall/discard compositions, retaining card conservation.
    foreach($g['deck'] as $i=>$normal)if($normal['type']==='evade'){array_splice($g['deck'],$i,1);$normal['readyAt']=0;$g['players']['a']['equipment'][]=$normal;break;}
    $total=count(acCards($g));$options=array_values(array_filter(Engine::legalActions($g,'a'),function($o)use($card){return ($o['action']['type']??'')==='play'&&($o['action']['card']??'')===$card['uid'];}));
    acCheck(!empty($options),'card can be played: '.$t['name']);Engine::act($g,'a',$options[0]['action']);acInvariant($g,$total);
    for($n=0;$n<20&&$g['pending'];$n++){acCheck(Engine::botStep($g),'private/attack response progresses');acInvariant($g,$total);}
    acCheck(!$g['pending'],'card resolution terminates: '.$t['name']);
}

// Mixed-IP 2–6 player games exercise the actual packaged builds and all turn phases.
$legacy=array_slice(Catalog::presets(),0,4);
foreach($pack['presets'] as $i=>$build){
    $count=2+$i%5;$mode=$i%2?'series':'color';$players=[['id'=>'a','name'=>$build['character']['name'],'build'=>$build,'bot'=>true]];
    for($j=1;$j<$count;$j++){$other=$j===1?$legacy[$build['character']['color']==='warm'?0:1]:($j%2?$legacy[$j%4]:$pack['presets'][($i+$j)%30]);$players[]=['id'=>chr(97+$j),'name'=>'测试'.$j,'build'=>$other,'bot'=>true];}
    $g=Engine::create($players,$mode);$total=count(acCards($g));
    $simulationLimit=(\Imaginary\RuleConfig::get('maxTurns')+1)*(\Imaginary\RuleConfig::get('maxActionsPerTurn')+1)*8;
    for($n=0;$n<$simulationLimit&&$g['status']==='playing';$n++){$states[$g['pending']['kind']??$g['phase']]=true;acInvariant($g,$total);acCheck(Engine::botStep($g),'bot has legal progress');$steps++;}
    acInvariant($g,$total);acCheck($g['status']==='finished','complete game for '.$build['character']['name'].'; turn='.$g['turnNumber'].' phase='.($g['pending']['kind']??$g['phase']).' steps='.$n);$games++;
}
echo "Adventure: $games complete games, $steps actions, $checks assertions; states: ".implode(', ',array_keys($states))."\n";
