<?php
namespace Imaginary;

use InvalidArgumentException;
use Throwable;

/** A serialized, authoritative state machine; each action is atomic even outside a DB transaction. */
final class Engine
{
    private static function check(bool $ok,string $message): void { if(!$ok) throw new InvalidArgumentException($message); }
    private static function shuffleCards(array &$cards): void
    {
        for($i=count($cards)-1;$i>0;$i--) { $j=random_int(0,$i); $swap=$cards[$i]; $cards[$i]=$cards[$j]; $cards[$j]=$swap; }
    }
    private static function log(array &$g,string $s): void
    {
        $g['log'][]=['turn'=>$g['turnNumber'],'text'=>$s];
        if(count($g['log'])>160) $g['log']=array_slice($g['log'],-160);
    }
    private static function hp(array $g,string $id): int
    {
        $p=$g['players'][$id];
        if($g['mode']==='series') $loss=$p['marks']['neutral'];
        elseif($p['color']==='neutral') $loss=$p['marks']['cool']+$p['marks']['warm'];
        else $loss=$p['marks'][$p['color']==='cool'?'warm':'cool'];
        return max(0,$p['maxHp']-$loss);
    }
    private static function broken(array $g,string $id): bool { return count($g['players'][$id]['mind'])===0; }
    private static function range(array $g,string $id): int { return self::broken($g,$id)?0:1+$g['players'][$id]['rangeBonus']; }
    private static function alive(array $g): array { return array_values(array_filter($g['order'],function($id)use($g){return $g['players'][$id]['alive'];})); }
    private static function distance(array $g,string $a,string $b): int
    {
        $alive=self::alive($g); $d=abs(array_search($a,$alive,true)-array_search($b,$alive,true)); return min($d,count($alive)-$d);
    }
    private static function target(array $g,$id,bool $allowSelf=true): string
    {
        self::check(is_string($id)&&isset($g['players'][$id])&&$g['players'][$id]['alive'],'目标不存在或已出局');
        if(!$allowSelf) self::check($id!==$g['turn'],'必须选择另一名角色'); return $id;
    }
    private static function cardIndex(array $cards,$uid): int
    {
        foreach($cards as $i=>$c) if(is_string($uid)&&$c['uid']===$uid) return $i;
        throw new InvalidArgumentException('找不到这张牌');
    }
    private static function take(array &$cards,string $uid): array { $i=self::cardIndex($cards,$uid); $c=$cards[$i]; array_splice($cards,$i,1); return $c; }
    private static function spend(array &$g,array $card): void
    {
        unset($card['readyAt']);
        if(isset($card['printedType'])) { $card['type']=$card['printedType']; unset($card['printedType']); }
        if($card['origin']==='mind'&&isset($g['players'][$card['owner']])) $g['players'][$card['owner']]['spent'][]=$card;
        else $g['discard'][]=$card;
    }
    private static function normal(array &$g): ?array
    {
        if(!$g['deck']&&$g['discard']) { $g['deck']=$g['discard']; $g['discard']=[]; self::shuffleCards($g['deck']); self::log($g,'普通弃牌重新洗入牌池。'); }
        return $g['deck']?array_shift($g['deck']):null;
    }
    private static function draw(array &$g,string $id,int $n): void
    {
        if(!$g['players'][$id]['alive']) return;
        for($i=0;$i<min(26,$n);$i++) { $c=self::normal($g); if($c===null) break; $g['players'][$id]['hand'][]=$c; }
    }
    private static function mindCost(array &$g,string $id,int $n): void
    {
        self::check(count($g['players'][$id]['mind'])>=$n,'心象牌不足');
        for($i=0;$i<$n;$i++) self::spend($g,array_shift($g['players'][$id]['mind']));
    }
    private static function tuck(array &$g,string $id,array $c): void
    {
        // A normal card becomes a mind card. Existing mind ownership survives transfer.
        if($c['origin']==='normal') { $c['origin']='mind'; $c['owner']=$id; }
        $g['players'][$id]['mind'][]=$c;
    }
    private static function mature(array $g,string $id,array $c): bool { return ($c['readyAt']??PHP_INT_MAX)<=$g['players'][$id]['turns']; }
    private static function hasEquipment(array $g,string $id,string $type): ?array
    {
        foreach($g['players'][$id]['equipment'] as $c) if($c['type']===$type&&self::mature($g,$id,$c)) return $c; return null;
    }
    private static function removeEquipment(array &$g,string $id,string $uid): array
    {
        $c=self::take($g['players'][$id]['equipment'],$uid); self::spend($g,$c);
        if($c['type']==='treasure') self::damage($g,null,$id,2,'neutral',false); return $c;
    }
    public static function create(array $players,string $mode): array
    {
        self::check(in_array($mode,['color','series'],true),'模式不支持'); self::check(count($players)>=2&&count($players)<=6,'对局需要 2～6 人');
        $g=['status'=>'playing','mode'=>$mode,'players'=>[],'order'=>[],'deck'=>Catalog::ordinary(),'discard'=>[],'turnNumber'=>0,'turn'=>'','phase'=>'draw','resumePhase'=>'draw','pending'=>null,'queue'=>[],'log'=>[],'winner'=>null,'turnSeconds'=>120,'deadline'=>time()+120,'eventCount'=>0];
        self::shuffleCards($g['deck']); $teams=[];
        foreach($players as $p) {
            self::check(is_string($p['id']??null)&&!isset($g['players'][$p['id']]),'玩家编号重复或无效');
            $b=Rules::validateBuild($p['build']); $c=$b['character']; $id=$p['id']; $mind=[];
            foreach($b['deck'] as $i=>$d) {
                $def=$d['type']==='custom'?['name'=>$d['custom']['name'],'kind'=>'event','tags'=>[],'description'=>'限定系列：'.$d['custom']['series'].'；'.Rules::describeEffects($d['custom']['effects']).'。其他系列视为 '.Catalog::cards()[$d['custom']['fallback']]['name'].'。']:Catalog::cards()[$d['type']];
                $mind[]=array_merge($def,$d,['uid'=>'m_'.$id.'_'.$i,'origin'=>'mind','owner'=>$id]);
            }
            $g['players'][$id]=['id'=>$id,'name'=>$p['name'],'character'=>$c,'bot'=>!empty($p['bot']),'color'=>$c['color'],'series'=>$c['series'],'maxHp'=>$c['hp'],'marks'=>['cool'=>0,'warm'=>0,'neutral'=>0],'alive'=>true,'flipped'=>false,'hand'=>[],'mind'=>$mind,'spent'=>[],'equipment'=>[],'delayed'=>[],'turns'=>0,'shield'=>0,'rangeBonus'=>0,'attackBonus'=>0,'attacks'=>0,'usedSkills'=>[]];
            $g['order'][]=$id; $teams[]=$mode==='series'?$c['series']:($c['color']==='neutral'?'neutral_'.$id:$c['color']);
        }
        self::check(count(array_unique($teams))>=2,'需要至少两个互为对手的颜色或系列');
        foreach($g['order'] as $id) self::draw($g,$id,5);
        self::log($g,'对局开始：固定 104 张普通牌，首轮各摸 5 张。');
        self::beginTurn($g,$g['order'][0]); return $g;
    }
    public static function finished(array $g): bool { return $g['status']==='finished'; }
    private static function victory(array &$g): void
    {
        $alive=self::alive($g); $teams=[];
        foreach($alive as $id) $teams[]=$g['mode']==='series'?$g['players'][$id]['series']:($g['players'][$id]['color']==='neutral'?'neutral_'.$id:$g['players'][$id]['color']);
        if(count(array_unique($teams))<=1) {
            $g['status']='finished'; $g['pending']=null; $g['queue']=[];
            $g['winner']=!$alive?'平局':($g['mode']==='series'?$teams[0].' 系列':(strpos($teams[0],'neutral_')===0?$g['players'][$alive[0]]['name']:['cool'=>'冷色阵营','warm'=>'暖色阵营'][$teams[0]]));
            self::log($g,'对局结束，'.($g['winner']==='平局'?'平局':$g['winner'].'胜利').'。');
        }
    }
    private static function heal(array &$g,string $id,int $n,bool $rescue=false): void
    {
        if(!$g['players'][$id]['alive']||self::broken($g,$id)) return;
        $p=&$g['players'][$id];
        if($rescue&&$p['maxHp']<=0) $p['maxHp']=min($p['character']['hp'],$n);
        if($g['mode']==='series') $keys=['neutral']; elseif($p['color']==='neutral') $keys=['cool','warm']; else $keys=[$p['color']==='cool'?'warm':'cool'];
        foreach($keys as $k) { $take=min($n,$p['marks'][$k]); $p['marks'][$k]-=$take; $n-=$take; }
    }
    private static function damage(array &$g,?string $source,string $target,int $n,string $color,bool $attack=false): void
    {
        if($g['status']!=='playing'||!$g['players'][$target]['alive']) return;
        if($source!==null&&self::hasEquipment($g,$source,'treasure')!==null) $n++;
        if($attack&&$source!==null) $n+=$g['players'][$source]['attackBonus'];
        $shield=min($n,$g['players'][$target]['shield']); $g['players'][$target]['shield']-=$shield; $n-=$shield;
        if($n<=0) { self::log($g,$g['players'][$target]['name'].'的护盾抵消了伤害。'); return; }
        if($g['mode']==='series') $g['players'][$target]['marks']['neutral']+=$n;
        elseif($color==='neutral') $g['players'][$target]['maxHp']=max(0,$g['players'][$target]['maxHp']-$n);
        else $g['players'][$target]['marks'][$color]+=$n;
        self::log($g,$g['players'][$target]['name'].'受到 '.$n.' 点'.['cool'=>'冷色','warm'=>'暖色','neutral'=>'无色'][$color].'伤害。');
        if(self::hp($g,$target)<=0) {
            foreach(array_merge([$target],array_values(array_diff($g['order'],[$target]))) as $rescuer) {
                if(!$g['players'][$rescuer]['alive']) continue;
                $miracle=self::hasEquipment($g,$rescuer,'miracle');
                if($miracle!==null&&!self::broken($g,$target)) {
                    self::removeEquipment($g,$rescuer,$miracle['uid']); self::heal($g,$target,$rescuer===$target?2:1,true);
                    self::log($g,$g['players'][$rescuer]['name'].'的奇迹救援了'.$g['players'][$target]['name'].'。');
                    if(self::hp($g,$target)>0) break;
                }
            }
            $p=&$g['players'][$target];
            if(self::hp($g,$target)<=0&&$g['mode']==='color'&&$p['maxHp']>0&&!$p['flipped']&&$p['character']['flipColor']!==null) {
                $old=$p['color']; $p['color']=$p['character']['flipColor']; $p['flipped']=true;
                $opponents=0; foreach(self::alive($g) as $id) if($g['players'][$id]['color']!==$old) $opponents++;
                foreach(self::alive($g) as $id) if($g['players'][$id]['color']===$old) self::draw($g,$id,$opponents);
                self::log($g,$p['name'].'翻面为'.($p['color']==='cool'?'冷色':'暖色').'；保留所有伤害指示物。');
            }
            if(self::hp($g,$target)<=0) {
                $p['alive']=false; self::log($g,$p['name'].'出局。');
                foreach(['hand','equipment','delayed'] as $zone) { $cards=$p[$zone]; $p[$zone]=[]; foreach($cards as $c) self::spend($g,$c); }
            }
        }
        if($g['players'][$target]['alive']) self::trigger($g,$target,'after_damage',$source);
        if($attack&&$source!==null&&$g['players'][$source]['alive']) self::trigger($g,$source,'after_attack',$target);
        self::victory($g);
    }
    private static function condition(array $g,string $id,array $s): bool
    {
        return $s['condition']==='always'||($s['condition']==='wounded'&&self::hp($g,$id)<$g['players'][$id]['maxHp'])||($s['condition']==='hand_low'&&count($g['players'][$id]['hand'])<=2);
    }
    private static function skillUsable(array $g,string $id,int $i): bool
    {
        $p=$g['players'][$id]; $s=$p['character']['skills'][$i];
        return $p['alive']&&!self::broken($g,$id)&&($p['usedSkills'][$i]??-1)!==$g['turnNumber']&&self::condition($g,$id,$s)&&count($p['hand'])>=$s['cost']['hand']&&count($p['mind'])>=$s['cost']['mind'];
    }
    private static function trigger(array &$g,string $id,string $trigger,?string $target): void
    {
        if($g['eventCount']>=96) return;
        foreach($g['players'][$id]['character']['skills'] as $i=>$s) if($s['trigger']===$trigger&&self::skillUsable($g,$id,$i)) {
            self::executeSkill($g,$id,$i,$target??$id,[]);
        }
    }
    private static function executeSkill(array &$g,string $id,int $i,string $target,array $costCards): void
    {
        self::check(self::skillUsable($g,$id,$i),'技能当前不能使用'); $s=$g['players'][$id]['character']['skills'][$i];
        self::check(!$costCards||count($costCards)===$s['cost']['hand'],'手牌费用数量错误');
        $g['players'][$id]['usedSkills'][$i]=$g['turnNumber']; $g['eventCount']++;
        for($j=0;$j<$s['cost']['hand'];$j++) {
            $uid=$costCards[$j]??$g['players'][$id]['hand'][0]['uid']; self::spend($g,self::take($g['players'][$id]['hand'],$uid));
        }
        self::mindCost($g,$id,$s['cost']['mind']);
        self::log($g,$g['players'][$id]['name'].'发动「'.$s['name'].'」。');
        self::effects($g,$id,$target,$s['effects']);
    }
    private static function effects(array &$g,string $id,string $target,array $effects): void
    {
        foreach($effects as $e) {
            if($g['status']!=='playing'||$g['eventCount']>=96) break; $g['eventCount']++;
            $to=$e['target']==='self'?$id:$target;
            if(!isset($g['players'][$to])||!$g['players'][$to]['alive']) continue; $n=$e['amount'];
            switch($e['op']) {
                case 'draw': self::draw($g,$to,$n); break;
                case 'heal': self::heal($g,$to,$n); break;
                case 'damage': self::damage($g,$id,$to,$n,$e['color']); break;
                case 'recover_mind':
                    for($j=0;$j<$n&&$g['players'][$to]['spent'];$j++) $g['players'][$to]['mind'][]=array_shift($g['players'][$to]['spent']); break;
                case 'shield': $g['players'][$to]['shield']=min(6,$g['players'][$to]['shield']+$n); break;
                case 'range': $g['players'][$to]['rangeBonus']=min(99,$g['players'][$to]['rangeBonus']+$n); break;
                case 'attack_bonus': $g['players'][$to]['attackBonus']=min(3,$g['players'][$to]['attackBonus']+$n); break;
            }
        }
    }
    private static function beginTurn(array &$g,string $id): void
    {
        if($g['status']!=='playing') return;
        $g['turn']=$id; $g['turnNumber']++; $g['actionsThisTurn']=0; $g['phase']='draw'; $g['resumePhase']='draw'; $g['deadline']=time()+$g['turnSeconds'];
        if($g['turnNumber']>300) { $g['status']='finished'; $g['winner']='平局'; $g['queue']=[]; $g['pending']=null; self::log($g,'达到 300 回合内测上限，对局以平局结束。'); return; }
        $p=&$g['players'][$id]; $p['turns']++; $p['attacks']=0; $p['rangeBonus']=0; $p['attackBonus']=0; $p['shield']=0;
        self::log($g,'第 '.$g['turnNumber'].' 回合：'.$p['name'].'。'); self::trigger($g,$id,'turn_start',$id);
        foreach($p['delayed'] as $c) $g['queue'][]=['kind'=>'judge','player'=>$id,'card'=>$c];
        self::progress($g);
    }
    private static function nextTurn(array &$g): void
    {
        if($g['status']!=='playing') return;
        $at=array_search($g['turn'],$g['order'],true); $g['queue']=[]; $g['pending']=null;
        for($i=1;$i<=count($g['order']);$i++) { $id=$g['order'][($at+$i)%count($g['order'])]; if($g['players'][$id]['alive']) { self::beginTurn($g,$id); return; } }
        self::victory($g);
    }
    private static function pending(array &$g,array $p): void
    {
        $g['pending']=$p; $g['phase']='response'; $g['deadline']=time()+min(45,$g['turnSeconds']);
    }
    private static function event(array &$g,string $source,string $target,string $effect,array $extra=[]): void
    {
        $g['queue'][]=array_merge(['kind'=>'event','source'=>$source,'player'=>$target,'effect'=>$effect],$extra);
    }
    private static function progress(array &$g): void
    {
        for($steps=0;$steps<64&&$g['status']==='playing'&&$g['pending']===null&&$g['queue'];$steps++) {
            $e=array_shift($g['queue']); $id=$e['player']; if(!$g['players'][$id]['alive']) continue;
            if($e['kind']==='event') {
                $canCounter=false; foreach($g['players'][$id]['hand'] as $c) if(self::effective($g,$id,$c)['type']==='alliance') $canCounter=true;
                $eventName=Catalog::cards()[$e['effect']]['name']??($e['effect']==='draft'?'法力之泉':($e['effect']==='delayed'?($e['card']['name']??'延时事件'):'限定心象'));
                if($id!==$g['turn']&&$canCounter) self::pending($g,['kind'=>'event','player'=>$id,'source'=>$e['source'],'prompt'=>'可使用攻守同盟取消「'.$eventName.'」对你的效果。','event'=>$e]);
                else self::applyEvent($g,$e);
            } elseif($e['kind']==='attack') self::pending($g,array_merge($e,['prompt'=>'受到攻击：打出防御或承受伤害。']));
            elseif($e['kind']==='judge') self::pending($g,array_merge($e,['prompt'=>'为「'.$e['card']['name'].'」选择判定牌来源。']));
        }
        if($g['status']==='playing'&&$g['pending']===null&&!$g['queue']) {
            if(!empty($g['draft'])) { foreach($g['draft'] as $leftover) self::spend($g,$leftover); $g['draft']=[]; }
            if(!$g['players'][$g['turn']]['alive']) { self::nextTurn($g); return; }
            $g['phase']=$g['resumePhase']; $g['deadline']=time()+$g['turnSeconds'];
        }
    }
    private static function applyEvent(array &$g,array $e): void
    {
        $id=$e['player']; $source=$e['source'];
        switch($e['effect']) {
            case 'surprise':
                if(($e['mode']??'outside')==='mind') { if($g['players'][$id]['mind']) self::mindCost($g,$id,1); }
                else {
                    $all=[]; foreach(['hand','equipment','delayed'] as $zone) foreach($g['players'][$id][$zone] as $c) $all[]=['zone'=>$zone,'card'=>$c];
                    if($all) {
                        $pick=null; foreach($all as $a) if(($e['selection'][0]??'')===$a['card']['uid']) $pick=$a;
                        if($pick===null) $pick=$all[random_int(0,count($all)-1)];
                        if($pick['zone']==='equipment') self::removeEquipment($g,$id,$pick['card']['uid']);
                        else self::spend($g,self::take($g['players'][$id][$pick['zone']],$pick['card']['uid']));
                    }
                } break;
            case 'exchange':
                $all=[]; foreach(['hand','equipment','delayed'] as $zone) foreach($g['players'][$id][$zone] as $c) $all[]=['zone'=>$zone,'card'=>$c];
                if($all) {
                    $pick=$all[random_int(0,count($all)-1)]; $c=self::take($g['players'][$id][$pick['zone']],$pick['card']['uid']); unset($c['readyAt']); $removedType=$c['type'];
                    if(isset($c['printedType'])) { $c['type']=$c['printedType']; unset($c['printedType']); }
                    if($g['players'][$source]['alive']) $g['players'][$source]['hand'][]=$c; else self::spend($g,$c);
                    if($pick['zone']==='equipment'&&$removedType==='treasure') self::damage($g,null,$id,2,'neutral');
                } break;
            case 'alliance': self::draw($g,$id,1); self::draw($g,$source,3); break;
            case 'life': self::heal($g,$id,1); break;
            case 'potential': self::pending($g,['kind'=>'potential','player'=>$id,'source'=>$source,'prompt'=>'潜能爆发：选择一种代价。']); break;
            case 'energy': self::pending($g,['kind'=>'energy','player'=>$id,'source'=>$source,'prompt'=>'能量爆发：打出防守/躲避，或受到无色伤害。']); break;
            case 'draft':
                if($g['draft']) self::pending($g,['kind'=>'draft','player'=>$id,'source'=>$source,'prompt'=>'法力之泉：选择一张展示牌加入手牌。']); break;
            case 'custom': self::effects($g,$source,$id,$e['effects']); break;
            case 'delayed':
                $duplicate=false; foreach($g['players'][$id]['delayed'] as $c) if($c['type']===$e['card']['type']) $duplicate=true;
                if(!$duplicate) $g['players'][$id]['delayed'][]=$e['card']; else self::spend($g,$e['card']); break;
        }
    }
    private static function effective(array $g,string $id,array $c): array
    {
        if($c['type']==='custom'&&$c['custom']['series']!==$g['players'][$id]['series']) {
            $type=$c['custom']['fallback']; return array_merge($c,Catalog::cards()[$type],['type'=>$type]);
        }
        return $c;
    }
    public static function act(array &$g,string $id,array $a): void
    {
        $before=$g;
        try {
            self::check($g['status']==='playing','对局已经结束'); self::check(isset($g['players'][$id])&&$g['players'][$id]['alive'],'你不能行动');
            self::check(time()<=$g['deadline'],'行动已超时，请刷新房间推进对局');
            self::check(is_string($a['type']??null),'缺少行动类型'); $g['eventCount']=0;
            foreach(['cards','costCards','selection'] as $list) if(isset($a[$list])) {
                self::check(is_array($a[$list])&&count($a[$list])<=200,'选牌列表格式无效');
                foreach($a[$list] as $uid) self::check(is_string($uid)&&strlen($uid)<=160,'牌编号格式无效');
            }
            foreach(['card','costCard','target','mode','choice'] as $str) if(isset($a[$str])) self::check(is_string($a[$str])&&strlen($a[$str])<=160,'行动参数格式无效');
            if($a['type']==='respond') { self::respond($g,$id,$a); self::progress($g); return; }
            self::check($g['turn']===$id&&$g['pending']===null,'尚未轮到你或正在等待响应');
            self::check(($g['actionsThisTurn']??0)<120||in_array($a['type'],['end','discard'],true),'本回合达到 120 次行动上限，请结束回合');
            $g['actionsThisTurn']=($g['actionsThisTurn']??0)+1;
            switch($a['type']) {
                case 'draw':
                    self::check($g['phase']==='draw','现在不是摸牌阶段'); $n=$a['mind']??0;
                    self::check(is_int($n)&&$n>=0&&$n<=2&&count($g['players'][$id]['mind'])>=$n,'心象替换数量不合法');
                    for($i=0;$i<$n;$i++) $g['players'][$id]['hand'][]=array_shift($g['players'][$id]['mind']);
                    self::draw($g,$id,2-$n); $g['phase']='play'; $g['resumePhase']='play'; break;
                case 'play': self::check($g['phase']==='play','现在不是出牌阶段'); self::play($g,$id,$a); break;
                case 'skill':
                    self::check($g['phase']==='play','现在不是出牌阶段'); $i=$a['index']??null;
                    self::check(is_int($i)&&isset($g['players'][$id]['character']['skills'][$i]),'技能编号无效');
                    self::check($g['players'][$id]['character']['skills'][$i]['trigger']==='active','只能主动发动主动技能');
                    $target=self::target($g,$a['target']??$id); self::executeSkill($g,$id,$i,$target,$a['costCards']??[]); break;
                case 'equip_use': self::check($g['phase']==='play','现在不是出牌阶段'); self::equipUse($g,$id,$a); break;
                case 'end':
                    self::check($g['phase']==='play','请先完成当前阶段');
                    if(count($g['players'][$id]['hand'])>self::hp($g,$id)) { $g['phase']='discard'; $g['resumePhase']='discard'; }
                    else self::nextTurn($g); break;
                case 'discard':
                    self::check($g['phase']==='discard','现在不是弃牌阶段'); $cards=$a['cards']??[]; $n=count($g['players'][$id]['hand'])-self::hp($g,$id);
                    self::check(is_array($cards)&&count($cards)===$n&&count(array_unique($cards))===$n,'必须弃掉 '.$n.' 张手牌');
                    foreach($cards as $uid) self::spend($g,self::take($g['players'][$id]['hand'],$uid)); self::nextTurn($g); break;
                default: throw new InvalidArgumentException('未知行动');
            }
            self::progress($g); self::victory($g);
        } catch(Throwable $e) { $g=$before; throw $e; }
    }
    private static function extraHandCost(array &$g,string $id,array $a): void
    {
        self::check(is_string($a['costCard']??null),'请选择额外弃掉的手牌'); self::spend($g,self::take($g['players'][$id]['hand'],$a['costCard']));
    }
    private static function play(array &$g,string $id,array $a): void
    {
        self::check(is_string($a['card']??null),'请选择手牌'); $card=self::take($g['players'][$id]['hand'],$a['card']); $c=self::effective($g,$id,$card); $type=$c['type'];
        $target=$a['target']??$id; $mode=$a['mode']??''; $g['resumePhase']='play';
        self::check($type!=='defense','防御只能在响应时使用');
        if(in_array($type,['punch','evade','haste','miracle','treasure','automaton'],true)) {
            foreach($g['players'][$id]['equipment'] as $old) self::check($old['type']!==$type,'同名永续牌不能重复装备');
            if($card['type']!==$type) $card['printedType']=$card['type']; $card['type']=$type; $card['readyAt']=$g['players'][$id]['turns']+1; $g['players'][$id]['equipment'][]=$card;
            self::log($g,$g['players'][$id]['name'].'装备「'.$c['name'].'」，下个自己的回合成熟。'); return;
        }
        if(in_array($type,['calamity','fortune'],true)) {
            $target=self::target($g,$target); foreach($g['players'][$target]['delayed'] as $old) self::check($old['type']!==$type,'目标已有同名延时牌');
            if($card['type']!==$type) $card['printedType']=$card['type']; $card['type']=$type; self::event($g,$id,$target,'delayed',['card'=>$card]);
            self::log($g,$g['players'][$id]['name'].'向'.$g['players'][$target]['name'].'放置「'.$c['name'].'」。'); return;
        }
        self::spend($g,$card); self::log($g,$g['players'][$id]['name'].'使用「'.$c['name'].'」。');
        if(strpos($type,'attack_')===0) {
            $color=substr($type,7); $target=self::target($g,$target);
            if($target===$id) { self::check($g['mode']==='color'&&$color!=='neutral'&&$g['players'][$id]['color']===$color,'这张攻击不能对自己回复'); self::heal($g,$id,1); return; }
            self::check($g['players'][$id]['attacks']<1,'本回合已使用攻击');
            self::check(self::distance($g,$id,$target)<=self::range($g,$id),'目标不在攻击范围内（心坏时范围为 0）');
            self::check($g['mode']==='series'||$color==='neutral'||$g['players'][$target]['color']!==$color,'有色攻击不能攻击同色角色');
            $g['players'][$id]['attacks']++; $g['queue'][]=['kind'=>'attack','player'=>$target,'source'=>$id,'color'=>$color,'amount'=>1,'punch'=>false]; return;
        }
        switch($type) {
            case 'surprise':
                $target=self::target($g,$target); self::check(in_array($mode,['','outside','mind'],true),'出其不意模式无效');
                if($mode==='mind') self::extraHandCost($g,$id,$a);
                $selection=$a['selection']??[]; self::check(count($selection)<=1,'出其不意至多指定一张牌');
                if($selection) {
                    $public=array_merge($g['players'][$target]['equipment'],$g['players'][$target]['delayed']);
                    if($target===$id) $public=array_merge($public,$g['players'][$target]['hand']);
                    self::check(in_array($selection[0],array_column($public,'uid'),true),'只能指定公开装备、延时牌或自己的手牌；他人手牌只能随机弃置');
                }
                self::event($g,$id,$target,'surprise',['mode'=>$mode,'selection'=>$a['selection']??[]]); break;
            case 'exchange':
                if($mode==='steal') { $target=self::target($g,$target,false); self::extraHandCost($g,$id,$a); self::event($g,$id,$target,'exchange'); }
                else { self::check(in_array($mode,['','cycle'],true),'你来我往模式无效'); self::draw($g,$id,1); self::pending($g,['kind'=>'tuck','player'=>$id,'prompt'=>'可将一张手牌置于自己心象最下面，或跳过。']); } break;
            case 'alliance': $target=self::target($g,$target,false); self::event($g,$id,$target,'alliance'); break;
            case 'life': foreach(self::alive($g) as $to) self::event($g,$id,$to,'life'); break;
            case 'potential': foreach(self::alive($g) as $to) if($to!==$id) self::event($g,$id,$to,'potential'); break;
            case 'mana':
                self::check(in_array($mode,['','draw','draft'],true),'法力之泉模式无效');
                if($mode==='draft') {
                    foreach($g['draft']??[] as $leftover) self::spend($g,$leftover);
                    $g['draft']=[]; $alive=self::alive($g); for($i=0;$i<count($alive);$i++) { $d=self::normal($g); if($d) $g['draft'][]=$d; }
                    $at=array_search($id,$alive,true); for($i=0;$i<count($alive);$i++) self::event($g,$id,$alive[($at+$i)%count($alive)],'draft');
                } else self::draw($g,$id,2); break;
            case 'amplify': self::mindCost($g,$id,1); $g['players'][$id]['rangeBonus']=99; $g['players'][$id]['attackBonus']=min(3,$g['players'][$id]['attackBonus']+1); break;
            case 'energy': self::mindCost($g,$id,1); foreach(self::alive($g) as $to) if($to!==$id) self::event($g,$id,$to,'energy'); break;
            case 'recover':
                if($mode==='reset') { self::check(self::broken($g,$id),'只有心坏时可以重置'); self::pending($g,['kind'=>'reset','player'=>$id,'prompt'=>'按选择的顺序将全部已耗心象重置；直接确认保留当前顺序。']); }
                else {
                    self::check(in_array($mode,['','rebuild'],true),'精神回复模式无效'); $n=count($g['players'][$id]['spent']); self::draw($g,$id,$n); $n=min($n,count($g['players'][$id]['hand']));
                    if($n>0) self::pending($g,['kind'=>'rebuild','player'=>$id,'count'=>$n,'prompt'=>'选择 '.$n.' 张手牌，按提交顺序放入心象底。']);
                } break;
            case 'custom':
                $target=self::target($g,$target); self::event($g,$id,$target,'custom',['effects'=>$c['custom']['effects']]); break;
            default: throw new InvalidArgumentException('这张卡暂不支持');
        }
    }
    private static function equipUse(array &$g,string $id,array $a): void
    {
        $i=self::cardIndex($g['players'][$id]['equipment'],$a['card']??null); $c=$g['players'][$id]['equipment'][$i];
        self::check(self::mature($g,$id,$c),'永续牌要等到下个自己的回合才可使用');
        self::check(in_array($c['type'],['punch','haste','automaton'],true),'该永续牌当前不能主动卸除');
        if($c['type']==='punch') {
            $target=self::target($g,$a['target']??null,false); self::check(!self::broken($g,$id)&&self::distance($g,$id,$target)===1,'拳击需要距离 1 且未心坏');
            self::check($g['players'][$id]['attacks']<1,'本回合已使用攻击'); $g['players'][$id]['attacks']++;
            self::removeEquipment($g,$id,$c['uid']); $g['queue'][]=['kind'=>'attack','player'=>$target,'source'=>$id,'color'=>'neutral','amount'=>1,'punch'=>true];
        } elseif($c['type']==='haste') { self::removeEquipment($g,$id,$c['uid']); self::draw($g,$id,3); }
        else { self::removeEquipment($g,$id,$c['uid']); foreach(self::alive($g) as $to) if($to!==$id) self::event($g,$id,$to,'energy'); }
    }
    private static function defended(array &$g,string $id,?string $source): void
    {
        self::log($g,$g['players'][$id]['name'].'成功防御。'); self::trigger($g,$id,'on_defend',$source);
    }
    private static function respond(array &$g,string $id,array $a): void
    {
        $p=$g['pending']; self::check(is_array($p)&&$p['player']===$id,'现在不需要你响应');
        $choice=$a['choice']??''; self::check(is_string($choice),'响应格式不正确'); $kind=$p['kind']; $g['pending']=null;
        if($kind==='event') {
            if($choice==='alliance') {
                $c=self::take($g['players'][$id]['hand'],$a['card']??''); self::check(self::effective($g,$id,$c)['type']==='alliance','需要攻守同盟');
                self::spend($g,$c); self::draw($g,$p['source'],1);
                if($p['event']['effect']==='delayed') self::spend($g,$p['event']['card']);
                self::log($g,$g['players'][$id]['name'].'用攻守同盟取消事件对自己的效果。');
            } else { self::check($choice==='accept','请选择接受事件或使用攻守同盟'); self::applyEvent($g,$p['event']); }
            return;
        }
        if(in_array($kind,['attack','energy'],true)) {
            if($choice==='defend') {
                $c=self::take($g['players'][$id]['hand'],$a['card']??''); $effective=self::effective($g,$id,$c);
                self::check(empty($p['punch']),'拳击只能被已装备且成熟的回避取消');
                self::check($effective['type']==='defense'||($kind==='energy'&&$effective['type']==='evade'),'请选择有效的防守/躲避牌');
                self::spend($g,$c); self::defended($g,$id,$p['source']); return;
            }
            if($choice==='evade') {
                $i=self::cardIndex($g['players'][$id]['equipment'],$a['card']??''); $c=$g['players'][$id]['equipment'][$i];
                self::check($c['type']==='evade'&&self::mature($g,$id,$c),'需要成熟的回避装备');
                self::removeEquipment($g,$id,$c['uid']); self::draw($g,$id,1); self::defended($g,$id,$p['source']); return;
            }
            if($choice==='haste') {
                self::check(empty($p['punch'])&&empty($p['hasteUsed'])&&self::hasEquipment($g,$id,'haste')!==null&&count($g['players'][$id]['mind'])>0,'当前不能加速判定');
                $c=array_shift($g['players'][$id]['mind']); self::spend($g,$c); self::log($g,$g['players'][$id]['name'].'的加速判定为 '.$c['rank'].'。');
                if($c['rank']>7) self::defended($g,$id,$p['source']); else { $p['hasteUsed']=true; self::pending($g,$p); } return;
            }
            self::check($choice==='damage','请选择有效防御或承受伤害');
            self::damage($g,$p['source'],$id,$p['amount']??1,$p['color']??'neutral',$kind==='attack'); return;
        }
        if($kind==='potential') {
            if($choice==='attack') {
                $c=self::take($g['players'][$id]['hand'],$a['card']??''); self::check(strpos(self::effective($g,$id,$c)['type'],'attack_')===0,'需要攻击牌'); self::spend($g,$c);
            } elseif($choice==='mind') self::mindCost($g,$id,1);
            elseif($choice==='give') {
                $gift=self::take($g['players'][$id]['hand'],$a['card']??'');
                if($g['players'][$p['source']]['alive']) $g['players'][$p['source']]['hand'][]=$gift; else self::spend($g,$gift);
            }
            else { self::check($choice==='damage','潜能爆发响应无效'); self::damage($g,$p['source'],$id,1,$g['players'][$p['source']]['color']); }
            return;
        }
        if($kind==='draft') {
            self::check($choice==='choose','请选择展示牌'); $g['players'][$id]['hand'][]=self::take($g['draft'],$a['card']??''); return;
        }
        if($kind==='tuck') {
            self::check(in_array($choice,['tuck','pass'],true),'请选择置底或跳过');
            if($choice==='tuck') self::tuck($g,$id,self::take($g['players'][$id]['hand'],$a['card']??'')); return;
        }
        if($kind==='reset'||$kind==='rebuild') {
            self::check($choice==='confirm','请确认所选牌与顺序');
            $zone=$kind==='reset'?'spent':'hand'; $n=$kind==='reset'?count($g['players'][$id]['spent']):$p['count'];
            $cards=$a['cards']??($a['selection']??array_column(array_slice($g['players'][$id][$zone],0,$n),'uid'));
            self::check(is_array($cards)&&count($cards)===$n&&count(array_unique($cards))===$n,'选牌数量或顺序不合法');
            foreach($cards as $uid) self::tuck($g,$id,self::take($g['players'][$id][$zone],$uid));
            self::log($g,$g['players'][$id]['name'].'将 '.$n.' 张牌置于心象底。'); return;
        }
        if($kind==='judge') {
            self::check(in_array($choice,['normal','mind'],true),'请选择普通牌池或心象顶进行判定');
            if($choice==='mind') { self::check(count($g['players'][$id]['mind'])>0,'心象为空'); $c=array_shift($g['players'][$id]['mind']); }
            else $c=self::normal($g);
            if($c===null) { self::log($g,'普通牌池与弃牌区为空，此次判定不成立。'); return; }
            self::spend($g,$c); self::log($g,$g['players'][$id]['name'].'为「'.$p['card']['name'].'」判定：'.$c['rank'].'。');
            $success=$p['card']['type']==='calamity'?$c['rank']>=2:$c['rank']>12;
            if($success) {
                self::spend($g,self::take($g['players'][$id]['delayed'],$p['card']['uid']));
                self::pending($g,['kind'=>$p['card']['type'],'player'=>$id,'revealed'=>$c,'prompt'=>$p['card']['type']==='calamity'?'判定成立：承受 4 点无色伤害或跳过整个回合。':'判定成立：摸三张或回复四点。']);
            }
            return;
        }
        if($kind==='calamity') {
            self::check(in_array($choice,['damage','skip'],true),'请选择受伤或跳过');
            if($choice==='skip') { self::log($g,$g['players'][$id]['name'].'跳过整个回合。'); self::nextTurn($g); }
            else self::damage($g,null,$id,4,'neutral'); return;
        }
        if($kind==='fortune') {
            self::check(in_array($choice,['draw','heal'],true),'请选择摸牌或回复');
            if($choice==='draw') self::draw($g,$id,3); else self::heal($g,$id,4); return;
        }
        throw new InvalidArgumentException('未知响应状态');
    }
    /** Candidate responses contain complete defaults; clients can additionally submit ordered cards. */
    private static function responseActions(array $g,string $id): array
    {
        $p=$g['pending']; if(!$p||$p['player']!==$id) return []; $out=[];
        $add=function(string $label,string $choice,array $extra=[])use(&$out){$out[]=['label'=>$label,'action'=>array_merge(['type'=>'respond','choice'=>$choice],$extra)];};
        switch($p['kind']) {
            case 'event':
                $add('接受事件效果','accept'); foreach($g['players'][$id]['hand'] as $c) if(self::effective($g,$id,$c)['type']==='alliance') $add('攻守同盟：取消事件','alliance',['card'=>$c['uid']]); break;
            case 'attack': case 'energy':
                $add('承受伤害','damage');
                foreach($g['players'][$id]['hand'] as $c) {
                    $type=self::effective($g,$id,$c)['type'];
                    if(empty($p['punch'])&&($type==='defense'||($p['kind']==='energy'&&$type==='evade'))) $add('打出 '.$c['name'],'defend',['card'=>$c['uid']]);
                }
                foreach($g['players'][$id]['equipment'] as $c) if($c['type']==='evade'&&self::mature($g,$id,$c)) $add('卸除回避并摸一张','evade',['card'=>$c['uid']]);
                if(empty($p['punch'])&&empty($p['hasteUsed'])&&self::hasEquipment($g,$id,'haste')&&$g['players'][$id]['mind']) $add('加速：弃心象顶牌判定 >7','haste'); break;
            case 'potential':
                $add('承受来源同色伤害','damage'); if($g['players'][$id]['mind']) $add('弃掉心象顶牌','mind');
                foreach($g['players'][$id]['hand'] as $c) {
                    if(strpos(self::effective($g,$id,$c)['type'],'attack_')===0) $add('打出 '.$c['name'],'attack',['card'=>$c['uid']]);
                    $add('交出 '.$c['name'],'give',['card'=>$c['uid']]);
                } break;
            case 'draft': foreach($g['draft'] as $c) $add('选择 '.$c['name'].' '.$c['rank'],'choose',['card'=>$c['uid']]); break;
            case 'tuck':
                $add('不置底','pass'); foreach($g['players'][$id]['hand'] as $c) $add('将 '.$c['name'].' 置于心象底','tuck',['card'=>$c['uid']]); break;
            case 'reset': $add('按当前顺序重置全部已耗心象','confirm',['cards'=>array_column($g['players'][$id]['spent'],'uid')]); break;
            case 'rebuild': $add('将手牌前 '.$p['count'].' 张依次置底','confirm',['cards'=>array_column(array_slice($g['players'][$id]['hand'],0,$p['count']),'uid')]); break;
            case 'judge': $add('从普通牌池判定','normal'); if($g['players'][$id]['mind']) $add('从心象顶判定','mind'); break;
            case 'calamity': $add('跳过本回合','skip'); $add('承受 4 点无色伤害','damage'); break;
            case 'fortune': $add('摸三张牌','draw'); $add('回复四点体力','heal'); break;
        }
        return $out;
    }
    public static function legalActions(array $g,string $id): array
    {
        if($g['status']!=='playing'||!isset($g['players'][$id])||!$g['players'][$id]['alive']) return [];
        if($g['pending']!==null) return self::responseActions($g,$id);
        if($g['turn']!==$id) return []; $p=$g['players'][$id]; $out=[];
        if($g['phase']==='draw') {
            for($i=0;$i<=min(2,count($p['mind']));$i++) $out[]=['label'=>'摸 '.(2-$i).' 张普通牌 + '.$i.' 张心象','action'=>['type'=>'draw','mind'=>$i]]; return $out;
        }
        if($g['phase']==='discard') {
            $n=count($p['hand'])-self::hp($g,$id); return [['label'=>'弃掉手牌前 '.$n.' 张并结束回合','action'=>['type'=>'discard','cards'=>array_column(array_slice($p['hand'],0,$n),'uid')]]];
        }
        $candidates=[['label'=>'结束出牌阶段','action'=>['type'=>'end']]]; $alive=self::alive($g);
        $add=function(string $label,array $a)use(&$candidates){$candidates[]=['label'=>$label,'action'=>$a];};
        foreach($p['hand'] as $card) {
            $c=self::effective($g,$id,$card); $t=$c['type']; $base=['type'=>'play','card'=>$card['uid']]; $label=$c['name'];
            $others=array_values(array_filter($p['hand'],function($h)use($card){return $h['uid']!==$card['uid'];})); $cost=$others[0]['uid']??null;
            if(in_array($t,['defense'],true)) continue;
            if(strpos($t,'attack_')===0||in_array($t,['alliance','calamity','fortune','custom'],true)) {
                foreach($alive as $to) $add($label.' → '.$g['players'][$to]['name'],array_merge($base,['target'=>$to]));
            } elseif($t==='surprise') {
                foreach($alive as $to) {
                    $add($label.'：弃随机区外牌 → '.$g['players'][$to]['name'],array_merge($base,['target'=>$to,'mode'=>'outside']));
                    foreach(array_merge($g['players'][$to]['equipment'],$g['players'][$to]['delayed']) as $visible) $add($label.'：弃 '.$visible['name'].' → '.$g['players'][$to]['name'],array_merge($base,['target'=>$to,'mode'=>'outside','selection'=>[$visible['uid']]]));
                    if($cost!==null&&$g['players'][$to]['mind']) $add($label.'：额外弃手牌，破坏心象 → '.$g['players'][$to]['name'],array_merge($base,['target'=>$to,'mode'=>'mind','costCard'=>$cost]));
                }
            } elseif($t==='exchange') {
                $add($label.'：摸一张，再可置底',array_merge($base,['mode'=>'cycle']));
                if($cost!==null) foreach($alive as $to) $add($label.'：额外弃手牌，随机获取 → '.$g['players'][$to]['name'],array_merge($base,['mode'=>'steal','target'=>$to,'costCard'=>$cost]));
            } elseif($t==='mana') {
                $add($label.'：自己摸两张',array_merge($base,['mode'=>'draw'])); $add($label.'：所有人轮选',array_merge($base,['mode'=>'draft']));
            } elseif($t==='recover') {
                $add($label.'：按已耗数摸牌后置底',array_merge($base,['mode'=>'rebuild']));
                if(self::broken($g,$id)) $add($label.'：心坏重置',array_merge($base,['mode'=>'reset']));
            } else $add($label.'：使用 / 装备',$base);
        }
        foreach($p['equipment'] as $c) {
            $base=['type'=>'equip_use','card'=>$c['uid']];
            if($c['type']==='punch') foreach($alive as $to) $add('卸除拳击 → '.$g['players'][$to]['name'],array_merge($base,['target'=>$to]));
            elseif(in_array($c['type'],['haste','automaton'],true)) $add('卸除 '.$c['name'],$base);
        }
        foreach($p['character']['skills'] as $i=>$s) if($s['trigger']==='active') {
            $hasTarget=false; foreach($s['effects'] as $e) if($e['target']==='target') $hasTarget=true;
            foreach($hasTarget?$alive:[$id] as $to) $add('技能「'.$s['name'].'」 → '.$g['players'][$to]['name'],['type'=>'skill','index'=>$i,'target'=>$to]);
        }
        // Use exactly the authoritative validators for availability. Clones never escape.
        foreach($candidates as $candidate) {
            $copy=$g; $copy['deadline']=max(time()+1,$copy['deadline']);
            try { self::act($copy,$id,$candidate['action']); $out[]=$candidate; } catch(InvalidArgumentException $e) { /* illegal candidate */ }
        }
        return $out;
    }
    public static function view(array $g,string $id): array
    {
        self::check(isset($g['players'][$id]),'仅对局参与者可查看'); $players=[];
        foreach($g['order'] as $pid) {
            $p=$g['players'][$pid]; $equipment=[];
            foreach($p['equipment'] as $c) { $c['ready']=self::mature($g,$pid,$c); $equipment[]=$c; }
            $players[]=['id'=>$pid,'name'=>$p['name'],'bot'=>$p['bot'],'character'=>$p['character'],'hp'=>self::hp($g,$pid),'maxHp'=>$p['maxHp'],'color'=>$p['color'],'series'=>$p['series'],'alive'=>$p['alive'],'flipped'=>$p['flipped'],'broken'=>self::broken($g,$pid),'marks'=>$p['marks'],'mindCount'=>count($p['mind']),'spentCount'=>count($p['spent']),'spent'=>$p['spent'],'handCount'=>count($p['hand']),'equipment'=>$equipment,'delayed'=>$p['delayed'],'shield'=>$p['shield'],'range'=>self::range($g,$pid),'attackBonus'=>$p['attackBonus']];
        }
        $p=$g['players'][$id]; $skills=[];
        foreach($p['character']['skills'] as $i=>$s) $skills[]=array_merge($s,['index'=>$i,'description'=>Rules::describeSkill($s),'available'=>self::skillUsable($g,$id,$i)]);
        $pending=null;
        if($g['pending']) {
            $raw=$g['pending']; $pending=['kind'=>$raw['kind'],'player'=>$raw['player'],'source'=>$raw['source']??null,'prompt'=>$raw['prompt'],'options'=>[]];
            if(isset($raw['revealed'])) $pending['revealed']=$raw['revealed'];
            if($raw['player']===$id) {
                foreach(self::responseActions($g,$id) as $option) $pending['options'][]=array_merge(['label'=>$option['label']],$option['action']);
                if($raw['kind']==='reset') { $pending['cards']=$p['spent']; $pending['count']=count($p['spent']); }
                elseif($raw['kind']==='rebuild') { $pending['cards']=$p['hand']; $pending['count']=$raw['count']; }
                elseif($raw['kind']==='tuck') $pending['cards']=$p['hand'];
            }
            if($raw['kind']==='draft') $pending['cards']=$g['draft'];
        }
        return ['rulesVersion'=>'0.1.0-alpha','status'=>$g['status'],'mode'=>$g['mode'],'turn'=>$g['turn'],'phase'=>$g['phase'],'turnNumber'=>$g['turnNumber'],'deadline'=>$g['deadline'],'serverTime'=>time(),'players'=>$players,
            'me'=>['id'=>$id,'hand'=>$p['hand'],'mind'=>$p['mind'],'spent'=>$p['spent'],'skills'=>$skills],
            'pending'=>$pending,'legalActions'=>self::legalActions($g,$id),'log'=>$g['log'],'winner'=>$g['winner'],'deckCount'=>count($g['deck']),'discardCount'=>count($g['discard']),'draft'=>$g['draft']??[],
            'discardRequired'=>$g['turn']===$id&&$g['phase']==='discard'?max(0,count($p['hand'])-self::hp($g,$id)):0];
    }
    private static function enemy(array $g,string $id,string $target): bool
    {
        if($id===$target) return false;
        if($g['mode']==='series') return $g['players'][$id]['series']!==$g['players'][$target]['series'];
        return $g['players'][$id]['color']==='neutral'||$g['players'][$target]['color']==='neutral'||$g['players'][$id]['color']!==$g['players'][$target]['color'];
    }
    private static function chooseAuto(array $g,string $id,bool $timeout=false): ?array
    {
        $legal=self::legalActions($g,$id); if(!$legal) return null;
        if($timeout&&$g['pending']===null) {
            if($g['phase']==='play') return ['type'=>'end']; if($g['phase']==='draw') return ['type'=>'draw','mind'=>0];
        }
        $best=null; $bestScore=-99999;
        foreach($legal as $item) {
            $a=$item['action']; $score=0;
            if($a['type']==='respond') {
                $scores=['damage'=>-10,'accept'=>1,'alliance'=>5,'defend'=>15,'evade'=>16,'haste'=>8,'attack'=>10,'mind'=>6,'give'=>2,'choose'=>4,'pass'=>1,'tuck'=>count($g['players'][$id]['mind'])<5?8:0,'confirm'=>5,'normal'=>3,'skip'=>10,'draw'=>7,'heal'=>self::hp($g,$id)<$g['players'][$id]['maxHp']-1?9:0];
                $score=$scores[$a['choice']]??0;
                if(($g['pending']['kind']??'')==='judge'&&$a['choice']==='mind') $score=2;
            } elseif($a['type']==='draw') $score=$a['mind']===(count($g['players'][$id]['mind'])>4?1:0)?10:0;
            elseif($a['type']==='end') $score=0;
            elseif($a['type']==='discard') $score=1;
            elseif($a['type']==='equip_use') $score=8;
            elseif($a['type']==='skill') $score=9;
            elseif($a['type']==='play') {
                $c=$g['players'][$id]['hand'][self::cardIndex($g['players'][$id]['hand'],$a['card'])]; $c=self::effective($g,$id,$c); $t=$c['type'];
                if(strpos($t,'attack_')===0) $score=($a['target']??$id)===$id?(self::hp($g,$id)<$g['players'][$id]['maxHp']?10:-5):12;
                elseif(in_array($t,['punch','evade','haste','miracle','treasure','automaton'],true)) $score=8;
                elseif($t==='recover') $score=($a['mode']??'')==='reset'?20:(count($g['players'][$id]['mind'])<5&&count($g['players'][$id]['spent'])>0?8:-5);
                elseif($t==='amplify') $score=$g['players'][$id]['attacks']===0&&count($g['players'][$id]['hand'])>2?5:-3;
                elseif($t==='life') $score=self::hp($g,$id)<$g['players'][$id]['maxHp']?4:-2;
                elseif($t==='mana') $score=($a['mode']??'')==='draw'?9:2;
                elseif($t==='exchange') $score=($a['mode']??'')==='cycle'?7:3;
                else $score=6;
                if($t==='fortune'&&isset($a['target'])) $score+=self::enemy($g,$id,$a['target'])?-20:3;
                if($t==='alliance'&&isset($a['target'])) $score+=self::enemy($g,$id,$a['target'])?-2:3;
                if($t==='custom') {
                    foreach($c['custom']['effects'] as $e) if($e['target']==='target') {
                        if($e['op']==='damage') $score+=self::enemy($g,$id,$a['target']??$id)?5:-20;
                        else $score+=self::enemy($g,$id,$a['target']??$id)?-10:2;
                    }
                }
            }
            if(isset($a['target'])&&$a['target']!==$id) {
                $offensive=($a['type']==='equip_use')||($a['type']==='play'&&isset($t)&&(strpos($t,'attack_')===0||in_array($t,['surprise','calamity'],true)));
                if($offensive) $score+=self::enemy($g,$id,$a['target'])?4:-30;
            }
            if($score>$bestScore) { $bestScore=$score; $best=$a; }
        }
        return $best;
    }
    public static function botStep(array &$g): bool
    {
        if($g['status']!=='playing') return false; $id=$g['pending']['player']??$g['turn'];
        if(!$g['players'][$id]['bot']) return false; $g['deadline']=max(time()+1,$g['deadline']);
        $a=self::chooseAuto($g,$id); if($a===null) return false; self::act($g,$id,$a); return true;
    }
    public static function tick(array &$g): bool
    {
        $changed=false;
        for($i=0;$i<4&&$g['status']==='playing';$i++) {
            $id=$g['pending']['player']??$g['turn'];
            if($g['players'][$id]['bot']) { if(!self::botStep($g)) break; $changed=true; }
            elseif(time()>$g['deadline']) {
                $g['deadline']=time()+$g['turnSeconds']; $a=self::chooseAuto($g,$id,true);
                if($a===null) break; self::log($g,$g['players'][$id]['name'].'超时，执行默认行动。'); self::act($g,$id,$a); $changed=true;
                // A timed-out draw moves straight to turn end; no unattended offensive play.
                if($g['status']==='playing'&&$g['turn']===$id&&$g['phase']==='play'&&!$g['pending']) self::act($g,$id,['type'=>'end']);
            } else break;
        }
        return $changed;
    }
}
