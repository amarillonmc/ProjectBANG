<?php
namespace Imaginary;

/** Composable mechanics. Continuations are data, so choices survive save/reconnect. */
trait Mechanics
{
    private static function stopResolution(array &$g): void
    {
        if(($g['pending']['kind']??'')==='scry')$g['deck']=array_merge($g['pending']['cards'],$g['deck']);
        if(($g['pending']['event']['effect']??'')==='delayed')self::spend($g,$g['pending']['event']['card']);
        foreach($g['queue'] as $event)if(($event['effect']??'')==='delayed')self::spend($g,$event['card']);
        foreach($g['draft']??[] as $card)self::spend($g,$card);
        $g['draft']=[];$g['queue']=[];$g['pending']=null;$g['reservedJudgments']=[];
        if(empty($g['resolutionStopped']))self::log($g,'达到服务器结算保险上限，结束此次效果链；已支付的费用与已完成的效果保留。');
        $g['resolutionStopped']=true;$g['eventStack']=[];$g['eventFrames']=[];
        foreach(self::alive($g) as $id) {
            if($g['status']!=='playing') break;
            if(self::hp($g,$id)<=0) self::dying($g,$id);
        }
        self::victory($g);
    }
    private static function takeUnreserved(array $g,array &$cards,bool $last=false): ?array
    {
        $indexes=array_keys($cards);if($last)$indexes=array_reverse($indexes);
        foreach($indexes as $i)if(empty($g['reservedJudgments'][$cards[$i]['uid']])) {
            $card=$cards[$i];array_splice($cards,$i,1);return $card;
        }
        return null;
    }
    private static function mechanicContinue(array &$g,string $id,string $target,array $effects,array $selection=[]): void
    {
        if($effects) array_unshift($g['queue'],['kind'=>'effects','player'=>$id,'source'=>$id,'target'=>$target,'effects'=>$effects,'selection'=>$selection]);
    }
    private static function advancedEffect(array &$g,string $id,string $target,array $e,array $remaining,array $selection): bool
    {
        $to=self::eventTarget($g,$id,$target,$e['target']);
        $kind=$e['op']; $n=$e['amount'];
        if(in_array($kind,['add_mark','remove_mark'],true)) {
            $value=$g['players'][$to]['skillMarks'][$e['key']]??0;
            $g['players'][$to]['skillMarks'][$e['key']]=max(0,min(RuleConfig::get('maxAmount'),$value+($kind==='add_mark'?$n:-$n)));
        } elseif($kind==='branch') {
            self::mechanicContinue($g,$id,$target,array_merge(self::condition($g,$id,['condition'=>$e['condition']])?$e['then']:$e['else'],$remaining),$selection); return true;
        } elseif($kind==='choose') {
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            array_unshift($g['queue'],['kind'=>'mechanic_choice','player'=>$to,'source'=>$id,'target'=>$target,'options'=>$e['options']]); return true;
        } elseif($kind==='choose_targets') {
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            $candidates=[];
            foreach(self::alive($g) as $pid) if($e['pool']==='everyone'||($pid!==$id&&($e['pool']==='others'||($e['pool']==='allies'&&!self::enemy($g,$id,$pid))||($e['pool']==='enemies'&&self::enemy($g,$id,$pid))))) $candidates[]=$pid;
            $candidates=array_values(array_filter($candidates,function($pid)use($g,$id,$e){return self::effectTargetsValid($g,$id,$pid,$e['effects']);}));
            if($g['players'][$id]['bot'])$candidates=array_values(array_filter($candidates,function($pid)use($g,$id,$e){return self::botEffectsSafe($g,$id,$pid,$e['effects']);}));
            if($candidates) array_unshift($g['queue'],['kind'=>'mechanic_targets','player'=>$id,'source'=>$id,'target'=>$target,'candidates'=>$candidates,'selected'=>[],'count'=>min($n,count($candidates)),'effects'=>$e['effects']]); return true;
        } elseif($kind==='judge') {
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            array_unshift($g['queue'],['kind'=>'mechanic_judge','player'=>$to,'source'=>$id,'target'=>$target,'definition'=>$e,'iteration'=>0]); return true;
        } elseif($kind==='pindian') {
            self::check($to!==$id,'拼点必须选择其他角色');
            self::check(!empty($g['players'][$id]['hand'])&&!empty($g['players'][$to]['hand']),'拼点双方均须有手牌');
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            array_unshift($g['queue'],['kind'=>'mechanic_pindian','player'=>$id,'source'=>$id,'target'=>$to,'stage'=>1,'definition'=>$e]); return true;
        } elseif($kind==='store_pile') {
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            if($g['players'][$to]['hand']) array_unshift($g['queue'],['kind'=>'mechanic_store','player'=>$to,'source'=>$id,'target'=>$target,'key'=>$e['key'],'visibility'=>$e['visibility'],'count'=>min($n,count($g['players'][$to]['hand']))]); return true;
        } elseif($kind==='take_pile') {
            self::mechanicContinue($g,$id,$target,$remaining,$selection);
            if(!empty($g['players'][$to]['piles'][$e['key']]['cards'])) array_unshift($g['queue'],['kind'=>'mechanic_take','player'=>$to,'source'=>$id,'target'=>$target,'key'=>$e['key'],'count'=>min($n,count($g['players'][$to]['piles'][$e['key']]['cards']))]); return true;
        } elseif($kind==='exchange_hands'||$kind==='exchange_equipment') {
            self::check($to!==$id,'交换必须选择其他角色'); $zone=$kind==='exchange_hands'?'hand':'equipment';
            $left=$g['players'][$id][$zone]; $right=$g['players'][$to][$zone];
            if($zone==='equipment') {
                foreach([[$id,$left],[$to,$right]] as $pair) foreach($pair[1] as $card) $g['equipmentLosses'][]=['player'=>$pair[0],'source'=>$id];
                // Reinstall with the recipient's own clock. Physical ownership stays unchanged.
                foreach($left as &$card) $card['readyAt']=$g['players'][$to]['turns']+self::maturity($card); unset($card);
                foreach($right as &$card) $card['readyAt']=$g['players'][$id]['turns']+self::maturity($card); unset($card);
            }
            $g['players'][$id][$zone]=$right; $g['players'][$to][$zone]=$left;
            if($zone==='equipment') foreach([[$id,$left],[$to,$right]] as $pair) foreach($pair[1] as $card) if($card['type']==='treasure') self::damage($g,null,$pair[0],2,'neutral');
        } elseif($kind==='duel') {
            self::check($to!==$id,'决斗必须选择其他角色'); self::mechanicContinue($g,$id,$target,$remaining,$selection);
            array_unshift($g['queue'],['kind'=>'mechanic_duel','player'=>$to,'source'=>$id,'target'=>$to,'other'=>$id,'amount'=>$n,'color'=>$e['color'],'rounds'=>0]); return true;
        }
        return false;
    }
    private static function mechanicProgress(array &$g,array $e): void
    {
        $id=$e['player']; $kind=$e['kind'];
        $prompts=['mechanic_choice'=>'选择一个效果分支。','mechanic_targets'=>'选择受益或受影响的角色，再确认。','mechanic_pindian'=>'选择一张手牌拼点，双方选好后同时公开。','mechanic_store'=>'选择要放到专属牌堆的手牌。','mechanic_take'=>'选择要从专属牌堆取回的牌。','mechanic_duel'=>'决斗：打出攻击牌，或承受伤害。','mechanic_judge_repeat'=>'判定成功，是否再次判定？'];
        if($kind==='mechanic_judge') {
            self::pending($g,['kind'=>'judge','player'=>$id,'source'=>$e['source'],'judgmentMode'=>'mechanic','judgmentEvent'=>$e,'prompt'=>'技能判定：选择普通牌池或自己的心象顶。']); return;
        }
        self::pending($g,$e+['prompt'=>$prompts[$kind]??'选择机制的结算方式。']);
    }
    private static function judgmentWindow(array &$g,array $card,array $completion): void
    {
        $g['reservedJudgments'][$card['uid']]=true;
        $eligible=[];
        foreach(self::alive($g) as $id)if(!self::broken($g,$id))foreach($g['players'][$id]['character']['skills'] as $i=>$s)if(self::skillEnabled($g,$id,$i)&&$s['trigger']==='passive'&&self::condition($g,$id,$s))foreach($s['effects'] as $e)if($e['op']==='passive_retrial') {
            $used=$g['players'][$id]['retrialUses'][$i]??[];
            if(($used['turn']??-1)===$g['turnNumber']&&($used['count']??0)>=RuleConfig::get('unlimitedUses'))continue;
            $eligible[]=['player'=>$id,'index'=>$i,'rule'=>$e];
        }
        self::nextRetrial($g,['remaining'=>$eligible,'revealed'=>$card,'completion'=>$completion]);
    }
    private static function nextRetrial(array &$g,array $p): void
    {
        while($g['status']==='playing'&&$p['remaining']) {
            $next=array_shift($p['remaining']);$id=$next['player'];
            if(!self::retrialAvailable($g,$id,$next['index']))continue;
            self::pending($g,array_merge($p,['kind'=>'mechanic_retrial','player'=>$id,'source'=>$id,'target'=>$id,'retrial'=>$next,'prompt'=>'判定生效前：可以替换当前判定牌，或放弃改判。'])); return;
        }
        self::finishJudgment($g,$p['revealed'],$p['completion']);
    }
    private static function resolvedCard(array &$g,array $card): array
    {
        if(($card['origin']??'normal')==='mind')return self::take($g['players'][$card['owner']]['spent'],$card['uid']);
        return self::take($g['discard'],$card['uid']);
    }
    private static function finishJudgment(array &$g,array $card,array $completion): void
    {
        unset($g['reservedJudgments'][$card['uid']]);
        if($g['status']!=='playing')return;
        $e=$completion['event'];$id=$e['player'];if(!$g['players'][$id]['alive'])return;
        if($completion['mode']==='mechanic') {
            $definition=$e['definition']; $match=self::cardFilter($card,$definition['filter']);
            self::log($g,$g['players'][$id]['name'].'判定：'.($card['suit']??'无花色').$card['rank'].'，'.($match?'符合':'不符合').'条件。');
            if($match&&$definition['obtain']) {
                $g['players'][$id]['hand'][]=self::resolvedCard($g,$card);
            }
            $effects=$match?$definition['then']:$definition['else'];
            if($match&&$definition['repeat']&&$e['iteration']+1<RuleConfig::get('unlimitedUses')) {
                $e['kind']='mechanic_judge_repeat'; $e['iteration']++; array_unshift($g['queue'],$e);
            }
            self::mechanicContinue($g,$e['source'],$e['target'],$effects); return;
        }
        if($completion['mode']==='haste') {
            $e['hasteUsed']=true;if($card['rank']>7)self::defendStep($g,$id,$e);else self::pending($g,$e);return;
        }
        self::log($g,$g['players'][$id]['name'].'为「'.$e['card']['name'].'」判定：'.$card['rank'].'。');
        $success=$e['card']['type']==='calamity'?$card['rank']>=2:$card['rank']>12;
        if($success) {
            self::spend($g,self::take($g['players'][$id]['delayed'],$e['card']['uid']));
            self::pending($g,['kind'=>$e['card']['type'],'player'=>$id,'revealed'=>$card,'prompt'=>$e['card']['type']==='calamity'?'判定成立：承受 4 点无色伤害或跳过整个回合。':'判定成立：摸三张或回复四点。']);
        }
    }
    private static function cardFilter(array $card,string $filter): bool
    {
        $suit=$card['suit']??'';
        switch($filter) {
            case 'any': return true;
            case 'red': return in_array($suit,['♥','♦'],true);
            case 'black': return in_array($suit,['♠','♣'],true);
            case 'heart': return $suit==='♥';
            case 'not_heart': return $suit!==''&&$suit!=='♥';
            case 'spade': return $suit==='♠';
            case 'club': return $suit==='♣';
            case 'diamond': return $suit==='♦';
            case 'attack': return strpos($card['type'],'attack_')===0;
            case 'defense': return in_array($card['type'],['defense','evade'],true);
            case 'basic': return (Catalog::cards()[$card['type']]['kind']??$card['kind']??'')==='basic';
            case 'event': return (Catalog::cards()[$card['type']]['kind']??$card['kind']??'')==='event';
            case 'equipment': return self::trueEquipment($card);
        }
        return false;
    }
    private static function mechanicRespond(array &$g,string $id,array $p,array $a): void
    {
        $choice=$a['choice']; $kind=$p['kind']; $source=$p['source']; $target=$p['target'];
        if($kind==='mechanic_retrial') {
            $queued=count($g['queue']);
            self::check(in_array($choice,['replace','pass'],true),'请选择改判或放弃');
            if($choice==='replace') {
                self::check(self::retrialAvailable($g,$id,$p['retrial']['index']),'此改判技能已失效或达到保险上限');
                $rule=$p['retrial']['rule'];$s=['conversion'=>['from'=>$rule['filter']==='any'?'hand':$rule['filter'],'zone'=>$rule['zone']]];
                self::check(!self::broken($g,$id),'心坏时不能改判');$cards=self::conversionCards($g,$id,$s);$card=$cards[self::cardIndex($cards,$a['card']??'')];
                $fromEquipment=!in_array($card['uid'],array_column($g['players'][$id]['hand'],'uid'),true);
                if(!$fromEquipment)self::take($g['players'][$id]['hand'],$card['uid']);
                else { self::take($g['players'][$id]['equipment'],$card['uid']);$g['equipmentLosses'][]=['player'=>$id,'source'=>$id]; }
                self::spend($g,$card);
                unset($g['reservedJudgments'][$p['revealed']['uid']]);
                $g['reservedJudgments'][$card['uid']]=true;
                if($rule['exchange'])$g['players'][$id]['hand'][]=self::resolvedCard($g,$p['revealed']);
                $p['revealed']=$card;$i=$p['retrial']['index'];$used=$g['players'][$id]['retrialUses'][$i]??[];
                $g['players'][$id]['retrialUses'][$i]=['turn'=>$g['turnNumber'],'count'=>(($used['turn']??-1)===$g['turnNumber']?($used['count']??0):0)+1];
                self::log($g,$g['players'][$id]['name'].'替换判定为 '.($card['suit']??'').$card['rank'].'。');
                if($card['type']==='treasure'&&$fromEquipment)self::damage($g,null,$id,2,'neutral');
            }
            // Removing equipment can open damage/dying windows. Complete those before
            // asking the next retrial owner, retaining the reserved judgment meanwhile.
            array_splice($g['queue'],max(0,count($g['queue'])-$queued),0,[['kind'=>'judgment_continue','player'=>$p['completion']['event']['player'],'state'=>$p]]);
        } elseif($kind==='mechanic_copy_skill') {
            self::check(ctype_digit($choice)&&isset($p['choices'][(int)$choice]),'复制技能选择无效');
            $chosen=$p['choices'][(int)$choice];$key='copy_'.substr(sha1($target.':'.($chosen['skill']['key']??$chosen['index'])),0,16);
            self::grantSkill($g,$id,$id,$key,$chosen['skill'],$p['duration']);
        } elseif($kind==='mechanic_choice') {
            if($choice==='pass') {
                foreach($p['options'] as $option)self::check(!self::effectTargetsValid($g,$source,$target,$option['effects']),'仍有可执行选项，不能跳过强制选择');
                return;
            }
            self::check(ctype_digit($choice)&&isset($p['options'][(int)$choice]),'分支选项无效');
            $effects=$p['options'][(int)$choice]['effects'];
            self::check(self::effectTargetsValid($g,$source,$target,$effects),'此分支当前不能结算');
            self::mechanicContinue($g,$source,$target,$effects);
        } elseif($kind==='mechanic_targets') {
            if($choice==='confirm') {
                self::check(count($p['selected'])>0,'至少选择一个目标');
                foreach(array_reverse($p['selected']) as $to) self::mechanicContinue($g,$source,$to,$p['effects']);
            } else {
                self::check($choice==='target'&&in_array($a['target']??null,$p['candidates'],true)&&!in_array($a['target'],$p['selected'],true)&&count($p['selected'])<$p['count'],'目标选择无效');
                $p['selected'][]=$a['target']; self::pending($g,$p);
            }
        } elseif($kind==='mechanic_judge_repeat') {
            self::check(in_array($choice,['again','pass'],true),'请选择继续或结束判定');
            if($choice==='again') { $p['kind']='mechanic_judge'; unset($p['prompt']); array_unshift($g['queue'],$p); }
        } elseif($kind==='mechanic_pindian') {
            self::check($choice==='card','请选择拼点牌'); $uid=$a['card']??'';self::cardIndex($g['players'][$id]['hand'],$uid);
            if($p['stage']===1) { $p['first']=$uid;$p['stage']=2;$p['player']=$target;self::pending($g,$p); }
            else {
                $first=self::take($g['players'][$source]['hand'],$p['first']);$second=self::take($g['players'][$target]['hand'],$uid);
                self::spend($g,$first);self::spend($g,$second);
                self::log($g,'拼点：'.$g['players'][$source]['name'].' '.$first['rank'].'，'.$g['players'][$target]['name'].' '.$second['rank'].'。');
                $win=$first['rank']>$second['rank'];
                $g['players'][$source]['lastPindianWin']=$win;
                $g['players'][$target]['lastPindianWin']=$second['rank']>$first['rank'];
                self::mechanicContinue($g,$source,$target,$win?$p['definition']['then']:$p['definition']['else']);
            }
        } elseif(in_array($kind,['mechanic_store','mechanic_take'],true)) {
            self::check($choice==='confirm','请选择要移动的牌');$uids=$a['cards']??[];
            self::check(count($uids)===$p['count']&&count(array_unique($uids))===count($uids),'专属牌堆选牌数量错误');
            if($kind==='mechanic_store') {
                $pile=$g['players'][$id]['piles'][$p['key']]??['visibility'=>$p['visibility'],'cards'=>[]];
                self::check($pile['visibility']===$p['visibility'],'同名专属牌堆不能改变公开性');
                foreach($uids as $uid)$pile['cards'][]=self::take($g['players'][$id]['hand'],$uid);
                $g['players'][$id]['piles'][$p['key']]=$pile;
            } else foreach($uids as $uid)$g['players'][$id]['hand'][]=self::take($g['players'][$id]['piles'][$p['key']]['cards'],$uid);
        } elseif($kind==='mechanic_duel') {
            if($choice==='damage') { self::damage($g,$p['other'],$id,$p['amount'],$p['color']); return; }
            self::check($choice==='attack'&&!self::handLocked($g,$id),'决斗需要打出攻击牌');
            if(isset($a['conversion'])) { [$card,$effective]=self::converted($g,$id,$a); }
            else { $card=self::take($g['players'][$id]['hand'],$a['card']??'');$effective=self::effective($g,$id,$card); }
            self::check(strpos($effective['type'],'attack_')===0,'这张牌不能响应决斗');self::spend($g,$card);
            $p['rounds']++;if($p['rounds']>=RuleConfig::get('unlimitedUses')) { self::log($g,'决斗达到服务器响应保险上限，结束此次决斗。'); return; }
            $p['player']=$p['other'];$p['other']=$id;self::pending($g,$p);
        }
    }
    private static function mechanicActions(array $g,string $id,array $p): array
    {
        $out=[];$add=function($label,$choice,$extra=[])use(&$out){$out[]=['label'=>$label,'action'=>array_merge(['type'=>'respond','choice'=>(string)$choice],$extra)];};
        switch($p['kind']) {
            case 'mechanic_copy_skill':foreach($p['choices'] as $i=>$choice)$add('获得「'.$choice['skill']['name'].'」',$i);break;
            case 'mechanic_retrial':
                $add('放弃改判','pass');$r=$p['retrial']['rule'];
                if(self::retrialAvailable($g,$id,$p['retrial']['index']))foreach(self::conversionCards($g,$id,['conversion'=>['from'=>$r['filter']==='any'?'hand':$r['filter'],'zone'=>$r['zone']]]) as $card)$add('改判：'.$card['name'].' '.($card['suit']??'').$card['rank'],'replace',['card'=>$card['uid']]);break;
            case 'mechanic_choice': foreach($p['options'] as $i=>$option) if(self::effectTargetsValid($g,$p['source'],$p['target'],$option['effects']))$add($option['label'],$i);if(!$out)$add('已无可执行选项，继续结算','pass');break;
            case 'mechanic_targets':
                if($p['selected'])$add('确认选中的 '.count($p['selected']).' 名角色','confirm');
                if(count($p['selected'])<$p['count'])foreach($p['candidates'] as $target)if(!in_array($target,$p['selected'],true))$add('选择 '.$g['players'][$target]['name'],'target',['target'=>$target]);break;
            case 'mechanic_pindian': foreach($g['players'][$id]['hand'] as $card)$add('拼点：'.$card['name'].' '.$card['rank'],'card',['card'=>$card['uid']]);break;
            case 'mechanic_store':case 'mechanic_take':
                $cards=$p['kind']==='mechanic_store'?$g['players'][$id]['hand']:$g['players'][$id]['piles'][$p['key']]['cards'];
                $add('按当前顺序移动 '.$p['count'].' 张牌','confirm',['cards'=>array_column(array_slice($cards,0,$p['count']),'uid')]);break;
            case 'mechanic_judge_repeat':$add('结束判定','pass');$add('继续判定','again');break;
            case 'mechanic_duel':
                $add('承受伤害','damage');
                if(!self::handLocked($g,$id))foreach($g['players'][$id]['hand'] as $card)if(strpos(self::effective($g,$id,$card)['type'],'attack_')===0)$add('打出 '.$card['name'],'attack',['card'=>$card['uid']]);
                foreach($g['players'][$id]['character']['skills'] as $i=>$skill)if($skill['trigger']==='convert'&&strpos($skill['conversion']['to'],'attack_')===0&&self::skillUsable($g,$id,$i))foreach(self::conversionCards($g,$id,$skill) as $card)if(self::conversionPayable($g,$id,$skill,$card['uid']))$add('转化 '.$card['name'],'attack',['card'=>$card['uid'],'conversion'=>$i]);break;
        }
        return $out;
    }
    private static function retrialAvailable(array $g,string $id,int $index): bool
    {
        $p=$g['players'][$id];$s=$p['character']['skills'][$index]??null;$used=$p['retrialUses'][$index]??[];
        return $p['alive']&&self::skillEnabled($g,$id,$index)&&!self::broken($g,$id)&&$s!==null&&self::condition($g,$id,$s)&&(($used['turn']??-1)!==$g['turnNumber']||($used['count']??0)<RuleConfig::get('unlimitedUses'));
    }
}
