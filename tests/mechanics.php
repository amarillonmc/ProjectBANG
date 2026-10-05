<?php
require __DIR__.'/feedback.php';
use Imaginary\{Engine,Rules,RuleConfig};
RuleConfig::configure(['characterBudget'=>200,'customBudget'=>200,'customCardBudget'=>100]);
function completeMechanic(array &$g,string $id,string $choice,array $extra=[]): void {fa($g,$id,array_merge(['type'=>'respond','choice'=>$choice],$extra));}
function countMechanicCards(array $g): array {
    $cards=[];foreach(['deck','discard','draft'] as $zone)$cards=array_merge($cards,$g[$zone]??[]);
    foreach($g['players'] as $p){foreach(['hand','mind','spent','equipment','delayed'] as $zone)$cards=array_merge($cards,$p[$zone]);foreach($p['piles']??[] as $pile)$cards=array_merge($cards,$pile['cards']);foreach($p['sequestered']??[] as $h)$cards[]=$h['card'];}
    if(($g['pending']['kind']??'')==='scry')$cards=array_merge($cards,$g['pending']['cards']);
    foreach($g['queue'] as $event)if(($event['effect']??'')==='delayed')$cards[]=$event['card'];
    if(($g['pending']['event']['effect']??'')==='delayed')$cards[]=$g['pending']['event']['card'];
    $uids=array_column($cards,'uid');return [count($uids),count(array_unique($uids))];
}
$choice=fe('choose')+['options'=>[['label'=>'付体力','effects'=>[fe('lose_health')]],['label'=>'付上限','effects'=>[fe('lose_max_hp')]]]];
$b=fb([fs('turn_end',[$choice])]);checkFeedback(Rules::budget(Rules::validateBuild($b))['character']<0,'least negative mandatory choice still gives honest rebate');
$g=fg([fs('active',[$choice,fe('draw',2)],1)]);fa($g,'a',['type'=>'skill','index'=>0]);
checkFeedback($g['pending']['kind']==='mechanic_choice'&&!$g['players']['a']['hand'],'choice suspends later effects');
$g=json_decode(json_encode($g),true);completeMechanic($g,'a','0');checkFeedback(count($g['players']['a']['hand'])===2&&$g['players']['a']['marks']['neutral']===1,'choice reconnect resumes once');checkFeedback(countMechanicCards($g)===[143,143],'choice card conservation');
$mark=fe('add_mark',3)+['key'=>'觉醒'];$branch=fe('branch')+['condition'=>['value'=>'mark','cmp'=>'ge','amount'=>3,'key'=>'觉醒'],'then'=>[fe('draw','mark:觉醒')],'else'=>[fe('lose_health')]];
$g=fg([fs('active',[$mark,$branch],1)]);fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback(count($g['players']['a']['hand'])===3,'mark added before branch/dynamic draw evaluated');
$g=fg([fs('active',[fe('choose_targets',2)+['pool'=>'others','effects'=>[fe('draw',2,'target')]],fe('draw')],1)]);fa($g,'a',['type'=>'skill','index'=>0]);completeMechanic($g,'a','target',['target'=>'b']);
rejectFeedback(function()use(&$g){completeMechanic($g,'a','target',['target'=>'b']);},'duplicate targets');completeMechanic($g,'a','target',['target'=>'c']);completeMechanic($g,'a','confirm');
checkFeedback(count($g['players']['b']['hand'])===2&&count($g['players']['c']['hand'])===2&&count($g['players']['a']['hand'])===1,'multi target effects and outer continuation in order');

$store=fe('store_pile',2)+['key'=>'田','visibility'=>'private'];$take=fe('take_pile','all')+['key'=>'田'];
$g=fg([fs('active',[$store],1),fs('active',[$take],1)]);$a=fc($g,'a','defense');$b=fc($g,'a','punch');fa($g,'a',['type'=>'skill','index'=>0]);
checkFeedback(count(Engine::view($g,'a')['pending']['cards'])===2&&!isset(Engine::view($g,'b')['pending']['cards']),'pile selection only visible to selector');
completeMechanic($g,'a','confirm',['cards'=>[$b,$a]]);$g=json_decode(json_encode($g),true);
checkFeedback(countMechanicCards($g)===[143,143],'pile stores physical cards without duplication');checkFeedback(Engine::view($g,'b')['players'][0]['piles']['田']['cards']===[],'private pile hidden from opponent');checkFeedback(count(Engine::view($g,'a')['players'][0]['piles']['田']['cards'])===2,'private pile owner can inspect');
fa($g,'a',['type'=>'skill','index'=>1]);completeMechanic($g,'a','confirm',['cards'=>[$a,$b]]);checkFeedback(array_column($g['players']['a']['hand'],'uid')===[$a,$b]&&countMechanicCards($g)===[143,143],'pile retrieval preserves selected order/ownership');

$pindian=fe('pindian',1,'target')+['then'=>[fe('draw',2)],'else'=>[fe('draw',1,'target')]];
$g=fg([fs('active',[$pindian],1)]);$a=fc($g,'a','recover');$b=fc($g,'b','punch');$g['players']['a']['hand'][0]['rank']=13;$g['players']['b']['hand'][0]['rank']=2;
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'a','card',['card'=>$a]);
checkFeedback(!isset(Engine::view($g,'b')['pending']['first'])&&!isset(Engine::view($g,'c')['pending']['cards']),'first pindian card stays hidden until both choose');
completeMechanic($g,'b','card',['card'=>$b]);checkFeedback(count($g['players']['a']['hand'])===2&&$g['players']['a']['lastPindianWin']&&countMechanicCards($g)===[143,143],'pindian winner and card conservation');
checkFeedback($g['players']['b']['lastPindianWin']===false,'pindian records the challenged player result too');
$g=fg([fs('active',[$pindian],1)]);$a=fc($g,'a','defense');$b=fc($g,'b','defense');$g['players']['a']['hand'][0]['rank']=$g['players']['b']['hand'][0]['rank']=5;$g['players']['b']['lastPindianWin']=true;fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'a','card',['card'=>$a]);completeMechanic($g,'b','card',['card'=>$b]);checkFeedback(!$g['players']['a']['lastPindianWin']&&count($g['players']['b']['hand'])===1,'pindian tie is not a win');
checkFeedback($g['players']['b']['lastPindianWin']===false,'tie clears both previous pindian wins');

$judge=fe('judge')+['filter'=>'black','then'=>[fe('add_mark')+['key'=>'黑']],'else'=>[fe('draw')],'repeat'=>true,'obtain'=>true];$g=fg([fs('active',[$judge],1)]);
for($i=0;$i<3;$i++)$g['deck'][$i]['suit']='♠';RuleConfig::configure(['characterBudget'=>200,'unlimitedUses'=>3]);fa($g,'a',['type'=>'skill','index'=>0]);completeMechanic($g,'a','normal');
checkFeedback($g['pending']['kind']==='mechanic_judge_repeat'&&count($g['players']['a']['hand'])===1,'successful judgment obtained before repeat choice');completeMechanic($g,'a','again');completeMechanic($g,'a','normal');completeMechanic($g,'a','again');completeMechanic($g,'a','normal');
checkFeedback($g['pending']===null&&count($g['players']['a']['hand'])===3&&$g['players']['a']['skillMarks']['黑']===3,'repeated judgment respects configurable safety limit');checkFeedback(countMechanicCards($g)===[143,143],'judgment cards conserved');
RuleConfig::configure(['characterBudget'=>200]);
$g=fg([fs('active',[fe('duel',1,'target'),fe('draw')],1)]);$a=fc($g,'a','attack_neutral');$b=fc($g,'b','attack_cool');fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'b','attack',['card'=>$b]);completeMechanic($g,'a','attack',['card'=>$a]);completeMechanic($g,'b','damage');
checkFeedback($g['players']['b']['marks']['neutral']===1&&count($g['players']['a']['hand'])===1,'duel alternates responses then resumes outer effect');checkFeedback(countMechanicCards($g)===[143,143],'duel physical cards conserved');

foreach(['exchange_hands','exchange_equipment'] as $op){$g=fg([fs('active',[fe($op,1,'target')],1)]);$a=fc($g,'a','punch');$b=fc($g,'b','evade');if($op==='exchange_equipment'){callEngine('play',$g,'a',['card'=>$a]);callEngine('play',$g,'b',['card'=>$b]);}$zone=$op==='exchange_hands'?'hand':'equipment';fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);checkFeedback($g['players']['a'][$zone][0]['uid']===$b&&$g['players']['b'][$zone][0]['uid']===$a,'swap '.$zone);checkFeedback(countMechanicCards($g)===[143,143],'swap conserves cards');}
$bad=fe('choose')+['options'=>[['label'=>'一','effects'=>[fe('draw')]],['label'=>'二','effects'=>[['op'=>'execute','amount'=>1]]]]];rejectFeedback(function()use($bad){Rules::effects([$bad]);},'nested arbitrary opcode rejected');
$bad=fe('branch')+['condition'=>'always','then'=>[],'else'=>[]];for($i=0;$i<14;$i++)$bad=fe('branch')+['condition'=>'always','then'=>[$bad],'else'=>[]];rejectFeedback(function()use($bad){Rules::effects([$bad]);},'deep tree rejected');
checkFeedback(strpos(Rules::describeSkill(fs('active',[$branch],1)),'觉醒')!==false,'structured predicates have readable text');
foreach(\Imaginary\SkillPuzzles::all() as $template)checkFeedback(count(Rules::validateBuild(fb([$template['skill']]))['character']['skills'])===1,'mechanism template valid: '.$template['id']);

$convert=fs('convert',[],0,0,['conversion'=>['from'=>'red','to'=>'attack_neutral','zone'=>'hand_equipment']]);
$g=fg([$convert]);$uid=fc($g,'a','haste');$g['players']['a']['hand'][0]['suit']='♥';fa($g,'a',['type'=>'play','card'=>$uid]);
checkFeedback((bool)array_filter(Engine::legalActions($g,'a'),function($x)use($uid){return ($x['action']['conversion']??null)===0&&($x['action']['card']??null)===$uid;}),'equipment appears as legal conversion material');
fa($g,'a',['type'=>'play','card'=>$uid,'conversion'=>0,'target'=>'b']);completeMechanic($g,'b','damage');checkFeedback(!$g['players']['a']['equipment']&&countMechanicCards($g)===[143,143],'red equipment converts once and conserves cards');
$convert['conversion']=['from'=>'hand','to'=>'attack_neutral','count'=>2,'match'=>'same_suit'];$g=fg([$convert]);$one=fc($g,'a','defense');$two=fc($g,'a','defense');$g['players']['a']['hand'][0]['suit']=$g['players']['a']['hand'][1]['suit']='♣';
fa($g,'a',['type'=>'play','card'=>$one,'cards'=>[$one,$two],'conversion'=>0,'target'=>'b']);completeMechanic($g,'b','damage');checkFeedback(!$g['players']['a']['hand']&&countMechanicCards($g)===[143,143],'two material conversion consumes both');
$g=fg([$convert]);$one=fc($g,'a','defense');$two=fc($g,'a','defense');$g['players']['a']['hand'][0]['suit']='♥';$g['players']['a']['hand'][1]['suit']='♠';$before=$g;rejectFeedback(function()use(&$g,$one,$two){fa($g,'a',['type'=>'play','card'=>$one,'cards'=>[$one,$two],'conversion'=>0,'target'=>'b']);},'mixed suits rejected');checkFeedback($g===$before,'invalid multi conversion atomic');
$convert['conversion']=['from'=>'hand','to'=>'attack_neutral','zone'=>'pile','pile'=>'资源'];$g=fg([$convert]);$one=fc($g,'a','defense');$card=array_pop($g['players']['a']['hand']);$g['players']['a']['piles']['资源']=['visibility'=>'private','cards'=>[$card]];fa($g,'a',['type'=>'play','card'=>$one,'conversion'=>0,'target'=>'b']);completeMechanic($g,'b','damage');checkFeedback(!$g['players']['a']['piles']['资源']['cards']&&countMechanicCards($g)===[143,143],'pile material conversion preserves cards');

$judge=fe('judge')+['filter'=>'red','then'=>[fe('draw')],'else'=>[fe('add_mark',1,'target')+['key'=>'失败']],'repeat'=>false,'obtain'=>true];$g=fg([fs('active',[$judge],1)]);
$g['players']['b']['character']['skills']=[fs('passive',[fe('passive_retrial')+['filter'=>'black','zone'=>'hand_equipment','exchange'=>true]])];$uid=fc($g,'b','treasure');$g['players']['b']['hand'][0]['suit']='♠';$g['deck'][0]['suit']='♥';$original=$g['deck'][0]['uid'];
fa($g,'a',['type'=>'skill','index'=>0]);completeMechanic($g,'a','normal');checkFeedback($g['pending']['kind']==='mechanic_retrial'&&$g['pending']['revealed']['uid']===$original,'judgment waits before taking success branch');
$g=json_decode(json_encode($g),true);completeMechanic($g,'b','replace',['card'=>$uid]);checkFeedback($g['players']['a']['skillMarks']['失败']===1&&!$g['players']['a']['hand'],'replacement decides actual judgment result');checkFeedback(in_array($original,array_column($g['players']['b']['hand'],'uid'),true)&&callEngine('hp',$g,'b')===4,'retrial exchanges old judgment; hand treasure does not trigger equipment penalty');checkFeedback(countMechanicCards($g)===[143,143],'retrial conservation');
$g=fg();$g['players']['b']['character']['skills']=[fs('passive',[fe('passive_retrial')+['filter'=>'any','zone'=>'hand','exchange'=>false]])];$calamity=fc($g,'a','calamity');$replacement=fc($g,'b','punch');$g['players']['b']['hand'][0]['rank']=1;fa($g,'a',['type'=>'play','card'=>$calamity,'target'=>'a']);$g['pending']=['kind'=>'judge','player'=>'a','card'=>$g['players']['a']['delayed'][0],'prompt'=>'test'];$g['phase']='response';$g['deck'][0]['rank']=13;completeMechanic($g,'a','normal');checkFeedback($g['pending']['kind']==='mechanic_retrial','ordinary delayed judgments also allow retrial');completeMechanic($g,'b','replace',['card'=>$replacement]);checkFeedback($g['pending']===null&&count($g['players']['a']['delayed'])===1,'retrial can prevent calamity from resolving');

$g=fg();$g['players']['b']['character']['skills']=[fs('on_targeted',[fe('shield')],0,0,['optional'=>true])];$hit=fc($g,'a','attack_neutral');fa($g,'a',['type'=>'play','card'=>$hit,'target'=>'b']);checkFeedback($g['pending']['kind']==='skill_offer','optional on-targeted occurs before defense');completeMechanic($g,'b','accept');checkFeedback($g['pending']['kind']==='attack'&&$g['players']['b']['shield']===1,'accept applies once before attack window');completeMechanic($g,'b','damage');checkFeedback($g['players']['b']['marks']['neutral']===0,'optional shield cancels incoming damage');
RuleConfig::configure([]);
foreach(\Imaginary\SkillPuzzles::all() as $template)checkFeedback(Rules::budget(Rules::validateBuild(fb([$template['skill']])))['character']<=18,'mechanism template fits default budget: '.$template['id']);
checkFeedback(Rules::effectCost(fe('judge')+['filter'=>'black','then'=>[],'else'=>[],'obtain'=>true,'repeat'=>true])>Rules::effectCost(fe('judge')+['filter'=>'black','then'=>[],'else'=>[],'obtain'=>false,'repeat'=>true]),'obtaining judgment cards is charged');
$g=fg([fs('convert',[],0,0,['conversion'=>['from'=>'red','to'=>'attack_neutral']]),fs('passive',[fe('passive_attacks','all')])]);
$g['players']['b']['maxHp']=20;
for($i=0;$i<5;$i++){$uid=fc($g,'a','defense');$last=count($g['players']['a']['hand'])-1;$g['players']['a']['hand'][$last]['suit']='♥';fa($g,'a',['type'=>'play','card'=>$uid,'conversion'=>0,'target'=>'b']);completeMechanic($g,'b','damage');}
checkFeedback($g['players']['a']['attacks']===5&&$g['players']['b']['marks']['neutral']===5,'unlimited conversion and continuous attacks actually permit five red attacks');
checkFeedback(countMechanicCards($g)===[143,143],'repeated conversion conserves every physical card');
$g=fg();$g['players']['b']['character']['skills']=[fs('passive',[fe('passive_no_attack_target')])];$uid=fc($g,'a','punch');fa($g,'a',['type'=>'play','card'=>$uid]);$g['players']['a']['turns']++;$before=$g;rejectFeedback(function()use(&$g,$uid){fa($g,'a',['type'=>'equip_use','card'=>$uid,'target'=>'b']);},'target immunity also rejects punch');checkFeedback($before===$g,'rejected punch does not consume equipment');
$g=fg();
foreach(['甲','乙'] as $name){$uid=fc($g,'a','defense');$last=count($g['players']['a']['hand'])-1;$card=&$g['players']['a']['hand'][$last];$card['type']='custom';$card['name']=$name;$card['custom']=['name'=>$name,'series'=>'a','kind'=>'delayed','maturityTurns'=>0,'fallback'=>'defense','effects'=>[fe('draw')]];unset($card);fa($g,'a',['type'=>'play','card'=>$uid,'target'=>'b']);}
checkFeedback(count($g['players']['b']['delayed'])===2&&countMechanicCards($g)===[143,143],'different delayed custom names coexist and conserve cards');
$g=fg([fs('active',[fe('recall_equipment',2)],1)]);
foreach(['punch','haste'] as $type){$uid=fc($g,'a',$type);fa($g,'a',['type'=>'play','card'=>$uid]);}
$uids=array_column($g['players']['a']['equipment'],'uid');fa($g,'a',['type'=>'skill','index'=>0]);
checkFeedback(!$g['players']['a']['equipment']&&array_column($g['players']['a']['hand'],'uid')===$uids,'recall uses configured amount above one and preserves order');
checkFeedback(countMechanicCards($g)===[143,143],'multi equipment recall conserves physical cards');
$g=fg();$uid=fc($g,'a','defense');$g['players']['a']['hand'][0]['type']='custom';$g['players']['a']['hand'][0]['custom']=['name'=>'增伤验证','series'=>'a','fallback'=>'defense','kind'=>'equipment','slot'=>'weapon','effects'=>[fe('equip_damage',3)]];
fa($g,'a',['type'=>'play','card'=>$uid]);$g['players']['b']['maxHp']=20;
callEngine('damage',$g,'a','b',1,'neutral',true);callEngine('damage',$g,'a','b',1,'neutral',true);
checkFeedback($g['players']['b']['marks']['neutral']===5,'equipment bonus above one applies configured amount only on first hit');
$description=Rules::describeEffects([fe('judge')+['filter'=>'black','then'=>[],'else'=>[],'obtain'=>true,'repeat'=>true]]);
checkFeedback(strpos($description,'黑色')!==false&&strpos($description,'获得判定牌')!==false&&strpos($description,'继续判定')!==false,'judgment description includes filter, obtain and repetition');
$nested=fe('choose')+['options'=>[['label'=>'夺牌','effects'=>[fe('steal_hand',1,'target')]],['label'=>'摸牌','effects'=>[fe('draw')]]]];
$g=fg([fs('active',[$nested],1)]);$uid=fc($g,'b','defense');
checkFeedback((bool)array_filter(Engine::legalActions($g,'a'),function($a){return $a['action']['type']==='skill'&&($a['action']['target']??'')==='b';}),'nested choice exposes external targets');
fa($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);completeMechanic($g,'a','0');
checkFeedback(array_column($g['players']['a']['hand'],'uid')===[$uid],'nested target resolves to chosen other player');
$g=fg([fs('active',[fe('choose_targets',2)+['pool'=>'others','effects'=>[fe('damage',1,'target')]]],1)]);$g['players']['a']['bot']=true;$g['players']['b']['series']='a';
fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback($g['pending']['candidates']===['c'],'bot target selection excludes same-series partner inside nested effects');
// While a second player may still retrial, equipment-loss draw must not recycle the live judgment.
$judge=fe('judge')+['filter'=>'red','then'=>[],'else'=>[],'obtain'=>true,'repeat'=>false];
$g=fg([fs('active',[$judge],1)]);$replacement=fc($g,'b','haste');$g['players']['b']['hand'][0]['suit']='♥';callEngine('play',$g,'b',['card'=>$replacement]);
$retrial=fs('passive',[fe('passive_retrial')+['filter'=>'any','zone'=>'hand_equipment','exchange'=>false]]);
$g['players']['b']['character']['skills']=[$retrial,fs('after_lose_equipment',[fe('draw',98)],0)];$g['players']['c']['character']['skills']=[$retrial];
$original=array_shift($g['deck']);$original['suit']='♥';$g['players']['c']['hand']=array_merge($g['players']['c']['hand'],$g['deck']);$g['deck']=[$original];
fa($g,'a',['type'=>'skill','index'=>0]);completeMechanic($g,'a','normal');completeMechanic($g,'b','replace',['card'=>$replacement]);
checkFeedback($g['pending']['player']==='c'&&array_column($g['players']['b']['hand'],'uid')===[$original['uid']],'only released old judgment can be recycled by equipment-loss draw');
$g=json_decode(json_encode($g),true);completeMechanic($g,'c','pass');
checkFeedback(array_column($g['players']['a']['hand'],'uid')===[$replacement]&&empty($g['reservedJudgments']),'final judgment is obtained once after retrial reconnect');
checkFeedback(countMechanicCards($g)===[143,143],'retrial/draw combination conserves all physical cards');
RuleConfig::configure(['maxResolutionSteps'=>16]);
$inner=fe('judge')+['filter'=>'black','then'=>[],'else'=>[],'repeat'=>true,'obtain'=>false];
$outer=fe('judge')+['filter'=>'black','then'=>[$inner],'else'=>[],'repeat'=>true,'obtain'=>false];
$g=fg([fs('active',[$outer])]);foreach($g['deck'] as &$card)$card['suit']='♠';unset($card);
fa($g,'a',['type'=>'skill','index'=>0]);
for($responses=0;$responses<30&&$g['pending'];$responses++){$g=json_decode(json_encode($g),true);completeMechanic($g,'a',$g['pending']['kind']==='judge'?'normal':'again');}
checkFeedback($responses<30&&$g['pending']===null&&!$g['queue']&&$g['phase']==='play','one resolution budget spans nested response windows and reconnects');
checkFeedback(!empty($g['resolutionStopped'])&&countMechanicCards($g)===[143,143],'resolution insurance cleans pending cards without duplication');
fa($g,'a',['type'=>'skill','index'=>0]);checkFeedback($g['pending']!==null&&empty($g['resolutionStopped']),'next deliberate action receives a fresh resolution budget');
RuleConfig::configure(null);echo 'PASS '.$checks.' cumulative mechanism assertions'.PHP_EOL;
