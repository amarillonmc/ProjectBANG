<?php
namespace Imaginary;
require_once __DIR__.'/RuleConfig.php';

/** One server-owned vocabulary for validation, documentation and the visual editor. */
final class SkillBlocks
{
    public const VERSION = '0.5.0-alpha';

    public static function metadata(): array
    {
        $rows = [
            'gain_skill'=>['获得一个技能',2,['self','target'],1,'获得完整技能定义；重复获得同一标识保留原次数，不会刷新限定技。'],
            'lose_skill'=>['失去指定技能',2,['self','target'],1,'按稳定标识失去技能；槽位与计数保留，持续能力立即失效。'],
            'seal_skill'=>['暂时封锁指定技能',4,['self','target'],1,'指定技能不能发动或提供持续能力，到期后自动解除。'],
            'copy_skill'=>['复制指定角色的技能',8,['target'],1,'从目标现有、未失效的技能中选择一个，获得独立的技能实例。'],
            'event_add_damage'=>['本次伤害增加',4,['self'],98,'只修改正在结算的伤害，不另造一次伤害。'],
            'event_reduce_damage'=>['本次伤害减少',4,['self'],98,'在护盾与扣血之前减少本次伤害；减至零即取消。'],
            'event_set_damage'=>['将本次伤害改为',5,['self'],98,'替换当前伤害数值，之后仍正常结算减伤与护盾。'],
            'event_cancel'=>['取消本次伤害',6,['self'],1,'完全取消当前伤害，不扣血、不消耗护盾、不触发受伤后效果。'],
            'event_redirect'=>['将本次伤害转移给',5,['self','target'],1,'保留来源与数量，重新进入新目标的受伤前窗口；同次伤害不能回到已转移过的角色。'],
            'event_source'=>['将本次伤害来源改为',4,['self','target'],1,'修改归属，不再重复叠加原伤害加成。'],
            'choose'=>['选择效果分支',0,['self','target'],1,'由指定角色选择一个分支后结算。'],
            'branch'=>['按条件分支',0,['self'],1,'判断条件，只执行满足或不满足的一个分支。'],
            'choose_targets'=>['选择若干角色依次结算',0,['self'],98,'由发动者自由选择至多指定数量的角色，然后按选择顺序结算。'],
            'judge'=>['按花色判定',1,['self','target'],1,'从普通牌顶判定，按成功 / 失败分支结算，可获得成功牌并重复。'],
            'pindian'=>['与目标拼点',2,['target'],1,'双方秘密选择手牌，同时揭示；点数严格大于才算赢，平局算未赢。'],
            'add_mark'=>['获得标记',1,['self','target'],98,'持久化的命名标记，可被条件和动态数量读取。'],
            'remove_mark'=>['移去标记',1,['self','target'],98,'移去指定数量的已有标记。'],
            'store_pile'=>['将手牌存入专属牌堆',1,['self','target'],98,'持有者选择牌；保留实体牌归属，支持公开和私密牌堆。'],
            'take_pile'=>['从专属牌堆取回手牌',2,['self','target'],98,'持有者选择取回的牌；数量不足时取尽。'],
            'exchange_hands'=>['交换所有手牌',5,['target'],1,'双方手牌整体交换，保留心象原归属。'],
            'exchange_equipment'=>['交换所有装备',5,['target'],1,'交换装备并触发失装，重新按接收者的回合数计算成熟。'],
            'duel'=>['与目标决斗',5,['target'],3,'目标先响应，双方轮流打出攻击，首先不打出的角色受到另一方伤害。'],
            'passive_attacks'=>['持续增加攻击次数',3,['self','allies','enemies','everyone'],98,'持续修改攻击次数；不限次数用动态数量「全部」。'],
            'passive_range'=>['持续增加攻击范围',2,['self','allies','enemies','everyone'],98,'持续修改范围，失去技能或心坏时立即失效。'],
            'passive_hand'=>['持续增加手牌上限',2,['self','allies','enemies','everyone'],98,'弃牌阶段实时读取，支持光环。'],
            'passive_hand_penalty'=>['持续减少手牌上限',-2,['self'],98,'强制负面效果；手牌上限最低为零。'],
            'passive_distance_out'=>['计算与他人的距离时减少',2,['self','allies','enemies','everyone'],98,'攻击距离最低为一；不改变座次。'],
            'passive_distance_in'=>['他人计算与你的距离时增加',2,['self','allies','enemies','everyone'],98,'实时距离修正，不消耗次数。'],
            'passive_draw'=>['摸牌阶段额外摸牌',3,['self','allies','enemies','everyone'],98,'修改正常摸牌阶段数量，不影响其他摸牌效果。'],
            'passive_draw_penalty'=>['摸牌阶段少摸牌',-3,['self'],98,'强制负面效果；摸牌数最低为零。'],
            'passive_guard'=>['每次受到伤害减少',5,['self','allies','enemies','everyone'],98,'实时减伤，适用于每次伤害。'],
            'passive_no_attack_target'=>['不能成为攻击目标',6,['self','allies','enemies','everyone'],1,'配合无手牌条件可表达空城类目标限制。'],
            'passive_double_defense'=>['攻击需要额外防御',4,['self','allies','enemies','everyone'],1,'所有攻击实时增加防御需求。'],
            'passive_retrial'=>['可替换判定牌',4,['self'],1,'任意角色的判定生效前，可用符合过滤条件的手牌或装备替换判定；可配置获得旧判定牌。'],
            'lose_max_hp'=>['失去体力上限',3,['self','target'],98,'体力上限最低为零，随后检查濒死。'],
            'gain_max_hp'=>['增加体力上限',4,['self','target'],98,'提高上限，不抵消已有伤害。'],
            'turn_over'=>['翻转行动面',2,['self','target'],1,'翻至背面会在下一次自己的回合跳过并恢复正面，与冷暖翻面独立。'],
            'extra_turn'=>['获得额外回合',8,['self','target'],1,'当前回合结束后插入额外回合，之后恢复正常座次。'],
            'skip_draw'=>['跳过下次摸牌阶段',2,['self','target'],1,'跳过阶段而非将摸牌数置零。'],
            'skip_play'=>['跳过下次出牌阶段',4,['self','target'],1,'进入弃牌阶段，不产生新的出牌阶段。'],
            'skip_discard'=>['跳过下次弃牌阶段',3,['self','target'],1,'回合结束时不因手牌上限弃牌。'],
            'draw'=>['摸普通牌',2,['self','target'],3,'从普通牌池摸指定数量。'],
            'heal'=>['回复体力',2,['self','target'],3,'回复有效体力，心坏时不能回复。'],
            'damage'=>['造成伤害',4,['self','target'],3,'直接伤害，受护盾影响；无色每点额外预算 1。'],
            'recover_mind'=>['回收已耗心象到底部',3,['self'],3,'按已耗区顺序回收至心象底。'],
            'shield'=>['获得护盾',2,['self','target'],3,'累计上限取服务器 maxAmount 配置，下次自己的回合开始清空。'],
            'range'=>['本回合攻击范围增加',1,['self'],3,'下次自己的回合开始清空。'],
            'attack_bonus'=>['本回合攻击伤害增加',3,['self'],3,'累计上限取服务器 maxAmount 配置，下次自己的回合开始清空。'],
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
            'damage_guard'=>['每次受到伤害减少',4,['self','target'],2,'先于护盾计算，每次伤害都适用，下次自己的回合开始清空；累计上限取 maxAmount，不影响失去体力。'],
            'hand_lock'=>['本回合不能使用或打出手牌',6,['target'],1,'必须选择他人；本全局回合结束解除，仍可弃牌、赠牌、支付技能费用及使用已装备回避。'],
            'inspect_hand'=>['私密调查随机手牌',2,['target'],3,'仅发动者查看目标随机至多指定张手牌，确认后继续；不移动牌，不公开给其他玩家。'],
            'scry_mind'=>['重排自己的心象顶',1,['self'],3,'私密重排至多指定张心象顶牌；不抽取、不消耗，不改变心坏状态。'],
            'cycle_mind'=>['将心象顶依次移到底部',1,['self'],3,'移动至多指定张现有心象；不生成牌，原归属与总数不变。'],
            'draw_mind'=>['将心象顶加入手牌',3,['self'],2,'至多抽取指定张心象，消耗储备；抽空立即心坏，不能绕过系列限制。'],
            'recall_equipment'=>['将最早装备收回手牌',3,['self'],1,'收回自己最早的指定数量装备，保留心象归属；触发失去装备，宝藏仍造成卸除伤害。'],
            'break_shield'=>['削去护盾',1,['target'],3,'必须选择他人；只移除现有护盾，不造成伤害，也不移除御伤。'],
            'equip_range'=>['持续增加攻击范围',2,['self'],2,'装备立即生效，离场失效；心坏时攻击范围仍为 0。'],
            'equip_damage'=>['每个自己回合周期首次命中攻击增伤',6,['self'],1,'每个自己的回合开始后，首次结算攻击伤害时增加指定数值（被盾全抵消也消耗）；所有装备共用次数，反复拆装不刷新。'],
            'equip_shield'=>['自己的回合开始获得护盾',4,['self'],2,'自己的回合开始，清除旧护盾后获得指定护盾；装备入场时不送护盾。'],
            'equip_draw'=>['自己的回合开始摸普通牌',7,['self'],1,'每个自己的回合开始自动摸指定张数；装备入场不摸牌。'],
            'equip_distance'=>['其他角色攻击自己时距离增加',4,['self'],1,'立即增加攻击距离，不改变座次、邻近支援或事件距离；离场失效。'],
        ];
        $effects=[]; $effectMeta=[];
        foreach($rows as $op=>$r) {
            if(strpos($op,'equip_')!==0&&strpos($op,'passive_')!==0&&!in_array($op,['branch','choose_targets','event_add_damage','event_reduce_damage','event_set_damage','event_cancel'],true))$r[2]=array_values(array_unique(array_merge($r[2],['event_source','event_target','turn_player'])));
            $effects[$op]=$r[0];
            $effectMeta[$op]=['targets'=>$r[2],'min'=>1,'max'=>in_array($op,['passive_retrial','choose','branch','judge','pindian','exchange_hands','exchange_equipment','double_defense','hand_lock','turn_over','extra_turn','skip_draw','skip_play','skip_discard','passive_no_attack_target','passive_double_defense'],true)?1:RuleConfig::get('maxAmount'),'description'=>$r[4],'cost'=>$r[1],'equipmentOnly'=>strpos($op,'equip_')===0,'passiveOnly'=>strpos($op,'passive_')===0];
        }
        $meta = [
            'triggers'=>['passive'=>'持续生效','active'=>'出牌阶段主动','turn_start'=>'自己的回合开始','turn_end'=>'自己的回合结束（弃牌后）','after_damage'=>'受到伤害后','after_attack'=>'攻击造成伤害后','on_defend'=>'防御成功后','after_play_attack'=>'使用攻击牌后（等待防御前）','after_play_event'=>'使用事件牌后','hand_empty'=>'行动及即时效果结算后失去最后手牌','convert'=>'将指定素材转化使用 / 响应','on_targeted'=>'自己成为攻击目标时（关联攻击者）','ally_targeted'=>'距离 1 内其他同阵营角色成为攻击目标时（关联受攻者）','after_play_card'=>'主动使用实体手牌后','after_lose_equipment'=>'失去装备结算后（关联移除者）','after_equip'=>'主动装备手牌后（关联自己）'],
            'conditions'=>['hp_not_lowest'=>'体力不是全场最低','no_equipment'=>'没有装备','outside_turn'=>'自己的回合外','always'=>'总是','wounded'=>'体力受损','hand_low'=>'手牌不超过 2 张','hand_empty'=>'没有手牌','hand_full'=>'手牌不少于有效体力','healthy'=>'有效体力未受损','played_two'=>'本全局回合已主动使用至少 2 张手牌','played_three'=>'本全局回合已主动使用至少 3 张手牌','played_hp'=>'本全局回合主动用牌数不少于有效体力','first_card'=>'本全局回合主动使用的第一张手牌','same_rank_or_suit'=>'最近两张主动使用手牌点数或花色相同','first_damage'=>'本全局回合第一次实际受到伤害后','repeat_damage'=>'本全局回合已实际受到至少两次伤害','has_equipment'=>'装备区至少有一张牌','has_true_equipment'=>'至少有一件真正装备心象','mind_low'=>'心象储备不超过 4 张','hand_same_color'=>'至少两张手牌且均为同一红黑花色颜色'],
            'effects'=>$effects,'effectMeta'=>$effectMeta,
            'equipmentSlots'=>['weapon'=>'武器','armor'=>'防具','gadget'=>'装置'],
            'equipmentEffects'=>['weapon'=>['equip_range','equip_damage'],'armor'=>['equip_shield','equip_distance'],'gadget'=>['equip_range','equip_draw']],
            'targets'=>['self'=>'自己','target'=>'指定角色 / 触发关联角色','allies'=>'其他同阵营角色','enemies'=>'所有敌方角色','everyone'=>'所有存活角色'],
            'conversions'=>['from'=>['attack'=>'攻击牌','defense'=>'防御牌','red'=>'红桃 / 方块手牌','black'=>'黑桃 / 梅花牌','heart'=>'红桃牌','spade'=>'黑桃牌','club'=>'梅花牌','diamond'=>'方块牌','basic'=>'基本牌','event'=>'事件牌','equipment'=>'真正装备牌','hand'=>'任意牌'],'to'=>['attack_neutral'=>'无色攻击','defense'=>'防御']],
            'limits'=>range(0,RuleConfig::get('unlimitedUses')),
            'limitScopes'=>['turn'=>'每个全局回合','owner_turn'=>'自己的回合周期','game'=>'整局游戏'],
            'amounts'=>['all'=>'全部（最多配置上限）','hand'=>'自己的手牌数','hp'=>'自己的体力','lost_hp'=>'自己的已损失体力','target_hand'=>'目标的手牌数','paid_hand'=>'本次弃置的手牌数'],
        ];
        $meta['triggers']+=['before_deal_damage'=>'自己造成伤害前','before_take_damage'=>'自己受到伤害前','any_before_damage'=>'任意角色受到伤害前','after_deal_damage'=>'自己造成伤害后','any_after_damage'=>'任意角色受到伤害后','damage_point'=>'自己受到伤害后，按每点依次触发','dying'=>'自己进入濒死时','any_dying'=>'任意角色进入濒死时','death'=>'自己确定死亡时（清理牌区前）','any_death'=>'任意角色死亡并清理牌区后'];
        $meta['conditions']+=['event_attack'=>'本次伤害来自攻击','event_source_self'=>'本次事件来源是自己','event_target_self'=>'本次事件目标是自己','event_source_other'=>'本次事件来源是其他角色'];
        $meta['targets']+=['event_source'=>'本次事件的来源','event_target'=>'本次事件的目标','turn_player'=>'当前回合角色'];
        $meta['amounts']+=['event_amount'=>'本次实际事件数量','event_original_amount'=>'本次事件初始数量','event_point'=>'本次伤害已结算的点数序号'];
        $meta['amounts']+=['paid_cards'=>'本次费用弃牌总数','paid_equipment'=>'本次费用弃置装备数'];
        $meta['conditions']+=['paid_all_hand'=>'本次费用弃置了原有的全部手牌','never'=>'从不满足'];
        foreach(['event_cancel','event_redirect','event_source'] as $op)$meta['effectMeta'][$op]['max']=1;
        foreach(['gain_skill','lose_skill','seal_skill','copy_skill'] as $op)$meta['effectMeta'][$op]['max']=1;
        return $meta;
    }
}
