/* Plain-language and structural descriptions share the server's vocabulary. */
'use strict';
globalThis.SkillText = (() => {
  const colors={cool:'冷色',warm:'暖色',neutral:'无色'};
  const phrases={draw:'摸{n}张牌',heal:'回复{n}点体力',lose_health:'失去{n}点体力',damage:'受到{n}点{color}伤害',attack:'受到一次{n}点{color}攻击（可以防御）',shield:'获得{n}点护盾',draw_to:'将手牌补至{n}张',give_hand:'获得你交出的{n}张手牌',steal_hand:'被你随机获得{n}张手牌',discard_hand:'随机弃置{n}张手牌',recover_mind:'将{n}张已耗心象放回心象底',range:'本回合攻击范围增加{n}',extra_attacks:'本回合可额外使用{n}次攻击',attack_bonus:'本回合攻击伤害增加{n}',scry:'观看并排列牌堆顶的{n}张牌',scry_mind:'观看并排列心象顶的{n}张牌',draw_mind:'抽取{n}张心象',cycle_mind:'将心象顶的{n}张牌移到底部',hand_lock:'本回合不能使用或打出手牌',double_defense:'本回合的攻击需要连续防御两次',damage_guard:'每次受到的伤害减少{n}',discard_equipment:'弃置{n}张装备',sequester_hand:'暂置{n}张手牌，在回合结束时归还',inspect_hand:'被你私下查看{n}张随机手牌',draw_discard:'获得弃牌堆顶的{n}张牌',recall_equipment:'将最早的{n}张装备收回手牌',break_shield:'失去{n}点护盾'};
  Object.assign(phrases,{
    lose_max_hp:'失去{n}点体力上限',gain_max_hp:'增加{n}点体力上限',turn_over:'翻转行动面；背面角色跳过下一次自己的回合',extra_turn:'在当前回合后获得一个额外回合',skip_draw:'跳过下一次摸牌阶段',skip_play:'跳过下一次出牌阶段',skip_discard:'跳过下一次弃牌阶段',
    add_mark:'获得{n}个标记',remove_mark:'移去{n}个标记',store_pile:'选择{n}张手牌放入专属牌堆',take_pile:'从专属牌堆选择{n}张牌加入手牌',exchange_hands:'与你交换全部手牌',exchange_equipment:'与你交换全部装备',duel:'与你决斗：从其开始轮流打出攻击牌，首先不能或不愿打出者受到对方造成的{n}点伤害',
    passive_attacks:'每回合可额外使用{n}次攻击',passive_range:'攻击范围增加{n}',passive_hand:'手牌上限增加{n}',passive_hand_penalty:'手牌上限减少{n}',passive_distance_out:'计算与其他角色的攻击距离时减少{n}',passive_distance_in:'被其他角色计算攻击距离时增加{n}',passive_draw:'摸牌阶段多摸{n}张牌',passive_draw_penalty:'摸牌阶段少摸{n}张牌',passive_guard:'每次受到的伤害减少{n}',passive_no_attack_target:'不能成为攻击目标',passive_double_defense:'使用的攻击需要额外一次防御',
    equip_range:'攻击范围增加{n}',equip_damage:'每个自己的回合周期内，首次结算攻击伤害时额外造成{n}点伤害',equip_shield:'在自己的回合开始时获得{n}点护盾',equip_draw:'在自己的回合开始时摸{n}张牌',equip_distance:'被其他角色计算攻击距离时增加{n}'
  });
  const filters={red:'红色',black:'黑色',heart:'红桃',not_heart:'非红桃',spade:'黑桃',club:'梅花',diamond:'方块',any:'任意牌',attack:'攻击牌',defense:'防御牌',basic:'基本牌',event:'事件牌',equipment:'真正装备牌',hand:'任意牌'};
  function amount(n,meta){if(typeof n==='number')return String(n);if(n.startsWith('mark:'))return '「'+n.slice(5)+'」标记数';if(n.startsWith('pile:'))return '「'+n.slice(5)+'」牌堆张数';return {all:'全部',hand:'当前手牌数',hp:'当前体力值',lost_hp:'已损失体力值',target_hand:'目标手牌数',paid_hand:'本次弃置的手牌数'}[n]||meta.amounts?.[n]||n;}
  function effects(list,meta,mode='natural') {
    return list.map(e=>{
      const duration={permanent:'持续保留',turn:'至当前回合结束',owner_turn:'至获得者下个回合开始',source_turn:'至发动者下个回合开始'}[e.duration];
      if(e.op==='gain_skill')return (e.target==='self'?'你':meta.targets[e.target]||'角色')+'获得「'+e.skill.name+'」：'+skill(e.skill,meta,mode)+'（'+duration+'，标识：'+e.key+'）';
      if(['lose_skill','seal_skill','copy_skill'].includes(e.op))return (meta.targets[e.target]||'角色')+(e.op==='copy_skill'?'的一项有效技能由你选择并获得':(e.op==='lose_skill'?'失去':'封锁')+'标识为「'+e.key+'」的技能')+(duration?'（'+duration+'）':'');
      if(e.op==='choose')return `由${e.target==='self'?'你':'目标角色'}选择：`+e.options.map(o=>o.label+'（'+effects(o.effects,meta,mode)+'）').join('；或');
      if(['branch','judge','pindian'].includes(e.op))return (e.op==='branch'?'若'+predicate(e.condition,meta):e.op==='judge'?'判定，符合'+({red:'红色',black:'黑色',heart:'红桃',not_heart:'非红桃',spade:'黑桃',club:'梅花',diamond:'方块',any:'任意牌'}[e.filter]||e.filter)+'条件': '与目标拼点，若你赢')+'，'+(effects(e.then,meta,mode)||'不执行额外效果')+'；否则'+(effects(e.else,meta,mode)||'不执行额外效果')+(e.obtain||e.repeat?'；判定成功时，'+[e.obtain?'获得判定牌':'',e.repeat?'可以继续判定':''].filter(Boolean).join('，'):'');
      if(e.op==='choose_targets')return `从${{everyone:'所有角色',others:'其他角色',allies:'其他同阵营角色',enemies:'敌方角色'}[e.pool]}中选择至多${amount(e.amount,meta)}名，依次令其：`+effects(e.effects,meta,mode);
      const target=e.target==='self'?'你':(meta.targets?.[e.target]||'目标角色');
      const n=amount(e.amount,meta);
      if(e.op.startsWith('event_'))return {event_add_damage:'令本次伤害增加'+n+'点',event_reduce_damage:'令本次伤害减少'+n+'点',event_set_damage:'将本次伤害改为'+n+'点',event_cancel:'取消本次伤害',event_redirect:'将本次伤害转移给'+target,event_source:'将本次伤害来源改为'+target}[e.op];
      if(mode==='technical') return `${meta.targets?.[e.target]||e.target} · ${meta.effects[e.op]||e.op}(${n}${['damage','attack'].includes(e.op)?'，'+colors[e.color]:''})`;
      if(e.op==='passive_retrial')return '任意角色的判定生效前，你可以用'+(e.zone==='hand_equipment'?'手牌或装备区':'手牌区')+'的一张'+filters[e.filter]+'替换判定牌'+(e.exchange?'，并获得原判定牌':'');
      if(e.op==='passive_attacks'&&e.amount==='all')return target+'使用攻击没有次数限制';
      let phrase=(phrases[e.op]||`${meta.effects[e.op]||e.op}（{n}）`).replaceAll('{n}',typeof e.amount==='number'||e.amount==='all'?n:'X').replaceAll('{color}',colors[e.color]||'无色');
      if(typeof e.amount==='string'&&e.amount!=='all')phrase+='（X为'+n+'）';
      if(e.amount==='all'&&!['give_hand','discard_hand','steal_hand','recover_mind','store_pile','take_pile','discard_equipment','recall_equipment','sequester_hand'].includes(e.op))phrase=phrase.replaceAll('全部',String(meta.effectMeta[e.op].max))+'（服务器数量上限）';
      phrase=phrase.replaceAll('全部张','全部');
      if(['range','extra_attacks','attack_bonus','double_defense','damage_guard','shield'].includes(e.op))phrase=phrase.replaceAll('本回合','')+'（至下个自己的回合开始）';
      return target+phrase+(e.key?'「'+e.key+'」':'')+(e.op==='store_pile'?'（'+(e.visibility==='private'?'仅持有者可见':'公开')+'）':'');
    }).join('；然后');
  }
  function skill(s,meta,mode='natural') {
    const cap=s.limit??0,scope=meta.limitScopes?.[s.limitScope||'turn']||'每个全局回合';
    const limit=cap===0?'不限次数':`${scope}限${cap}次`;
    const hand=typeof s.cost?.hand==='number'?s.cost.hand:s.cost?.hand==='all'?'全部':'任意数量';
    const c=s.conversion||{},zone={hand:'手牌',hand_equipment:'手牌或装备',pile:'「'+c.pile+'」专属牌堆的牌'}[c.zone||'hand'];
    const effect=s.trigger==='convert'?`将${c.count||1}张${filters[c.from]||meta.conversions.from[c.from]||'牌'}（来自${zone}${c.match==='same_suit'?'，花色相同':c.match==='same_color'?'，红黑颜色相同':''}）当作${meta.conversions.to[c.to]||'牌'}使用或打出`:effects(s.effects||[],meta,mode);
    if(mode==='technical') return `${meta.triggers[s.trigger]||s.trigger} / ${predicate(s.condition,meta)} / ${limit} / 手牌费用：${hand||0}；心象费用：${s.cost?.mind||0}。${effect}。`;
    const costs=[];
    if(s.cost?.hand)costs.push(`${hand}${s.cost.hand==='all'?'':'张'}${s.cost?.zone==='hand_equipment'?'手牌或装备':'手牌'}`);
    if(s.cost?.mind)costs.push(`${s.cost.mind}张心象顶牌`);
    const passive=s.trigger==='passive';
    if(passive&&s.effects.every(e=>e.op==='passive_retrial'))return (s.condition&&s.condition!=='always'?'若'+predicate(s.condition,meta)+'，':'')+effect+'。';
    const locked=!['active','convert'].includes(s.trigger)&&!s.optional;
    const timing=s.trigger==='active'?'出牌阶段':s.trigger==='convert'?'需要使用或打出对应牌时':meta.triggers[s.trigger]||s.trigger;
    const result=!locked?effect.replace(/^(?:由你|你)/,''):effect;
    return `${locked?'锁定技。':''}${timing}${s.condition&&s.condition!=='always'?'，若'+predicate(s.condition,meta):''}，${locked?'':'你可以'}${costs.length?'弃置'+costs.join('和')+'，然后':''}${result}。${cap&&!passive?limit+'。':''}`;
  }
  function predicate(c,meta){if(typeof c==='string')return meta.conditions[c]||c;if(!c)return '总是';if(c.all||c.any)return '('+(c.all||c.any).map(x=>predicate(x,meta)).join(c.all?'且':'或')+')';const values={hp:'体力',max_hp:'体力上限',lost_hp:'已损失体力',hand:'手牌数',mind:'心象数',mark:'标记数',pile:'专属牌堆张数',played:'本回合用牌数',damage_taken:'本回合受伤次数',pindian_win:'上次拼点获胜',event_amount:'本次事件数量',event_original_amount:'本次事件初始数量',event_point:'当前伤害点数序号'};return (c.subject?(meta.targets[c.subject]+'的'):'')+(values[c.value]||c.value)+(c.key?'「'+c.key+'」':'')+({eq:'等于',ne:'不等于',lt:'小于',le:'不超过',gt:'大于',ge:'至少'}[c.cmp]||c.cmp)+c.amount;}
  function mind(card,meta,mode='natural') {
    let prefix='';
    if(card.kind==='equipment') prefix=`装备到${meta.equipmentSlots[card.slot]}槽，同槽装备替换。`;
    if(['persistent','delayed'].includes(card.kind)) prefix=`${card.maturityTurns?`第${card.maturityTurns}个自己的回合成熟`:'放置后立即成熟'}。${card.kind==='persistent'?'出牌阶段可卸除：':'成熟后在持有者回合开始结算：'}`;
    return prefix+effects(card.effects||[],meta,mode)+'。';
  }
  function basic(card,definition) {
    let text=definition.description||card.description||'';
    if(!['persistent','delayed'].includes(definition.kind))return text;
    const maturity=card.maturityTurns??definition.maturityTurns??(card.type==='punch'?1:0);
    const prefix=definition.kind==='delayed'?'放置后':'装备后';
    text=text.replace('默认装备后立即成熟。','').replaceAll('从下个自己的回合起','成熟后').replaceAll('装备后立即','成熟后可').replace('目标下个回合抽牌前','成熟后，在持有者回合开始时');
    text=text.replaceAll('成熟后可可','成熟后可');
    if(card.type==='miracle')text='成熟后，'+text;
    return prefix+(maturity===0?'立即成熟。':'，在第'+maturity+'个自己的回合开始时成熟。')+text;
  }
  return {effects,skill,mind,predicate,basic};
})();
