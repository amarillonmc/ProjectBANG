<?php
namespace Imaginary;

/** One server-owned vocabulary for validation, documentation and the visual editor. */
final class SkillBlocks
{
    public const VERSION = '0.2.0-alpha';

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
        ];
        $effects=[]; $effectMeta=[];
        foreach($rows as $op=>$r) {
            $effects[$op]=$r[0];
            $effectMeta[$op]=['targets'=>$r[2],'min'=>1,'max'=>$r[3],'description'=>$r[4],'cost'=>$r[1]];
        }
        return [
            'triggers'=>['active'=>'出牌阶段主动','turn_start'=>'自己的回合开始','turn_end'=>'自己的回合结束（弃牌后）','after_damage'=>'受到伤害后','after_attack'=>'攻击造成伤害后','on_defend'=>'防御成功后','after_play_attack'=>'使用攻击牌后（等待防御前）','after_play_event'=>'使用事件牌后','hand_empty'=>'行动及即时效果结算后失去最后手牌','convert'=>'将一张手牌转化使用 / 响应'],
            'conditions'=>['always'=>'总是','wounded'=>'体力受损','hand_low'=>'手牌不超过 2 张','hand_empty'=>'没有手牌','hand_full'=>'手牌不少于有效体力','healthy'=>'有效体力未受损'],
            'effects'=>$effects,'effectMeta'=>$effectMeta,
            'targets'=>['self'=>'自己','target'=>'指定角色 / 触发关联角色'],
            'conversions'=>['from'=>['attack'=>'攻击牌','defense'=>'防御牌','red'=>'红桃 / 方块手牌','black'=>'黑桃 / 梅花手牌','hand'=>'任意手牌'],'to'=>['attack_neutral'=>'无色攻击','defense'=>'防御']],
            'limits'=>[1,2],
        ];
    }
}
