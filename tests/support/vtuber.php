<?php
require_once __DIR__.'/../../src/Catalog.php';
require_once __DIR__.'/../../src/Rules.php';
require_once __DIR__.'/../../src/Engine.php';
use Imaginary\{Catalog,ContentPack,Rules,Engine};
set_error_handler(function($n,$s,$f,$l){if(error_reporting()&$n)throw new ErrorException($s,0,$n,$f,$l);});
$vtChecks=0;
function vtCheck($ok,string $why): void {global $vtChecks;$vtChecks++;if(!$ok)throw new RuntimeException($why);}
function vtReject(callable $fn,string $why): void {try{$fn();}catch(InvalidArgumentException $e){vtCheck(true,$why);return;}throw new RuntimeException('Not rejected: '.$why);}
function vtCards(array $g): array {
    $cards=[];foreach(['deck','discard','draft'] as $zone)$cards=array_merge($cards,$g[$zone]??[]);
    foreach($g['players'] as $p){foreach(['hand','mind','spent','equipment','delayed'] as $zone)$cards=array_merge($cards,$p[$zone]);foreach($p['piles']??[] as $pile)$cards=array_merge($cards,$pile['cards']);foreach($p['sequestered']??[] as $h)$cards[]=$h['card'];}
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    foreach($g['queue'] as $e)if(($e['effect']??'')==='delayed')$cards[]=$e['card'];
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    return $cards;
}
function vtInvariant(array $g,int $total): void {
    $cards=vtCards($g);vtCheck(count($cards)===$total,'physical count '.count($cards).' / '.$total);vtCheck(count(array_unique(array_column($cards,'uid')))===$total,'unique physical IDs');
    foreach($g['players'] as $id=>$p){
        vtCheck($p['shield']>=0&&$p['shield']<=6,'bounded shield');
        foreach($p['piles']??[] as $key=>$pile)if($pile['visibility']==='private')foreach($g['order'] as $other)if($other!==$id)vtCheck(Engine::view($g,$other)['players'][array_search($id,$g['order'],true)]['piles'][$key]['cards']===[],'private pile never leaks');
    }
    if($g['status']==='playing'){ $actor=$g['pending']['player']??$g['turn'];vtCheck($g['players'][$actor]['alive'],'actor alive'); }
}
function vtSettle(array &$g,int $limit=100): void {
    for($n=0;$n<$limit&&$g['pending'];$n++){vtCheck(Engine::botStep($g),'pending choice progresses');}
    vtCheck(!$g['pending'],'resolution terminates');
}
function vtBuild(int $number): array {return ContentPack::data('vtuber-summons')['presets'][$number-1];}
function vtGame(int $number): array {
    $shell=Catalog::presets()[0];$shell['character']['skills']=[];$shell['character']['hp']=7;$shell['character']['flipColor']=null;
    $players=[];foreach(['a','b','c'] as $id){$b=$shell;$b['character']['series']=$id;$players[]=['id'=>$id,'name'=>$id,'build'=>$b,'bot'=>true];}
    $g=Engine::create($players,'series');foreach($g['players'] as &$p){$g['deck']=array_merge($g['deck'],$p['hand']);$p['hand']=[];}unset($p);
    $g['players']['a']['character']=vtBuild($number)['character'];$g['players']['a']['maxHp']=$g['players']['a']['character']['hp'];$g['players']['a']['color']=$g['players']['a']['character']['color'];$g['players']['a']['usedSkills']=[];
    $g['players']['a']['series']=$g['players']['a']['character']['series'];
    foreach(['b','c'] as $id)$g['players'][$id]['color']=$g['players']['a']['color']==='warm'?'cool':'warm';
    $g['phase']=$g['resumePhase']='play';return $g;
}
function vtCard(array &$g,string $id,string $type='defense'): string {
    foreach($g['deck'] as $i=>$c)if($c['type']===$type){array_splice($g['deck'],$i,1);$g['players'][$id]['hand'][]=$c;return $c['uid'];}throw new RuntimeException('Missing fixture card '.$type);
}
function vtAct(array &$g,string $id,array $a): void {Engine::act($g,$id,$a);}
function vtRespond(array &$g,string $choice,array $extra=[]): void {vtAct($g,$g['pending']['player'],array_merge(['type'=>'respond','choice'=>$choice],$extra));}
function vtCall(string $name,array &$g,...$args){$m=new ReflectionMethod(Engine::class,$name);$m->setAccessible(true);$params=[&$g];foreach($args as $a)$params[]=$a;return $m->invokeArgs(null,$params);}
