<?php
namespace Imaginary;

/** One server-owned vocabulary for validation, documentation and the visual editor. */
final class SkillBlocks
{
    public const VERSION = '0.4.0-alpha';

    public static function metadata(): array
    {
        $rows = [
            'draw'=>['摸普通牌',2,['self','target'],3,'从普通牌池摸指定数量。'],
            'heal'=>['回复体力',2,['self','target'],3,'回复有效体力，心坏时不能回复。'],
            'damage'=>['造成伤害',4,['self','target'],3,'直接伤害，受护盾影响；无色每点额外预算 1。'],
            'recover_mind'=>['回收已耗心象到底部',3,['self'],3,'按已耗区顺序回收至心象底。'],
            'shield'=>['获得护盾',2,['self','target'],3,'护盾上限 6，下次自己的回合开始清空。'],
            'range'=>['本回合攻击范围增加',1,['self'],3,'下次自己的回合开始清空。'],
            'attack_bonus'=>['本回合攻击伤害增加',3,['self'],3,'增伤上限 3，下次自己的回合开始清空。'],
            'steal_hand'=>['被自己随机获得手牌',3,['target'],3,'必须选择另一名角色；随机获得其手牌，保留心象原归属。'],
            'discard_hand'=>['随机弃掉手牌',2,['target'],3,'必须选择另一名角色；不公开被弃前的手牌身份。'],
            'give_hand'=>['获得自己赠送的手牌',1,['target'],3,'必须选择另一名角色；费用支付后必须有足量手牌，赠送左侧指定数量，可用 selection 指定。'],
            'draw_to'=>['将手牌补至',2,['self'],5,'只摸至目标张数，已有更多手牌时不弃牌。'],
            'extra_attacks'=>['本回合普通攻击次数增加',3,['self'],3,'允许额外使用实体或转化攻击牌，下次自己的回合开始清空。'],
            'lose_health'=>['失去体力',2,['self'],3,'扣除有效体力，忽略护盾且不触发受到伤害；正常进入濒死处理。'],
            'attack'=>['受到虚拟攻击',3,['target'],3,'必须选择范围内另一名角色，可防御；遵守颜色限制，不消耗普通攻击次数，无色每点额外预算 1。'],
            'scry'=>['查看并重排普通牌顶',1,['self'],3,'私密查看普通牌池顶，响应时将全部查看牌按指定顺序放回顶，再继续后续效果。'],
            'draw_discard'=>['获得普通弃牌堆顶牌',2,['self','target'],3,'从普通弃牌堆顶依次获得，牌堆不足则取尽。'],
            'double_defense'=>['使自己的攻击须连续防御两次',4,['self'],1,'直到下次自己的回合开始；每次成功防御只抵消一次需求，全部抵消后才触发防御成功。'],
            'sequester_hand'=>['随机暂置手牌至回合末',3,['target'],3,'必须选择他人；暂置牌本全局回合结束归还，角色出局时弃置，心象原归属保留。'],
            'discard_equipment'=>['弃置装备',3,['self','target'],2,'按装备进入区域的顺序移除，保留宝藏卸除副作用；主动自弃必须有足量装备。'],
            'damage_guard'=>['每次受到伤害减少',4,['self','target'],2,'减伤总上限 2，先于护盾计算，每次伤害都适用，下次自己的回合开始清空；不影响失去体力。'],
            'hand_lock'=>['本回合不能使用或打出手牌',6,['target'],1,'必须选择他人；本全局回合结束解除，仍可弃牌、赠牌、支付技能费用及使用已装备回避。'],
            'inspect_hand'=>['私密调查随机手牌',2,['target'],3,'仅发动者查看目标随机至多指定张手牌，确认后继续；不移动牌，不公开给其他玩家。'],
            'scry_mind'=>['重排自己的心象顶',1,['self'],3,'私密重排至多指定张心象顶牌；不抽取、不消耗，不改变心坏状态。'],
            'cycle_mind'=>['将心象顶依次移到底部',1,['self'],3,'移动至多指定张现有心象；不生成牌，原归属与总数不变。'],
            'draw_mind'=>['将心象顶加入手牌',3,['self'],2,'至多抽取指定张心象，消耗储备；抽空立即心坏，不能绕过系列限制。'],
            'recall_equipment'=>['将最早装备收回手牌',3,['self'],1,'收回自己最早装备，保留心象归属；触发失去装备，宝藏仍造成卸除伤害。'],
            'break_shield'=>['削去护盾',1,['target'],3,'必须选择他人；只移除现有护盾，不造成伤害，也不移除御伤。'],
            'equip_range'=>['持续增加攻击范围',2,['self'],2,'装备立即生效，离场失效；心坏时攻击范围仍为 0。'],
            'equip_damage'=>['每个自己回合周期首次命中攻击增伤',6,['self'],1,'每个自己的回合开始后，首次结算攻击伤害时 +1（被盾全抵消也消耗）；所有装备共用次数，反复拆装不刷新。'],
            'equip_shield'=>['自己的回合开始获得护盾',4,['self'],2,'自己的回合开始，清除旧护盾后获得指定护盾；装备入场时不送护盾。'],
            'equip_draw'=>['自己的回合开始摸普通牌',7,['self'],1,'每个自己的回合开始自动摸一张；装备入场不摸牌。'],
            'equip_distance'=>['其他角色攻击自己时距离增加',4,['self'],1,'立即增加攻击距离，不改变座次、邻近支援或事件距离；离场失效。'],
        ];
        $effects=[]; $effectMeta=[];
        foreach($rows as $op=>$r) {
            $effects[$op]=$r[0];
            $effectMeta[$op]=['targets'=>$r[2],'min'=>1,'max'=>$r[3],'description'=>$r[4],'cost'=>$r[1],'equipmentOnly'=>strpos($op,'equip_')===0];
        }
        return [
            'triggers'=>['active'=>'出牌阶段主动','turn_start'=>'自己的回合开始','turn_end'=>'自己的回合结束（弃牌后）','after_damage'=>'受到伤害后','after_attack'=>'攻击造成伤害后','on_defend'=>'防御成功后','after_play_attack'=>'使用攻击牌后（等待防御前）','after_play_event'=>'使用事件牌后','hand_empty'=>'行动及即时效果结算后失去最后手牌','convert'=>'将一张手牌转化使用 / 响应','on_targeted'=>'自己成为攻击目标时（关联攻击者）','ally_targeted'=>'距离 1 内其他同阵营角色成为攻击目标时（关联受攻者）','after_play_card'=>'主动使用实体手牌后','after_lose_equipment'=>'失去装备结算后（关联移除者）','after_equip'=>'主动装备手牌后（关联自己）'],
            'conditions'=>['always'=>'总是','wounded'=>'体力受损','hand_low'=>'手牌不超过 2 张','hand_empty'=>'没有手牌','hand_full'=>'手牌不少于有效体力','healthy'=>'有效体力未受损','played_two'=>'本全局回合已主动使用至少 2 张手牌','played_three'=>'本全局回合已主动使用至少 3 张手牌','played_hp'=>'本全局回合主动用牌数不少于有效体力','first_card'=>'本全局回合主动使用的第一张手牌','same_rank_or_suit'=>'最近两张主动使用手牌点数或花色相同','first_damage'=>'本全局回合第一次实际受到伤害后','repeat_damage'=>'本全局回合已实际受到至少两次伤害','has_equipment'=>'装备区至少有一张牌','has_true_equipment'=>'至少有一件真正装备心象','mind_low'=>'心象储备不超过 4 张','hand_same_color'=>'至少两张手牌且均为同一红黑花色颜色'],
            'effects'=>$effects,'effectMeta'=>$effectMeta,
            'equipmentSlots'=>['weapon'=>'武器','armor'=>'防具','gadget'=>'装置'],
            'equipmentEffects'=>['weapon'=>['equip_range','equip_damage'],'armor'=>['equip_shield','equip_distance'],'gadget'=>['equip_range','equip_draw']],
            'targets'=>['self'=>'自己','target'=>'指定角色 / 触发关联角色'],
            'conversions'=>['from'=>['attack'=>'攻击牌','defense'=>'防御牌','red'=>'红桃 / 方块手牌','black'=>'黑桃 / 梅花手牌','hand'=>'任意手牌'],'to'=>['attack_neutral'=>'无色攻击','defense'=>'防御']],
            'limits'=>[1,2],
        ];
    }
}
