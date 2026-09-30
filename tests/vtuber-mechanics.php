<?php
require __DIR__.'/support/vtuber.php';
use Imaginary\Engine;

// Real packaged skills, default budgets and physical cards. Reflection only supplies event fixtures.
$g=vtGame(1);$before=$g;vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);},'AI cannot connect without giving a real card');vtCheck($g===$before,'failed gift is atomic');
$gift=vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>0,'target'=>'b','selection'=>[$gift]]);
vtCheck(in_array($gift,array_column($g['players']['b']['hand'],'uid'),true)&&count($g['players']['a']['hand'])===2&&$g['players']['b']['shield']===1,'AI gift, shield and own draw');vtInvariant($g,143);

$g=vtGame(3);vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>0]);},'Akari empty hand cannot produce free draw');$memory=vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>0]);vtCheck($g['pending']['kind']==='mechanic_store','Akari chooses an actual memory');
$g=json_decode(json_encode($g),true);vtRespond($g,'confirm',['cards'=>[$memory]]);vtInvariant($g,143);vtCheck(count($g['players']['a']['hand'])===1&&$g['players']['a']['piles']['明日']['cards'][0]['uid']===$memory,'Akari stores once then draws once after reconnect');
vtCall('trigger',$g,'a','turn_start',null);vtCall('progress',$g);vtRespond($g,'confirm',['cards'=>[$memory]]);vtCheck(in_array($memory,array_column($g['players']['a']['hand'],'uid'),true)&&!$g['players']['a']['piles']['明日']['cards'],'Akari retrieves the same physical card');vtInvariant($g,143);

$g=vtGame(6);$mine=vtCard($g,'a');$theirs=vtCard($g,'b');$g['players']['a']['hand'][0]['rank']=13;$g['players']['b']['hand'][0]['rank']=1;vtAct($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);vtRespond($g,'card',['card'=>$mine]);
vtCheck(!isset(Engine::view($g,'c')['pending']['first']),'pindian commitment stays private');$g=json_decode(json_encode($g),true);vtRespond($g,'card',['card'=>$theirs]);vtCheck($g['pending']['kind']==='attack','Hinata winning pindian creates attack response');vtRespond($g,'damage');vtSettle($g);vtCheck($g['players']['b']['marks']['neutral']===1&&count($g['players']['a']['hand'])===1,'Hinata win attacks and draws');vtInvariant($g,143);

$g=vtGame(7);$g['players']['a']['skillMarks']['旅程']=2;$fee=vtCard($g,'a');$gift=vtCard($g,'a');$g['players']['b']['marks']['neutral']=2;
vtAct($g,'a',['type'=>'skill','index'=>1,'target'=>'b','costCards'=>[$fee]]);vtCheck($g['players']['a']['skillMarks']['旅程']===0,'Azuma pays two journey marks before choosing');vtRespond($g,'1');vtSettle($g);
vtCheck(in_array($gift,array_column($g['players']['b']['hand'],'uid'),true)&&$g['players']['b']['marks']['neutral']===0&&$g['players']['b']['shield']===1,'Azuma companion route really gives and heals');vtInvariant($g,143);

foreach(['0','1'] as $choice){$g=vtGame(10);vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>1]);vtRespond($g,$choice);vtCheck(!$g['players']['a']['hand']&&$g['players']['a']['shield']===0,'Rin cannot obtain store or retrieve rewards without the material after paying');vtInvariant($g,143);}

$g=vtGame(22);$fee=vtCard($g,'a');vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>1,'target'=>'b']);},'Marine cannot sail without treasure');vtAct($g,'a',['type'=>'skill','index'=>0]);vtRespond($g,'confirm',['cards'=>[$fee]]);
vtCheck(count(Engine::view($g,'b')['players'][0]['piles']['宝藏']['cards'])===1,'Marine treasure is public');vtAct($g,'a',['type'=>'skill','index'=>1,'target'=>'b']);vtRespond($g,'confirm',['cards'=>[$fee]]);vtRespond($g,'damage');vtCheck(!$g['players']['a']['piles']['宝藏']['cards']&&$g['players']['b']['marks']['neutral']===1,'Marine retrieves then attacks');vtInvariant($g,143);

$g=vtGame(28);$one=vtCard($g,'a');vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>0]);},'Ina requires two real materials');$two=vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>0]);vtRespond($g,'confirm',['cards'=>[$one,$two]]);
vtCheck(count($g['players']['a']['piles']['绘卷']['cards'])===2&&count($g['players']['a']['hand'])===1,'Ina exchanges two cards for stored defense and one draw');
foreach([$one,$two] as $uid){$g['turn']='b';$g['phase']=$g['resumePhase']='play';$g['players']['b']['attacks']=0;$attack=vtCard($g,'b','attack_warm');vtAct($g,'b',['type'=>'play','card'=>$attack,'target'=>'a']);vtRespond($g,'defend',['card'=>$uid,'conversion'=>1]);}
vtCheck(!$g['players']['a']['piles']['绘卷']['cards']&&$g['players']['a']['marks']['neutral']===0,'Ina unlimited conversion consumes both materials without refreshing them');vtInvariant($g,143);

$g=vtGame(15);vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>1]);vtRespond($g,'1');vtCheck(count($g['players']['a']['character']['skills'])===3,'Fubuki receives real black-fox ability');
$g=json_decode(json_encode($g),true);vtAct($g,'a',['type'=>'end']);foreach(['b','c'] as $id){$g['phase']=$g['resumePhase']='play';vtAct($g,$id,['type'=>'end']);}vtCheck(Engine::view($g,'a')['me']['skills'][2]['disabled'],'Fubuki temporary stance expires at next owner turn');vtInvariant($g,143);

$g=vtGame(29);$g['players']['a']['skillMarks']['声纹']=3;$max=$g['players']['a']['maxHp'];vtCall('trigger',$g,'a','turn_start',null);vtCall('progress',$g);
vtCheck($g['players']['a']['maxHp']===$max-1&&$g['players']['a']['skillMarks']['声纹']===0&&count($g['players']['a']['character']['skills'])===3,'KAF awakens with actual max-HP cost');
$g=json_decode(json_encode($g),true);$g['players']['a']['skillMarks']['声纹']=3;$g['players']['a']['turns']++;vtCall('trigger',$g,'a','turn_start',null);vtCall('progress',$g);vtCheck($g['players']['a']['maxHp']===$max-1&&count($g['players']['a']['character']['skills'])===3,'KAF once-game awakening survives reconnect and later owner turn');vtInvariant($g,143);

$g=vtGame(27);$fee=vtCard($g,'a');vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>1]);},'Amelia extra turn requires two actual plays');
foreach(['life','mana'] as $type){$uid=vtCard($g,'a',$type);vtAct($g,'a',['type'=>'play','card'=>$uid]);vtSettle($g);}
$mind=count($g['players']['a']['mind']);vtAct($g,'a',['type'=>'skill','index'=>1,'costCards'=>[$fee]]);vtCheck($g['extraTurns']===['a']&&count($g['players']['a']['mind'])===$mind-2,'Amelia pays two minds for exactly one queued turn');
$g=json_decode(json_encode($g),true);vtAct($g,'a',['type'=>'end']);vtCheck($g['turn']==='a','Amelia additional turn runs immediately');$g['phase']=$g['resumePhase']='play';vtCard($g,'a');vtReject(function()use(&$g){vtAct($g,'a',['type'=>'skill','index'=>1]);},'Amelia cannot repeat limited time travel');vtInvariant($g,143);

foreach(['♥'=>0,'♠'=>1] as $suit=>$damage){$g=vtGame(21);$g['turn']='b';vtCard($g,'a');$attack=vtCard($g,'b','attack_warm');$g['deck'][0]['suit']=$suit;vtAct($g,'b',['type'=>'play','card'=>$attack,'target'=>'a']);vtRespond($g,'damage');vtCheck($g['pending']['kind']==='skill_offer','Pekora offers paid judgment before damage');$g=json_decode(json_encode($g),true);vtRespond($g,'accept');vtSettle($g);vtCheck($g['players']['a']['marks']['neutral']===$damage,'Pekora red cancels / black fails');vtInvariant($g,143);}

$g=vtGame(33);$one=vtCard($g,'a');$two=vtCard($g,'a');vtAct($g,'a',['type'=>'skill','index'=>0,'costCards'=>[$one,$two]]);vtCheck(count($g['players']['a']['hand'])===2&&$g['players']['a']['extraAttacks']===1,'Peanuts all-hand remix binds paid count before replacement');vtInvariant($g,143);

$g=vtGame(35);vtCard($g,'a');$held=vtCard($g,'b');vtAct($g,'a',['type'=>'skill','index'=>0,'target'=>'b']);vtCheck($g['pending']['player']==='b','Vox gives opponent control of the choice');vtRespond($g,'0');vtCheck(count($g['players']['a']['hand'])===1&&!$g['players']['b']['hand']&&$g['players']['b']['sequestered'][0]['card']['uid']===$held,'Vox keeps chooser and source distinct');vtAct($g,'a',['type'=>'end']);vtSettle($g);vtCheck(in_array($held,array_column($g['players']['b']['hand'],'uid'),true),'Vox temporary withholding returns at turn end');vtInvariant($g,143);
echo "VTuber mechanics: $vtChecks assertions passed.".PHP_EOL;
