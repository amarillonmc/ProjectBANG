<?php
namespace Imaginary;

/** Mechanism examples only: no new character or mandatory preset changes. */
final class SkillPuzzles
{
    public static function all(): array
    {
        $e=function($op,$n=1,$target='self',$extra=[]){return array_merge(['op'=>$op,'amount'=>$n,'target'=>$target,'color'=>'neutral'],$extra);};
        $skill=function($trigger,$effects,$limit=0,$hand=0,$extra=[]){return array_merge(['trigger'=>$trigger,'condition'=>'always','cost'=>['hand'=>$hand,'mind'=>0],'effects'=>$effects,'limit'=>$limit],$extra);};
        $rows=[
            ['damage_modifier','伤害发生前修改',$skill('before_take_damage',[$e('event_reduce_damage')]),'在扣血与消耗护盾前减少本次伤害，不通过事后回血模拟。'],
            ['damage_redirect','伤害转移',$skill('before_take_damage',[$e('choose_targets',1,'self',['pool'=>'others','effects'=>[$e('event_redirect',1,'target')]])],1,0,['optional'=>true]),'选择另一角色承受原来的伤害，保留来源；新目标也有受伤前窗口。'],
            ['damage_points','每点伤害触发',$skill('damage_point',[$e('draw')],0,0,['optional'=>true]),'一次受到多点伤害时，逐点询问和结算。'],
            ['event_observer','旁观伤害事件',$skill('any_before_damage',[$e('event_reduce_damage')],1,0,['condition'=>['subject'=>'event_target','value'=>'hp','cmp'=>'le','amount'=>1],'optional'=>true]),'任意角色受到伤害前，检查该角色的体力，再选择是否减少伤害。'],
            ['dying_rescue','濒死技能自救',$skill('dying',[$e('heal',2)],1,0,['limitScope'=>'game','optional'=>true]),'濒死时先询问技能，回复后仍存活才继续受伤后的效果。'],
            ['death_legacy','死亡前遗赠',$skill('death',[$e('choose_targets',1,'self',['pool'=>'others','effects'=>[$e('give_hand','all','target')]])],1,0,['limitScope'=>'game','optional'=>true]),'确定死亡后，在清理牌区前选择其他角色获得全部手牌。'],
            ['unbounded_red','红牌攻击 · 不限次数',$skill('convert',[],0,0,['conversion'=>['from'=>'red','to'=>'attack_neutral','zone'=>'hand_equipment']]),'红色手牌或装备转化为攻击，转换本身不限次；仍遵守角色的攻击次数。'],
            ['chosen_cycle','任意数量换牌',$skill('active',[$e('draw','paid_cards'),$e('branch',1,'self',['condition'=>'paid_all_hand','then'=>[$e('draw')],'else'=>[]])],1,'chosen',['cost'=>['hand'=>'chosen','mind'=>0,'zone'=>'hand_equipment']]),'任选手牌与装备弃置，摸等量牌；若弃置了原有的全部手牌，再摸一张。'],
            ['continuous_attacks','持续攻击额度',$skill('passive',[$e('passive_attacks','all')]),'持续增加攻击次数，支持不限次转换组合。'],
            ['locked_draw','受伤补牌 · 锁定',$skill('after_damage',[$e('draw')]),'每次实际受到伤害均触发，不受一两次设计限制。'],
            ['optional_draw','受伤补牌 · 可选',$skill('after_damage',[$e('draw')],0,0,['optional'=>true]),'受伤后询问是否发动，可在响应窗口放弃。'],
            ['hand_aura','同阵营手牌光环',$skill('passive',[$e('passive_hand',1,'allies')]),'其他同阵营角色手牌上限增加一；离场或心坏立即失效。'],
            ['empty_guard','空手目标限制',$skill('passive',[$e('passive_no_attack_target')],0,0,['condition'=>'hand_empty']),'没有手牌时，不能成为攻击目标；不是对所有事件的免疫。'],
            ['compulsory_cost','强制负面二选一',$skill('turn_end',[$e('choose',1,'self',['options'=>[['label'=>'失去一点体力','effects'=>[$e('lose_health')]],['label'=>'失去一点体力上限','effects'=>[$e('lose_max_hp')]]]])],0,0,['condition'=>'hp_not_lowest']),'体力不是全场最低时必须付出一种代价，按较轻的代价计算负预算。'],
            ['judgment_chain','黑色判定链',$skill('turn_start',[$e('judge',1,'self',['filter'=>'black','obtain'=>true,'repeat'=>true,'then'=>[],'else'=>[]])],1,0,['optional'=>true]),'成功获得黑色判定牌，并选择是否继续；失败结束。'],
            ['retrial','判定替换与交换',$skill('passive',[$e('passive_retrial',1,'self',['filter'=>'black','zone'=>'hand_equipment','exchange'=>true])]),'判定生效前，用黑色手牌或装备替换判定牌，并获得旧判定牌。'],
            ['pindian_branch','拼点与胜负分支',$skill('active',[$e('pindian',1,'target',['then'=>[$e('extra_attacks')],'else'=>[$e('draw',1,'target')]])],1),'双方暗选手牌，揭示比较点数，平局进入未赢分支。'],
            ['group_draw','多目标分配',$skill('active',[$e('choose_targets',2,'self',['pool'=>'others','effects'=>[$e('draw',1,'target')]])],1,1),'选择至多两名其他角色，各摸一张牌。'],
            ['public_pile','存入公开专属牌堆',$skill('active',[$e('store_pile',1,'self',['key'=>'资源','visibility'=>'public'])],0),'自由选择手牌放到角色专属区域，支持标记条件和动态数量。'],
            ['private_pile','存入私密专属牌堆',$skill('active',[$e('store_pile',1,'self',['key'=>'密藏','visibility'=>'private'])],0),'牌面只有持有者可见，对手只看数量。'],
            ['retrieve_pile','取回专属牌堆',$skill('active',[$e('take_pile','all','self',['key'=>'资源'])],1),'自选顺序把专属牌堆里的牌取回手牌，保留实体卡归属。'],
            ['pile_conversion','专属牌堆转化',$skill('convert',[],0,0,['conversion'=>['from'=>'hand','to'=>'attack_neutral','zone'=>'pile','pile'=>'资源']]),'将指定专属牌堆的牌转化为攻击。'],
            ['limited_draw','整局限定补牌',$skill('active',[$e('draw',3)],1,0,['limitScope'=>'game']),'整局只能发动一次，保存与重连不重置。'],
            ['mark_counter','积累命名标记',$skill('after_damage',[$e('add_mark',1,'self',['key'=>'觉醒'])]),'每次受伤获得一个标记，可供其他技能读取。'],
            ['awakening_gate','标记门槛与一次觉醒',$skill('turn_start',[$e('lose_max_hp'),$e('draw',2),$e('add_mark',1,'self',['key'=>'已觉醒'])],1,0,['limitScope'=>'game','condition'=>['value'=>'mark','key'=>'觉醒','cmp'=>'ge','amount'=>3]]),'达到三标记后执行一次；其他拼图可用已觉醒标记作为开放条件。'],
            ['duel','交替攻击的决斗',$skill('active',[$e('duel',1,'target')],1,1),'目标先响应，双方轮流打出攻击，先不打出的角色受到伤害。'],
            ['turn_over','行动面翻转',$skill('active',[$e('turn_over',1,'target')],1,1),'翻至背面跳过下一自身回合，与冷暖颜色翻面独立。'],
            ['extra_turn','插入额外回合',$skill('active',[$e('extra_turn')],1,1,['limitScope'=>'game']),'额外回合结束后继续原来的正常座次。'],
            ['gain_skill','永久获得新技能',$skill('active',[$e('gain_skill',1,'self',['key'=>'新能力','duration'=>'permanent','skill'=>['name'=>'获得的补牌']+$skill('active',[$e('draw')],1)])],1),'获得完整技能定义。失去后再获得同一标识不刷新原有次数。'],
            ['borrow_skill','临时获得新技能',$skill('active',[$e('gain_skill',1,'target',['key'=>'借用范围','duration'=>'source_turn','skill'=>['name'=>'借用的范围']+$skill('passive',[$e('passive_range')])])],1),'赋予另一角色持续范围，至发动者下个回合开始自动失效。'],
            ['copy_skill','选择并复制技能',$skill('active',[$e('copy_skill',1,'target',['duration'=>'turn'])],1),'选择另一角色的一项有效技能，在当前回合获得；复制同一技能保留自身的累计次数。'],
            ['seal_skill','封锁与自动恢复',$skill('active',[$e('seal_skill',1,'target',['key'=>'新能力','duration'=>'turn'])],1),'按稳定标识封锁一个技能，在当前回合结束时恢复；持续技能同样受影响。'],
        ];
        $out=[];foreach($rows as $row){$s=$row[2];$s['name']=$row[1];$out[]=['id'=>'mechanic_'.$row[0],'name'=>$row[1],'expansion'=>'通用机制 0.5','source'=>'引擎原生拼图','inspiration'=>'可复用机制示例','summary'=>$row[3],'adaptation'=>$row[3],'tags'=>['机制','0.5'],'skill'=>$s];}return $out;
    }
}
