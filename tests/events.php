<?php
require_once __DIR__.'/mechanics.php';
use Imaginary\{Rules,Engine,RuleConfig};
RuleConfig::configure(['characterBudget'=>1000]);
// A pending replacement must suspend damage, shield consumption, and the outer effect group.
$g=fg([fs('active',[fe('damage',2,'target'),fe('draw')],1),fs('before_deal_damage',[fe('event_add_damage',2)]),fs('after_deal_damage',[fe('draw','event_amount')])]);
$g['players']['b']['maxHp']=20;$g['players']['b']['shield']=1;
$g['players']['b']['character']['skills']=[fs('before_take_damage',[fe('event_reduce_damage')],0,0,['optional'=>true]),fs('after_damage',[fe('draw','event_amount')]),fs('damage_point',[fe('add_mark')+['key'=>'点']])];
$g['players']['c']['character']['skills']=[fs('any_after_damage',[fe('draw','event_amount')])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['pending']['player']==='b'&&$g['players']['b']['shield']===1&&$g['players']['b']['marks']['neutral']===0&&!$g['players']['a']['hand'],'pre-damage choice suspends damage and outer continuation');
$g=json_decode(json_encode($g),true);completeMechanic($g,'b','accept');
checkFeedback($g['players']['b']['marks']['neutral']===2&&$g['players']['b']['shield']===0,'source increase, target reduction, then shield in order');
checkFeedback(count($g['players']['a']['hand'])===3&&count($g['players']['b']['hand'])===2&&count($g['players']['c']['hand'])===2,'actual damage context available to owner, source and observers');
checkFeedback($g['players']['b']['skillMarks']['点']===2&&!$g['eventStack']&&!$g['eventFrames'],'per-point event runs twice and event frames unwind');
checkFeedback(countMechanicCards($g)===[143,143],'damage window reconnect conserves cards');

$g=fg([fs('active',[fe('damage',3,'target'),fe('draw')],1)]);
$g['players']['b']['shield']=2;$g['players']['b']['character']['skills']=[fs('before_take_damage',[fe('event_cancel')]),fs('after_damage',[fe('draw',3)])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['players']['b']['marks']['neutral']===0&&$g['players']['b']['shield']===2&&!$g['players']['b']['hand']&&count($g['players']['a']['hand'])===1,'cancel does not spend shield or emit after-damage; outer group resumes');

$g=fg([fs('active',[fe('damage',3,'target')],1)]);
$g['players']['b']['character']['skills']=[fs('before_take_damage',[fe('choose_targets')+['pool'=>'others','effects'=>[fe('event_redirect',1,'target')]]],1)];
$g['players']['c']['character']['skills']=[fs('before_take_damage',[fe('event_reduce_damage',2)])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'b','target',['target'=>'c']);$g=json_decode(json_encode($g),true);completeMechanic($g,'b','confirm');
checkFeedback($g['players']['b']['marks']['neutral']===0&&$g['players']['c']['marks']['neutral']===1,'transfer changes recipient before damage; new recipient gets own window');
checkFeedback($g['players']['a']['marks']['neutral']===0&&!$g['eventFrames'],'transfer does not recreate source bonuses or leak frames');

$g=fg([fs('active',[fe('damage',2,'target')],1),fs('after_deal_damage',[fe('draw',3)])]);
$g['players']['c']['character']['skills']=[fs('any_before_damage',[fe('event_source')],1),fs('after_deal_damage',[fe('draw','event_amount')])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback(!$g['players']['a']['hand']&&count($g['players']['c']['hand'])===2,'changed damage source owns subsequent dealt-damage triggers');

$g=fg([fs('active',[fe('damage',4,'target'),fe('draw')],1)]);
$g['players']['b']['character']['skills']=[fs('dying',[fe('heal',2)],1,0,['optional'=>true,'limitScope'=>'game']),fs('after_damage',[fe('draw')])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['pending']['player']==='b'&&$g['players']['b']['alive']&&callEngine('hp',$g,'b')===0&&!$g['players']['a']['hand'],'dying skill offered before death and outer draw');
$g=json_decode(json_encode($g),true);completeMechanic($g,'b','accept');
checkFeedback($g['players']['b']['alive']&&callEngine('hp',$g,'b')===2&&count($g['players']['b']['hand'])===1&&count($g['players']['a']['hand'])===1,'dying rescue finishes before damage aftermath');

$g=fg([fs('active',[fe('damage',4,'target')],1)]);
$g['players']['b']['character']['skills']=[fs('death',[fe('choose_targets')+['pool'=>'others','effects'=>[fe('give_hand','all','target')]]],1)];
$gift=fc($g,'b','defense');$g['players']['c']['character']['skills']=[fs('any_death',[fe('draw')])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['players']['b']['alive']&&$g['pending']['player']==='b'&&$g['players']['b']['hand'][0]['uid']===$gift,'death legacy selects before physical zone cleanup');
completeMechanic($g,'b','target',['target'=>'c']);$g=json_decode(json_encode($g),true);completeMechanic($g,'b','confirm');
checkFeedback(!$g['players']['b']['alive']&&in_array($gift,array_column($g['players']['c']['hand'],'uid'),true)&&count($g['players']['c']['hand'])===2,'death gift resolves then cleanup and global death notification');
checkFeedback(countMechanicCards($g)===[143,143],'death window conserves all physical cards');

$g=fg([fs('active',[fe('damage',2,'target')],1)]);
$g['players']['b']['character']['skills']=[fs('before_take_damage',[fe('damage',1,'event_source')],1),fs('after_damage',[fe('draw','event_amount')])];
$g['players']['a']['character']['skills'][]=fs('before_take_damage',[fe('event_reduce_damage')],0,0,['optional'=>true]);
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback($g['pending']['player']==='a'&&$g['players']['b']['marks']['neutral']===0,'nested retaliation resolves before parent damage');
$g=json_decode(json_encode($g),true);completeMechanic($g,'a','accept');
checkFeedback($g['players']['a']['marks']['neutral']===0&&$g['players']['b']['marks']['neutral']===2&&count($g['players']['b']['hand'])===2&&!$g['eventStack'],'nested event restores original amount and recipient');

rejectFeedback(function(){Rules::validateBuild(fb([fs('active',[fe('event_cancel')])]));},'event modification outside event window rejected');
$g=fg([fs('active',[fe('damage',2,'target')],1)]);
$g['players']['c']['character']['skills']=[fs('any_before_damage',[fe('event_reduce_damage')],1,0,['condition'=>['subject'=>'event_target','value'=>'hp','cmp'=>'le','amount'=>2]])];
$g['players']['b']['marks']['neutral']=2;fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback(callEngine('hp',$g,'b')===1,'structured predicate can inspect event target without changing skill owner');
$g=fg([fs('active',[fe('damage',4,'target')],1)]);
$g['players']['b']['character']['skills']=[fs('death',[fe('add_mark',1,'event_source')+['key'=>'击杀者']])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback(($g['players']['a']['skillMarks']['击杀者']??0)===1,'damage death retains its explicit source');
$g=fg([fs('active',[fe('damage',1,'target')],1)]);
$g['players']['b']['character']['skills']=[fs('after_damage',[fe('lose_health',4)]),fs('death',[fe('add_mark',1,'event_source')+['key'=>'击杀者']])];
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);
checkFeedback(!$g['players']['b']['alive']&&empty($g['players']['a']['skillMarks']['击杀者'])&&!$g['eventStack'],'nested loss of health does not inherit the outer damage killer');
checkFeedback(countMechanicCards($g)===[143,143],'nested sourceless death conserves cards');

$judge=fe('judge')+['filter'=>'black','then'=>[fe('draw')],'else'=>[],'obtain'=>true,'repeat'=>false];
$g=fg([fs('active',[$judge],1)]);
$retrial=fs('passive',[fe('passive_retrial')+['filter'=>'any','zone'=>'hand_equipment','exchange'=>false]]);
$g['players']['b']['character']['skills']=[$retrial,fs('before_take_damage',[fe('event_reduce_damage')],1,0,['optional'=>true])];
$g['players']['c']['character']['skills']=[$retrial];
$treasure=fc($g,'b','treasure');$g['players']['b']['hand'][0]['suit']='♠';callEngine('play',$g,'b',['card'=>$treasure]);fc($g,'c','defense');
fa($g,'a',['type'=>'skill','index'=>0]);completeMechanic($g,'b','replace',['card'=>$treasure]);
checkFeedback($g['pending']['kind']==='skill_offer'&&$g['pending']['player']==='b','retrial equipment damage resolves before the next retrial prompt');
checkFeedback(!empty($g['reservedJudgments'][$treasure])&&!$g['players']['a']['hand'],'current judgment remains reserved during nested damage');
$g=json_decode(json_encode($g),true);completeMechanic($g,'b','accept');
checkFeedback($g['players']['b']['marks']['neutral']===1&&$g['pending']['kind']==='mechanic_retrial'&&$g['pending']['player']==='c','next retrial begins after damage window closes');
completeMechanic($g,'c','pass');
checkFeedback(count($g['players']['a']['hand'])===2&&in_array($treasure,array_column($g['players']['a']['hand'],'uid'),true)&&!$g['reservedJudgments'],'judgment resumes once after nested responses and reconnect');
checkFeedback(countMechanicCards($g)===[143,143],'nested retrial damage conserves every physical card');
RuleConfig::configure(null);echo 'PASS '.$checks.' cumulative event assertions'.PHP_EOL;
