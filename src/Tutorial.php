<?php
namespace Imaginary;

/** Private, persisted lessons. Every demonstrated move is executed by Engine::act. */
final class Tutorial
{
    public static function lessons(): array
    {
        return [
            ['title'=>'把心象握进手里','goal'=>'选择「1 张普通牌 + 1 张心象」。普通牌未知，心象按你编排的顺序抽取。','after'=>'第一张心象已经进入手牌。心象不是额外摸牌，而是替换本次普通摸牌；通常最多替换两张。'],
            ['title'=>'同色攻击，也能照顾自己','goal'=>'你是冷色，精神受损。选中「冷色攻击」，再选择对自己回复。','after'=>'精神回复了 1，身体上限保持不变，也没有消耗攻击次数。此用法只属于冷暖对抗；心坏时普通回复无效。'],
            ['title'=>'有色伤害：精神受损','goal'=>'用「冷色攻击」攻击暖色的练习对手。留意精神和身体的两个数字。','after'=>'对手承受冷色伤害，精神下降，身体上限不变。练习对手在这一课自动承受攻击。'],
            ['title'=>'无色伤害：身体变小','goal'=>'用「无色攻击」攻击对手，比较受击前后的身体上限。','after'=>'身体上限减少了 1，精神也随上限下降。普通回复只能修复精神损伤，不能补回失去的身体上限。系列对抗则把所有颜色伤害都当作精神损伤。'],
            ['title'=>'延时基本牌可以储备多张','goal'=>'你已挂着一张回避。再装备手中的回避，然后用其中一张响应练习对手的拳击。','after'=>'第二张回避成功叠放。卸除一张只消耗那一张，并摸一张普通牌；另一张继续保留。拳击默认下个自己的回合成熟，回避默认立即成熟。'],
            ['title'=>'你可以选择判定来源','goal'=>'「意外之喜」需要 K 才能成功。你的心象顶正是 K：选择心象顶判定，再选择摸三张。','after'=>'判定使用并消耗了那张 K 心象，进入已耗区，不会成为手牌。普通牌池是未知选择，心象顶是已知选择；并非所有技能判定都允许选择来源。'],
            ['title'=>'心象用尽，仍有归途','goal'=>'取出最后一张心象，观察心坏；再使用刚拿到的「精神回复」选择心坏重置，确认顺序。','after'=>'心象清空时技能停用、攻击范围为 0；把已耗心象放回后心坏解除。接下来练习结束回合与胜负结算。'],
            ['title'=>'结束回合，整理手牌','goal'=>'你有三张手牌，当前手牌上限是二。点击结束出牌阶段，再任选一张手牌弃置。','after'=>'手牌整理完成，轮到下一位角色。通常回合依次为：开始结算、摸牌、出牌、弃牌；手牌上限通常等于当前精神，技能可能改变它。'],
            ['title'=>'让一局有始有终','goal'=>'练习对手仅剩一点精神，且没有翻面与救援。用冷色攻击完成这场小对决。','after'=>'场上只剩冷色角色，冷色阵营获胜；无色角色需要独自存活。系列对抗则要求只剩同一系列。接下来试一局自由练习，或用问答创作自己的角色。'],
        ];
    }
    private static function putHand(array &$game,string $id,string $type): string
    {
        foreach($game['deck'] as $i=>$card) if($card['type']===$type) {
            array_splice($game['deck'],$i,1); $game['players'][$id]['hand'][]=$card; return $card['uid'];
        }
        throw new \RuntimeException('教学牌池缺少 '.$type);
    }
    public static function start(string $id,string $name,int $step=0,int $revision=1): array
    {
        if($step<0||$step>=count(self::lessons())) throw new \InvalidArgumentException('教学章节不存在。');
        $players=[];
        foreach([$id,'lesson_partner'] as $i=>$pid) {
            $build=Catalog::presets()[0]; $build['character']['skills']=[];
            $build['character']['hp']=6; $build['character']['color']=$i?'warm':'cool';$build['character']['flipColor']=null;
            $build['character']['art']=$i?1:0;
            $build['character']['name']=$i?'练习对手':'你的练习角色';
            $build['deck']=[];foreach(Catalog::mindOptions() as $rank=>$types)$build['deck'][]=['rank'=>$rank,'type'=>$rank===13?'recover':$types[0]];
            $players[]=['id'=>$pid,'name'=>$i?'练习对手':$name,'build'=>$build,'bot'=>false];
        }
        $g=Engine::create($players,'color');
        foreach($g['players'] as &$p){$g['deck']=array_merge($g['deck'],$p['hand']);$p['hand']=[];}unset($p);
        $g['phase']=$g['resumePhase']='play';$g['deadline']=time()+3600;
        if($step===0||$step===6) $g['phase']=$g['resumePhase']='draw';
        if($step===1){$g['players'][$id]['marks']['warm']=2;self::putHand($g,$id,'attack_cool');}
        if($step===2)self::putHand($g,$id,'attack_cool');
        if($step===3){$g['players']['lesson_partner']['marks']['cool']=1;self::putHand($g,$id,'attack_neutral');}
        if($step===4){
            self::putHand($g,$id,'evade');$card=array_pop($g['players'][$id]['hand']);$card['readyAt']=$g['players'][$id]['turns'];$g['players'][$id]['equipment'][]=$card;
            self::putHand($g,$id,'evade');
        }
        if($step===5){
            self::putHand($g,$id,'fortune');$card=array_pop($g['players'][$id]['hand']);$card['readyAt']=0;$g['players'][$id]['delayed'][]=$card;
            $mind=array_pop($g['players'][$id]['mind']);array_unshift($g['players'][$id]['mind'],$mind);
            $g['phase']='response';$g['resumePhase']='draw';
            $g['pending']=['kind'=>'judge','player'=>$id,'card'=>$card,'prompt'=>'意外之喜：选择判定牌来源，K 点成功。'];
        }
        if($step===6){
            $last=array_pop($g['players'][$id]['mind']);$g['players'][$id]['spent']=$g['players'][$id]['mind'];$g['players'][$id]['mind']=[$last];
            $g['players'][$id]['character']['skills']=[Catalog::presets()[0]['character']['skills'][0]];
        }
        if($step===7){$g['players'][$id]['marks']['warm']=4;foreach(['defense','evade','attack_cool'] as $type)self::putHand($g,$id,$type);}
        if($step===8){$g['players']['lesson_partner']['marks']['cool']=5;self::putHand($g,$id,'attack_cool');}
        $g['log']=[['turn'=>1,'text'=>'本课使用安排好的牌面和状态，可反复重试；正式对局开局每人摸五张普通牌。']];
        return ['revision'=>$revision,'step'=>$step,'stage'=>0,'done'=>false,'game'=>$g];
    }
    public static function act(array $state,string $id,array $action): array
    {
        if($state['done']) throw new \InvalidArgumentException('这一课已完成，可以进入下一课。');
        $step=$state['step'];$stage=$state['stage'];$g=$state['game'];$type=$action['type']??'';
        $valid=false;
        if($step===0||($step===6&&$stage===0))$valid=$type==='draw'&&($action['mind']??null)===1;
        elseif(in_array($step,[1,2,3,8],true))$valid=$type==='play'&&($action['target']??'')===($step===1?$id:'lesson_partner');
        elseif($step===4)$valid=$stage===0?$type==='play':($type==='respond'&&($action['choice']??'')==='evade');
        elseif($step===5)$valid=$type==='respond'&&($action['choice']??'')===($stage===0?'mind':'draw');
        elseif($step===6)$valid=$stage===1?($type==='play'&&($action['mode']??'')==='reset'):($type==='respond'&&($action['choice']??'')==='confirm');
        elseif($step===7)$valid=$stage===0?$type==='end':$type==='discard';
        if(!$valid) throw new \InvalidArgumentException('请按本课上方的目标操作；你也可以退出教学，自由进入练习。');
        $g['deadline']=time()+3600;Engine::act($g,$id,$action);
        if(in_array($step,[2,3,8],true)&&($g['pending']['player']??null)==='lesson_partner'){
            $g['deadline']=time()+3600;Engine::act($g,'lesson_partner',['type'=>'respond','choice'=>'damage']);
        }
        if($step===4&&$stage===0){
            $g['turn']='lesson_partner';$g['phase']=$g['resumePhase']='play';
            $uid=self::putHand($g,'lesson_partner','punch');$card=array_pop($g['players']['lesson_partner']['hand']);$card['readyAt']=0;$g['players']['lesson_partner']['equipment'][]=$card;
            $g['deadline']=time()+3600;Engine::act($g,'lesson_partner',['type'=>'equip_use','card'=>$uid,'target'=>$id]);
        }
        $state['stage']++;$state['revision']++;$state['game']=$g;
        $state['done']=$state['stage']>=($step===6?3:(in_array($step,[4,5,7],true)?2:1));
        return $state;
    }
    public static function view(array $state,string $id): array
    {
        $lesson=self::lessons()[$state['step']];
        $g=$state['game'];$g['deadline']=time()+3600;
        return ['code'=>'tutorial','name'=>$lesson['title'],'status'=>'playing','revision'=>$state['revision'],'game'=>Engine::view($g,$id),'tutorial'=>array_merge($lesson,['step'=>$state['step'],'stage'=>$state['stage'],'total'=>count(self::lessons()),'titles'=>array_column(self::lessons(),'title'),'done'=>$state['done']])];
    }
}
