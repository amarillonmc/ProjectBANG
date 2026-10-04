<?php
namespace Imaginary;

/** A reproducible, budgeted recipe assembled from the existing skill vocabulary. */
final class CreationTest
{
    public static function questions(): array
    {
        $rows=[
            ['城门将闭，身后还有陌生人。你会……',['撑住门，承担追兵的第一击。','回身迎战，替所有人争取时间。','寻找侧门，改变撤离路线。','分发工具，让大家一起守住门。'],['guard','duel','cycle','aid']],
            ['你只能带走一件遗物。你选择……',['记录未发生之事的书。','能模仿他人技艺的面具。','在绝境中亮起的灯。','能把废料变成刀刃的炉火。'],['oracle','mirror','survive','convert']],
            ['同行者耗尽了补给，你还有最后一份。',['交给最需要的人，组织下一次补给。','分出一半，自己承担风险。','交换闲置物品，让资源重新流动。','侦察前路，找到补给出现的规律。'],['aid','guard','cycle','oracle']],
            ['对手公开挑衅，要你独自赴约。',['接下挑战，用正面对抗决出结果。','赴约，但学习并利用他的手段。','把不起眼的随身物件变成武器。','先准备退路，活着回来更重要。'],['duel','mirror','convert','survive']],
            ['一次失败已经无法挽回，你更在意……',['谁还需要我保护。','从失去的资源中重新建立优势。','找到下一次命运转向的征兆。','证明我还能站起来。'],['guard','cycle','oracle','survive']],
            ['你成为临时领队，第一件事是……',['让每个人都得到能派上用场的物资。','主动击破最危险的障碍。','观察伙伴的专长，随时接替空缺。','训练大家用现有材料解决问题。'],['aid','duel','mirror','convert']],
            ['商人给你四份报酬，你选择……',['一份稳定的防护。','一次扭转残局的机会。','几件可以反复交换的物品。','一条值得追踪的未知线索。'],['guard','survive','cycle','oracle']],
            ['为了打破僵局，你愿意……',['消耗自己的手牌，与对手展开较量。','把防守材料转成进攻。','先让伙伴获得行动所需的资源。','借用对手最擅长的能力。'],['duel','convert','aid','mirror']],
            ['同伴误解了你的选择，你会……',['继续承担后果，让行动说明一切。','邀请他共同解决眼前的问题。','换一种手段，找到双方能接受的路径。','等待新的证据出现，再作决定。'],['guard','aid','cycle','oracle']],
            ['最后一座桥正在坍塌，你留下的会是……',['我曾奋力一搏的痕迹。','我为下一次归来准备的路标。','我从敌人那里学来的秘密。','我把寻常事物变成奇迹的方法。'],['duel','survive','mirror','convert']],
            ['你最想打破哪一种命运？',['弱者必须承受所有代价。','资源一旦失去就无法再利用。','未来只能听天由命。','一次失败就再无机会。'],['aid','cycle','oracle','survive']],
            ['有人要为你写传记，你希望他记住……',['我守住了约定。','我直面了挑战。','我从不只有一种面貌。','我总能找到新的用途。'],['guard','duel','mirror','convert']],
            ['如果心象有温度，你的更接近……',['冷雨：冷色，保留暖色翻面。','炉火：暖色，保留冷色翻面。','深海：冷色，始终如一。','烛光：暖色，始终如一。'],['guard','duel','oracle','aid']],
            ['力量要求你选择一种生活方式。',['稳健：更高身体，主动技能以手牌付费。','燃烧：以心象支付主动技能，保留手牌。','敏锐：适中身体，让副技能更贴近你的第二倾向。','坚韧：优先身体与受伤后的补给。'],['guard','convert','mirror','survive']],
            ['远行之前，你会把什么放在行囊最上方？',['招牌心象：尽早建立自己的打法。','防御与回避：先稳住局面。','攻击：迅速试探对手。','加速与资源：先准备后续回合。'],['mirror','guard','duel','cycle']],
            ['旅途终点，有人请你留下最后一份礼物。',['一道守护：限定心象带来护盾。','一份补给：限定心象帮助补牌。','一条归途：限定心象回收已耗心象。','一次反击：限定心象增加攻击机会。'],['guard','aid','cycle','convert']],
        ];
        $out=[];
        foreach($rows as $i=>$row) {
            $options=[]; foreach($row[1] as $j=>$text) $options[]=['text'=>$text,'value'=>$j,'affinity'=>$row[2][$j]];
            $out[]=['id'=>'q'.($i+1),'prompt'=>$row[0],'options'=>$options];
        }
        return $out;
    }

    public static function profiles(): array
    {
        return [
            'guard'=>['name'=>'守誓者','template'=>'mechanic_damage_modifier','play'=>'在伤害发生前减伤，守住身体和精神，再寻找反击机会。','weakness'=>'减伤有次数限制；多段进攻和心坏会削弱防线。'],
            'duel'=>['name'=>'破阵者','template'=>'mechanic_duel','play'=>'保存攻击牌，按技能费用发起决斗，让对手先应战。','weakness'=>'决斗也可能伤到自己；缺少攻击牌时应保留费用。'],
            'cycle'=>['name'=>'织途者','template'=>'mechanic_chosen_cycle','play'=>'把暂时用不上的手牌或装备换成新牌，弃尽原手牌还能多摸一张。','weakness'=>'换牌会失去原有防御与装备，牌多也受回合结束手牌上限约束。'],
            'oracle'=>['name'=>'观兆者','template'=>'mechanic_judgment_chain','play'=>'回合开始选择黑色判定，成功获得牌后可继续，利用额外资源展开行动。','weakness'=>'判定并不稳定；红色结果会中断连锁，心象牌不提供普通花色。'],
            'aid'=>['name'=>'同行者','template'=>'mechanic_group_draw','play'=>'以一张手牌帮助至多两名其他角色补牌，在多人局建立互助。','weakness'=>'单挑时补牌对象只有对手；要判断援助是否值得，也可不发动。'],
            'convert'=>['name'=>'铸火者','template'=>'mechanic_unbounded_red','play'=>'把红色手牌或装备转成无色攻击，把资源变成削减身体上限的压力。','weakness'=>'转化仍占攻击次数；红色是花色属性，与冷暖阵营不同。'],
            'mirror'=>['name'=>'千面者','template'=>'mechanic_copy_skill','play'=>'支付技能费用，选择复制另一角色的一项技能，在当前回合借用其优势。','weakness'=>'借来的能力回合末消失；依赖专属资源或特殊时机的技能不一定有用。'],
            'survive'=>['name'=>'归来者','template'=>'mechanic_dying_rescue','play'=>'保留整局一次的濒死自救，配合补给把危险回合转化为反击机会。','weakness'=>'普通濒死回复不能修复身体上限，心坏时人物自救技能也会失效。'],
        ];
    }

    public static function compose(array $answers): array
    {
        if(count($answers)!==16||array_keys($answers)!==range(0,15)) throw new \InvalidArgumentException('请完成全部 16 道心象问答。');
        foreach($answers as $answer) if(!is_int($answer)||$answer<0||$answer>3) throw new \InvalidArgumentException('每题请选择一个有效答案。');
        $profiles=self::profiles(); $scores=array_fill_keys(array_keys($profiles),0);
        foreach(self::questions() as $i=>$q) $scores[$q['options'][$answers[$i]]['affinity']]+=($i<12?3:1);
        $ranked=array_keys($profiles);
        usort($ranked,function($a,$b)use($scores,$profiles){return ($scores[$b]<=>$scores[$a])?: (array_search($a,array_keys($profiles),true)<=>array_search($b,array_keys($profiles),true));});
        [$primary,$secondary]=$ranked;
        $templates=[]; foreach(SkillPuzzles::all() as $row) $templates[$row['id']]=$row['skill'];
        $signature=$templates[$profiles[$primary]['template']];
        if($signature['trigger']!=='passive'&&$signature['trigger']!=='convert') $signature['limit']=1;
        if($primary==='mirror') $signature['cost']['hand']=1;
        if($primary==='convert') $signature['limit']=0;
        if($signature['trigger']==='active'&&$signature['cost']['hand']===1&&$answers[13]===1) $signature['cost']=['hand'=>0,'mind'=>1];
        $signature['name']=$profiles[$primary]['name'].' · '.$signature['name'];
        // Independent supporting skills avoid broken pile/mark dependencies.
        $supportByStyle=['guard'=>'mechanic_optional_draw','duel'=>'mechanic_pindian_branch','cycle'=>'mechanic_limited_draw','oracle'=>'mechanic_limited_draw','aid'=>'mechanic_hand_aura','convert'=>'mechanic_limited_draw','mirror'=>'mechanic_optional_draw','survive'=>'mechanic_optional_draw'];
        $supportId=$supportByStyle[$answers[13]===3?'survive':$secondary];
        $support=$templates[$supportId]; if($support['trigger']!=='passive') $support['limit']=1;
        $support['name']='余响 · '.$support['name'];
        $color=in_array($answers[12],[0,2],true)?'cool':'warm';
        $flip=$answers[12]<2?($color==='cool'?'warm':'cool'):null;
        $hp=[6,4,5,6][$answers[13]];
        $base=Catalog::presets()[0]; unset($base['id']);
        $series='心象问答'; $title=$profiles[$primary]['name'].' · '.$profiles[$secondary]['name'];
        $base['name']=$title;
        $base['character']=['id'=>'virtue_'.substr(hash('sha256',json_encode($answers)),0,16),'name'=>$profiles[$primary]['name'],'title'=>$profiles[$secondary]['name'].'的余响','series'=>$series,'color'=>$color,'hp'=>min($hp,RuleConfig::get('maxHp')),'art'=>array_search($primary,array_keys($profiles),true)%4,'flipColor'=>$flip,'skills'=>[$signature,$support]];
        $e=function($op,$n=1,$target='self'){return Catalog::effect($op,$n,$target);};
        $effects=[
            'guard'=>[$e('shield',2),$e('draw',2)], 'duel'=>[$e('extra_attacks'),$e('draw',2),$e('range')],
            'cycle'=>[$e('recover_mind',2),$e('draw',2)], 'oracle'=>[$e('scry',3),$e('draw',2)],
            'aid'=>[$e('heal',2,'target'),$e('draw',2,'target')], 'convert'=>[$e('extra_attacks'),$e('draw',2),$e('range')],
            'mirror'=>[$e('draw',2),$e('shield'),$e('range')], 'survive'=>[$e('heal',2),$e('draw',2)],
        ];
        $gift=[[$e('shield',2),$e('draw',2)],[$e('draw',3)],[$e('recover_mind',2),$e('draw',2)],[$e('extra_attacks'),$e('draw',2)]];
        $base['deck']=[];
        foreach(Catalog::mindOptions() as $r=>$options) $base['deck'][]=['rank'=>$r,'type'=>$options[($answers[$r%16]+$answers[14])%count($options)]];
        foreach([[5,$profiles[$primary]['name'].'的誓约',$effects[$primary]],[9,'旅途的馈赠',$gift[$answers[15]]]] as $custom) {
            $base['deck'][$custom[0]-1]=['rank'=>$custom[0],'type'=>'custom','custom'=>['name'=>$custom[1],'series'=>$series,'fallback'=>'mana','effects'=>$custom[2]]];
        }
        $priority=[['custom','evade','haste'],['evade','miracle','custom'],['attack_'.$color,'attack_'.($color==='cool'?'warm':'cool'),'custom'],['haste','mana','custom']][$answers[14]];
        usort($base['deck'],function($a,$b)use($priority){$aa=array_search($a['type'],$priority,true);$bb=array_search($b['type'],$priority,true);return (($aa===false?9:$aa)<=>($bb===false?9:$bb))?:($a['rank']<=>$b['rank']);});
        // Adapt to server policy without silently deleting either defining skill.
        while(Rules::budget($base)['character']>RuleConfig::get('characterBudget')&&$base['character']['hp']>3) $base['character']['hp']--;
        if(Rules::budget($base)['character']>RuleConfig::get('characterBudget')) throw new \InvalidArgumentException('当前人物预算不足以容纳这组问答构筑，请在工坊手动创作，或调整服务器预算。');
        $sources=[$profiles[$primary]['template'],$supportId];
        // Spend remaining room on a complementary engine, preserving the chosen body/fee tradeoff.
        $presets=Catalog::presets();
        $accents=[
            'guard'=>$presets[3]['character']['skills'][0], 'duel'=>$presets[1]['character']['skills'][1],
            'cycle'=>$templates['mechanic_optional_draw'], 'oracle'=>$templates['mechanic_empty_guard'],
            'aid'=>$presets[0]['character']['skills'][1], 'convert'=>$templates['mechanic_continuous_attacks'],
            'mirror'=>$presets[3]['character']['skills'][0], 'survive'=>$presets[0]['character']['skills'][1],
        ];
        $accent=$accents[$primary];
        if($accent['trigger']!=='passive') $accent['limit']=1;
        if($primary==='convert') $accent['effects'][0]['amount']=1;
        $accent['name']='志向 · '.$accent['name'];$candidate=$base;$candidate['character']['skills'][]=$accent;
        if(RuleConfig::get('maxSkills')>=3&&Rules::budget($candidate)['character']<=RuleConfig::get('characterBudget')){$base=$candidate;$sources[]=$accent['name'];}
        foreach($base['deck'] as &$card) if($card['type']==='custom') {
            while(array_sum(array_map([Rules::class,'effectCost'],$card['custom']['effects']))>RuleConfig::get('customCardBudget')) {
                $changed=false; foreach($card['custom']['effects'] as &$effect) if($effect['amount']>1){$effect['amount']--; $changed=true;break;} unset($effect);
                if(!$changed) throw new \InvalidArgumentException('当前单张心象预算不足以容纳问答构筑。');
            }
        } unset($card);
        $build=Rules::validateBuild($base);
        return ['build'=>$build,'budget'=>Rules::budget($build),'profile'=>['primary'=>$primary,'secondary'=>$secondary,'title'=>$title,'scores'=>$scores,'play'=>$profiles[$primary]['play'],'weakness'=>$profiles[$primary]['weakness'],'sources'=>$sources,'choices'=>['温度决定颜色与翻面；代价决定身体和主动技能费用。','行囊决定心象出场顺序；礼物决定第二张限定心象。','前十二题共同决定招牌技能和副技能倾向；预算有余量时加入互补的志向技能。'],'opening'=>array_map(function($c){return $c['type']==='custom'?$c['custom']['name']:Catalog::cards()[$c['type']]['name'];},array_slice($build['deck'],0,3))]];
    }
}
