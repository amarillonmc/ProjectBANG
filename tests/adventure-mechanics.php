<?php
/** Stateful tests for real equipment, private information and creator compositions. */
require_once __DIR__.'/../src/Catalog.php';
require_once __DIR__.'/../src/Rules.php';
require_once __DIR__.'/../src/Engine.php';
use Imaginary\Catalog;
use Imaginary\Rules;
use Imaginary\Engine;
use Imaginary\SkillBlocks;
set_error_handler(function($n,$s,$f,$l){if(error_reporting()&$n)throw new ErrorException($s,0,$n,$f,$l);});
$checks=0;
function avCheck($ok,string $why): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function avE($op,$n=1,$target='self',$color='neutral'): array {return ['op'=>$op,'amount'=>$n,'target'=>$target,'color'=>$color];}
function avS($effects,$trigger='active',$condition='always'): array {return ['name'=>'验证技能','trigger'=>$trigger,'condition'=>$condition,'cost'=>['hand'=>0,'mind'=>0],'limit'=>1,'effects'=>$effects];}
function avGear($slot,$effects,$fallback='recover',$bound=null): array {return ['name'=>'验证装备','series'=>'冒险王','fallback'=>$fallback,'kind'=>'equipment','slot'=>$slot,'effects'=>$effects]+($bound===null?[]:['characterId'=>$bound]);}
function avGame($skills=[],$gear=null,$count=4): array {
    $base=Catalog::presets()[0];$base['character']['hp']=7;$base['character']['flipColor']=null;$base['character']['skills']=[];
    $base['deck'][4]=['rank'=>5,'type'=>'custom','custom'=>$gear??avGear('weapon',[avE('equip_range',2)])];
    $players=[];
    for($i=0;$i<$count;$i++){$b=$base;$b['character']['id']='test_'.$i;$b['character']['series']=$i?'对手'.$i:'冒险王';$b['character']['skills']=$i?[]:$skills;$players[]=['id'=>chr(97+$i),'name'=>chr(65+$i),'build'=>$b];}
    $g=Engine::create($players,'series');
    foreach($g['players'] as &$p){$g['deck']=array_merge($g['deck'],$p['hand']);$p['hand']=[];}unset($p);
    $g['queue']=[];$g['pending']=null;$g['phase']='play';$g['resumePhase']='play';return $g;
}
function avNormal(array &$g,$id,$type): string {foreach($g['deck'] as $i=>$c)if($c['type']===$type){array_splice($g['deck'],$i,1);$g['players'][$id]['hand'][]=$c;return $c['uid'];}throw new RuntimeException('missing fixture '.$type);}
function avMind(array &$g,$id='a'): string {foreach($g['players'][$id]['mind'] as $i=>$c)if($c['type']==='custom'){array_splice($g['players'][$id]['mind'],$i,1);$g['players'][$id]['hand'][]=$c;return $c['uid'];}throw new RuntimeException('missing mind');}
function avAction(array &$g,$id,$a): void {Engine::act($g,$id,$a);}
function avEquip(array &$g,$id='a'): string {$uid=avMind($g,$id);avAction($g,$id,['type'=>'play','card'=>$uid]);return $uid;}
function avPlayer(array $g,$id='a'): array {foreach(Engine::view($g,$id)['players'] as $p)if($p['id']===$id)return $p;throw new RuntimeException('missing player');}
function avReject(callable $fn,string $why): void {$bad=false;try{$fn();}catch(InvalidArgumentException $e){$bad=true;}avCheck($bad,$why);}

// Immediate range; one physical card stays equipped until removal. No event trigger.
$g=avGame([avS([avE('draw')],'after_equip'),avS([avE('shield')],'after_play_event')]);
$uid=avEquip($g);avCheck(avPlayer($g)['range']===3,'weapon gives immediate range');
avCheck(count($g['players']['a']['hand'])===1&&$g['players']['a']['shield']===0,'equipment triggers equip, not event');
avCheck(!in_array($uid,array_column($g['players']['a']['spent'],'uid'),true),'equipping does not spend the physical mind');
$g['players']['a']['character']['skills']=[avS([avE('recall_equipment')])];$g['players']['a']['usedSkills']=[];
avAction($g,'a',['type'=>'skill','index'=>0]);avCheck(avPlayer($g)['range']===1,'recall removes range immediately');
avCheck(in_array($uid,array_column($g['players']['a']['hand'],'uid'),true),'recall returns exact card to hand');

// Slots replace only their own slot, with one loss trigger and original ownership.
$g=avGame([avS([avE('draw')],'after_lose_equipment')]);$first=avEquip($g);
$other=avMind($g,'b');$card=array_pop($g['players']['b']['hand']);$card['custom']['series']='冒险王';$g['players']['a']['hand'][]=$card;
avAction($g,'a',['type'=>'play','card'=>$other]);
avCheck(count($g['players']['a']['equipment'])===1,'same slot replaces old equipment');
avCheck(in_array($first,array_column($g['players']['a']['spent'],'uid'),true)&&count($g['players']['a']['hand'])===1,'replacement spends once and triggers loss');
$g['players']['a']['character']['skills']=[avS([avE('discard_equipment')])];$g['players']['a']['usedSkills']=[];
avAction($g,'a',['type'=>'skill','index'=>0]);avCheck(in_array($other,array_column($g['players']['b']['spent'],'uid'),true),'borrowed equipment goes to original owner');

// Periodic resources do not fire at installation or the start of someone else's turn.
$g=avGame([],avGear('gadget',[avE('equip_draw')]));avEquip($g);avCheck(count($g['players']['a']['hand'])===0,'gadget gives no entry draw');
$g['turn']='d';avAction($g,'d',['type'=>'end']);avCheck(count($g['players']['a']['hand'])===1&&$g['phase']==='draw','gadget draws once before normal draw phase');
$g=avGame([],avGear('armor',[avE('equip_shield',2)]));avEquip($g);avCheck($g['players']['a']['shield']===0,'armor gives no entry shield');
$g['players']['a']['shield']=5;$g['turn']='d';avAction($g,'d',['type'=>'end']);avCheck($g['players']['a']['shield']===2,'old shield cleared before periodic armor');

// Defensive distance changes all attack checks, but does not remove adjacent ally support.
$g=avGame([],avGear('armor',[avE('equip_distance')]));avEquip($g);$g['turn']='b';
$hit=avNormal($g,'b','attack_neutral');$before=$g;
avReject(function()use(&$g,$hit){avAction($g,'b',['type'=>'play','card'=>$hit,'target'=>'a']);},'armor increases incoming attack distance');avCheck($g===$before,'illegal out-of-range attack rolls back');
$g['players']['b']['rangeBonus']=1;avAction($g,'b',['type'=>'play','card'=>$hit,'target'=>'a']);avCheck($g['pending']['kind']==='attack','range counters defensive distance');
$g=avGame([],avGear('armor',[avE('equip_distance')]));avEquip($g);
$g['players']['d']['character']['series']='冒险王';$g['players']['d']['series']='冒险王';$g['players']['d']['character']['skills']=[avS([avE('shield',1,'target')],'ally_targeted')];
$g['turn']='b';$g['players']['b']['rangeBonus']=1;$hit=avNormal($g,'b','attack_neutral');avAction($g,'b',['type'=>'play','card'=>$hit,'target'=>'a']);avCheck($g['players']['a']['shield']===1,'neighbor guard uses seating distance, not armor distance');

// First connected attack per owner cycle; blocked damage still spends it; reinstallation cannot refresh it.
$g=avGame([],avGear('weapon',[avE('equip_damage')]));$weapon=avEquip($g);$g['players']['a']['extraAttacks']=3;
$g['players']['b']['shield']=6;$attack=avNormal($g,'a','attack_neutral');avAction($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);avAction($g,'b',['type'=>'respond','choice'=>'damage']);
avCheck($g['players']['b']['shield']===4,'first hit consumes base plus equipment damage');
$g['players']['a']['character']['skills']=[avS([avE('recall_equipment')])];avAction($g,'a',['type'=>'skill','index'=>0]);avAction($g,'a',['type'=>'play','card'=>$weapon]);
$attack=avNormal($g,'a','attack_neutral');avAction($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);avAction($g,'b',['type'=>'respond','choice'=>'damage']);
avCheck($g['players']['b']['shield']===3,'same cycle reinstall does not refresh weapon damage');
$g['players']['a']['turns']++;$attack=avNormal($g,'a','attack_neutral');avAction($g,'a',['type'=>'play','card'=>$attack,'target'=>'b']);avAction($g,'b',['type'=>'respond','choice'=>'damage']);avCheck($g['players']['b']['shield']===1,'next own cycle refreshes weapon damage');

// Fallback and character binding work before the custom equipment branch.
$g=avGame([],avGear('weapon',[avE('equip_range')],'mana','some_other_character'));$uid=avMind($g);avAction($g,'a',['type'=>'play','card'=>$uid,'mode'=>'draw']);
avCheck(!$g['players']['a']['equipment']&&count($g['players']['a']['hand'])===2,'wrong bound character uses ordinary fallback');
$g=avGame([],avGear('weapon',[avE('equip_range')],'evade'));$uid=avMind($g);$card=array_pop($g['players']['a']['hand']);$g['players']['b']['hand'][]=$card;$g['turn']='b';avAction($g,'b',['type'=>'play','card'=>$uid]);
avCheck($g['players']['b']['equipment'][0]['type']==='evade'&&$g['players']['b']['equipment'][0]['printedType']==='custom','foreign series equips fallback persistent card');
$g['players']['b']['character']['skills']=[avS([avE('recall_equipment')])];avAction($g,'b',['type'=>'skill','index'=>0]);avCheck($g['players']['b']['hand'][0]['type']==='custom'&&!isset($g['players']['b']['hand'][0]['printedType']),'recall restores printed identity');

// Legacy treasure side effect and physical card conservation also apply to recall.
$g=avGame([avS([avE('recall_equipment')])]);$uid=avNormal($g,'a','treasure');avAction($g,'a',['type'=>'play','card'=>$uid]);avAction($g,'a',['type'=>'skill','index'=>0]);
avCheck($g['players']['a']['marks']['neutral']===2&&in_array($uid,array_column($g['players']['a']['hand'],'uid'),true),'recall treasure still inflicts removal damage');

// Investigation is a private snapshot, and the following attack waits for confirmation.
$g=avGame([avS([avE('inspect_hand',2,'target'),avE('attack',1,'target','warm')])]);avNormal($g,'b','defense');avNormal($g,'b','attack_neutral');
$hand=$g['players']['b']['hand'];avAction($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
avCheck($g['pending']['kind']==='inspect_hand'&&$g['players']['b']['hand']===$hand,'investigation does not move target hand');
avCheck(count(Engine::view($g,'a')['pending']['cards'])===2&&!isset(Engine::view($g,'b')['pending']['cards'])&&!isset(Engine::view($g,'c')['pending']['cards']),'only investigator sees inspected cards');
$before=$g;avReject(function()use(&$g){avAction($g,'c',['type'=>'respond','choice'=>'confirm']);},'non-chooser cannot confirm private window');avCheck($g===$before,'wrong responder is atomic');
avAction($g,'a',['type'=>'respond','choice'=>'confirm']);avCheck($g['pending']['kind']==='attack','investigation resumes later attack');

// Mind reordering does not temporarily remove the last mind, even while waiting.
$g=avGame([avS([avE('scry_mind',3),avE('draw_mind')])]);$g['players']['a']['spent']=array_splice($g['players']['a']['mind'],3);
$original=array_column($g['players']['a']['mind'],'uid');avAction($g,'a',['type'=>'skill','index'=>0]);
avCheck(count($g['players']['a']['mind'])===3&&!avPlayer($g)['broken'],'reorder leaves mind in its zone');
avCheck(!isset(Engine::view($g,'b')['pending']['cards']),'reorder private');$before=$g;
avReject(function()use(&$g,$original){avAction($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>[$original[0],$original[0],$original[2]]]);},'repeated reorder uid rejected');avCheck($g===$before,'invalid reorder rolls back');
avAction($g,'a',['type'=>'respond','choice'=>'confirm','cards'=>array_reverse($original)]);avCheck($g['players']['a']['hand'][0]['uid']===$original[2]&&$g['players']['a']['mind'][0]['uid']===$original[1],'draw mind follows chosen order');
$g=avGame([avS([avE('cycle_mind',2)])]);$original=array_column($g['players']['a']['mind'],'uid');avAction($g,'a',['type'=>'skill','index'=>0]);avCheck(array_column($g['players']['a']['mind'],'uid')===array_merge(array_slice($original,2),array_slice($original,0,2)),'cycle moves exactly existing top cards');
$g=avGame([avS([avE('draw_mind')])]);$g['players']['a']['spent']=array_splice($g['players']['a']['mind'],1);avAction($g,'a',['type'=>'skill','index'=>0]);avCheck(avPlayer($g)['broken']&&avPlayer($g)['range']===0,'drawing last mind immediately breaks mind');
$g=avGame([avS([avE('break_shield',3,'target')])]);$g['players']['b']['shield']=2;$g['players']['b']['damageGuard']=1;avAction($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);avCheck($g['players']['b']['shield']===0&&$g['players']['b']['damageGuard']===1&&$g['players']['b']['marks']['neutral']===0,'break shield neither damages nor removes guard');

// Creator compositions account for mind draw/recovery and costs before later gifts.
$g=avGame([avS([avE('draw_mind'),avE('give_hand',1,'target')])]);$top=$g['players']['a']['mind'][0]['uid'];
avAction($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);avCheck($g['players']['b']['hand'][0]['uid']===$top,'draw mind can supply a later gift');
$s=avS([avE('draw_mind'),avE('give_hand',1,'target')]);$s['cost']['mind']=1;$g=avGame([$s]);$g['players']['a']['spent']=array_splice($g['players']['a']['mind'],1);$before=$g;
avReject(function()use(&$g){avAction($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);},'mind paid as cost cannot be drawn again');avCheck($g===$before,'insufficient post-cost gift rejects atomically');
$s=avS([avE('recover_mind'),avE('draw_mind'),avE('give_hand',1,'target')]);$s['cost']['mind']=1;$g=avGame([$s]);$left=array_splice($g['players']['a']['mind'],1);$top=$g['players']['a']['mind'][0]['uid'];
avAction($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);avCheck($g['players']['b']['hand'][0]['uid']===$top,'recover can recycle the mind cost into a later draw and gift');

// Conditions distinguish genuine equipment from delayed basics, and use the current mind count.
$g=avGame();$s=avS([avE('draw_mind')],'turn_start');$s['cost']['mind']=1;$g['players']['a']['character']['skills']=[$s];$g['turn']='d';
avAction($g,'d',['type'=>'end']);avCheck(count($g['players']['a']['mind'])===11&&count($g['players']['a']['spent'])===1&&count($g['players']['a']['hand'])===1,'automatic skill forecasting never mutates live referenced zones or pays twice');
$g=avGame([avS([avE('shield')],'active','has_true_equipment')]);$evade=avNormal($g,'a','evade');avAction($g,'a',['type'=>'play','card'=>$evade]);
avReject(function()use(&$g){avAction($g,'a',['type'=>'skill','index'=>0]);},'delayed basic does not satisfy true equipment');avEquip($g);avAction($g,'a',['type'=>'skill','index'=>0]);avCheck($g['players']['a']['shield']===1,'real equipment satisfies condition');
$g=avGame([avS([avE('shield')],'active','mind_low')]);$g['players']['a']['spent']=array_splice($g['players']['a']['mind'],5);
avReject(function()use(&$g){avAction($g,'a',['type'=>'skill','index'=>0]);},'five mind is above low-mind threshold');$g['players']['a']['spent'][]=array_pop($g['players']['a']['mind']);avAction($g,'a',['type'=>'skill','index'=>0]);avCheck($g['players']['a']['shield']===1,'four mind satisfies low-mind condition');

// Creator safety: persistent modifiers cannot be smuggled into skills/events or wrong slots.
$base=Catalog::presets()[0];$base['character']['hp']=4;$base['character']['skills']=[];$base['character']['flipColor']=null;
$b=$base;$b['character']['skills']=[avS([avE('equip_damage')])];avReject(function()use($b){Rules::validateBuild($b);},'equipment op forbidden in character skill');
$b=$base;$b['deck'][4]['custom']['effects']=[avE('equip_draw')];avReject(function()use($b){Rules::validateBuild($b);},'equipment op forbidden in event');
$b=$base;$b['deck'][4]['custom']=avGear('armor',[avE('equip_draw')]);avReject(function()use($b){Rules::validateBuild($b);},'slot whitelist enforced');
$b=$base;$b['deck'][4]['custom']=avGear('weapon',[avE('equip_damage'),avE('equip_damage')]);avReject(function()use($b){Rules::validateBuild($b);},'duplicate persistent modifiers forbidden');
$g=avGame();$g['rulesVersion']='0.3.0-alpha';avAction($g,'a',['type'=>'end']);avCheck($g['rulesVersion']===SkillBlocks::VERSION,'old 0.3 room migrates');
echo "PASS $checks adventure mechanics assertions\n";
