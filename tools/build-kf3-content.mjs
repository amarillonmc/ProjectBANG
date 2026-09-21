// Rebuild the bundled pack from the reviewed ProjectK snapshot; no network needed.
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const research=JSON.parse(fs.readFileSync(path.join(root,'docs/content/kf3-research.json'),'utf8'));
const E=(op,amount=1,target='self',color='neutral')=>({op,amount,target,color});
const S=(name,trigger,effects,hand=0,condition='always',mind=0)=>({name,trigger,condition,cost:{hand,mind},limit:1,effects});
const C=(name,from,to)=>({...S(name,'convert',[]),conversion:{from,to}});
const bang='https://dvgiochi.com/giochi/bang10th/download/bang_10th-rules.pdf';
const sgs='https://www.sanguosha.com/hero';
const skillTemplates=[];
function template(id,game,reference,description,skill,tags,sourceUrl=game==='BANG!'?bang:sgs){skillTemplates.push({id,name:skill.name,game,reference,description,tags,sourceUrl,skill});}
template('rende','三国杀','刘备 · 仁德','定额赠两张手牌后回复自己1点；每全局回合一次，不采用原版累计赠牌阈值。',S('赠牌疗愈','active',[E('give_hand',2,'target'),E('heal')]),['赠牌','治疗']);
template('supply','三国杀','刘备 · 仁德 / 郭嘉 · 遗计','主动赠一张手牌，再摸一张；保留资源分配思想，不代替遗计的完整流程。',S('接力补给','active',[E('give_hand',1,'target'),E('draw')]),['赠牌','循环']);
template('bart','BANG!','Bart Cassidy','实际受到伤害后摸1张；改为每全局回合一次，不按每点伤害累计。',S('受创整备','after_damage',[E('draw')]),['受伤','摸牌']);
template('yiji','三国杀','郭嘉 · 遗计','受伤摸2张的有界版本；牌的后续分配可另外拼接赠牌主动技能。',S('受创启发','after_damage',[E('draw',2)]),['受伤','摸牌']);
template('yingzi','三国杀','周瑜 · 英姿','在回合开始额外摸1张，提前到抽牌前且不改变标准摸牌数量。',S('先行整备','turn_start',[E('draw')]),['回合开始','摸牌']);
template('jizhi','三国杀','黄月英 · 集智','使用事件牌后摸1张，每全局回合一次；本项目限定心象也属于事件。',S('灵感回响','after_play_event',[E('draw')]),['事件','摸牌']);
template('lianying','BANG! / 三国杀','Suzy Lafayette / 陆逊 · 连营','整个行动与即时效果结算后空手才补1张，每全局回合一次，避免支付中途插入抽牌。',S('空手续行','hand_empty',[E('draw')]),['空手','摸牌'],bang);
template('paoxiao','BANG! / 三国杀','Willy the Kid / 张飞 · 咆哮','回合开始增加2次普通攻击额度，仍需要攻击牌；不采用无限次数。',S('连续攻势','turn_start',[E('extra_attacks',2)]),['攻击','次数'],bang);
template('burst','三国杀','诸葛连弩','弃1张手牌，增加2次本回合普通攻击额度；不是装备牌，不永久生效。',S('连发准备','active',[E('extra_attacks',2)],1),['攻击','次数']);
template('mashu','BANG! / 三国杀','Rose Doolan / 马超 · 马术','回合开始攻击范围+1；仅影响自身攻击，不改变双方所有距离判断。',S('疾行巡猎','turn_start',[E('range')]),['距离','攻击'],bang);
template('longdan_def','BANG! / 三国杀','Calamity Janet / 赵云 · 龙胆','将攻击手牌作为防御响应；每全局回合一次，保留实体与原心象归属。',C('攻势化守势','attack','defense'),['转换','防御'],bang);
template('longdan_atk','BANG! / 三国杀','Calamity Janet / 赵云 · 龙胆','将防御手牌作为无色攻击；占用通常攻击额度，每全局回合一次。',C('守势化攻势','defense','attack_neutral'),['转换','攻击'],bang);
template('wusheng','三国杀','关羽 · 武圣','一张红桃或方块实体手牌可作无色攻击；每全局回合一次。无花色心象不能如此转化。',C('红牌进击','red','attack_neutral'),['转换','花色']);
template('qingguo','三国杀','甄姬 · 倾国','一张黑桃或梅花实体手牌可作防御；每全局回合一次。',C('黑牌闪避','black','defense'),['转换','花色']);
template('kurou','三国杀','黄盖 · 苦肉','主动失去1点有效体力，再摸2张；不触发受伤技能，先结算濒死，退场后不摸牌。',S('以身开路','active',[E('lose_health'),E('draw',2)]),['体力代价','摸牌']);
template('qingnang','三国杀','华佗 · 青囊','弃1张手牌，治疗自己或指定角色1点。心坏者仍不能回复。',S('悉心照料','active',[E('heal',1,'target')],1),['治疗','援助']);
template('sid','BANG!','Sid Ketchum','弃两张手牌回复自己1点；限自己出牌阶段每全局回合一次。',S('整理休憩','active',[E('heal')],2,'wounded'),['治疗','弃牌']);
template('zhiheng','三国杀','孙权 · 制衡','定额弃两张手牌、摸两张；不支持原版任意张数与装备区。',S('试作重整','active',[E('draw',2)],2),['换牌','循环']);
template('qixi','三国杀','甘宁 · 奇袭 / 过河拆桥','弃1张任意手牌，随机弃置目标1张手牌；不要求黑色，也不处理公开装备。',S('潜行拆解','active',[E('discard_hand',1,'target')],1),['拆牌','控制']);
template('jesse','BANG!','Jesse Jones / Panic!','主动弃1张自己的手牌，随机取得另一人的1张手牌；不替代摸牌阶段，不要求距离。',S('觅得珍藏','active',[E('steal_hand',1,'target')],1),['取牌','控制']);
template('tuxi','三国杀','张辽 · 突袭','主动弃1张手牌，随机取得同一目标2张手牌；替代原版摸牌阶段、多目标流程。',S('包抄补给','active',[E('steal_hand',2,'target')],1),['取牌','控制']);
template('elgringo','BANG!','El Gringo','实际受伤后随机取得来源1张手牌；无来源、来源退场或没有牌时不获得，每全局回合一次。',S('迎击缴获','after_damage',[E('steal_hand',1,'target')]),['受伤','取牌']);
template('ganglie','三国杀','夏侯惇 · 刚烈','受伤后对范围内来源发起1点暖色可防御攻击；不使用原版判定，仍遵守冷暖目标规则。',S('守护反击','after_damage',[E('attack',1,'target','warm')]),['受伤','反击']);
template('guanxing','三国杀','诸葛亮 · 观星','回合开始私密查看普通牌顶3张并重排回牌顶；不能置底，不改变普通摸牌数。',S('仰望星图','turn_start',[E('scry',3)]),['私密信息','排序']);
template('survey','BANG!','Kit Carlson','主动弃1张牌，私密重排普通牌顶3张，再摸1张；保留筛选信息的思路，非原版三选二。',S('线索筛选','active',[E('scry',3),E('draw')],1),['私密信息','排序']);
template('pedro','BANG!','Pedro Ramirez','回合开始取得普通弃牌堆顶1张；弃牌堆为空则无收益。该版本为额外收益，不替代标准摸牌。',S('拾回旧物','turn_start',[E('draw_discard')]),['弃牌回收','循环']);
template('wushuang','BANG! / 三国杀','Slab the Killer / 吕布 · 无双','回合开始令本人的攻击需连续2次防御；不扩展到决斗，失败时整次攻击伤害正常结算。',S('强势压阵','turn_start',[E('double_defense')]),['双重防御','攻击'],bang);
template('wine','三国杀','酒','弃1张手牌，本回合所有攻击伤害+1；不像酒仅作用于下一次杀。',S('蓄势一击','active',[E('attack_bonus')],1),['增伤','攻击']);
template('steadfast','三国杀','曹仁 · 据守（防守整备思想）','回合结束弃1张手牌，摸1张并获1护盾；无翻面/跳过回合，不声称等同据守。',S('夜间守备','turn_end',[E('draw'),E('shield')],1),['结束阶段','护盾']);
template('rest','BANG!','Beer / Saloon（恢复思想）','自己的回合结束时若受伤则回复1点；不具备酒的濒死即时救援窗口。',S('休息恢复','turn_end',[E('heal')],0,'wounded'),['治疗','结束阶段']);
template('refill','三国杀','陆逊 · 连营（低资源补充思想）','主动把手牌补到3张，仅手牌不超过2张时使用；与即时连营分别提供。',S('重新出发','active',[E('draw_to',3)],0,'hand_low'),['低手牌','摸牌']);
template('defend_draw','BANG!','Missed!（防守资源改编）','成功完成整次防御后摸1张，每全局回合一次。',S('从容应对','on_defend',[E('draw')]),['防御','摸牌']);
template('press','三国杀','庞德 · 猛进（追击拆牌思想）','使用攻击牌后、等待防御前随机弃目标1张手牌；不采用原版被闪后才触发。',S('打乱节奏','after_play_attack',[E('discard_hand',1,'target')]),['攻击','拆牌']);
template('guard','BANG!','Barrel（防护思想）','回合开始获得1点有界护盾；这是确定减伤，明确不使用木桶的随机判定。',S('警戒防线','turn_start',[E('shield')]),['护盾','准备']);
template('yinghun','三国杀','孙坚 · 英魂（受伤援助思想）','受伤时主动让指定角色摸2张；固定数值，不按伤势计算，也不强制弃牌。',S('逆境指引','active',[E('draw',2,'target')],0,'wounded'),['援助','受伤条件']);
const lookup=Object.fromEntries(skillTemplates.map(t=>[t.id,t]));
// ID, title, color, HP, [template/name pairs], [two bespoke card definitions].
// Card effects are actual reusable blocks, never interpreted from flavor text.
const rows=[
 [1,'把大家连在一起','warm',6,['rende','群体的羁绊','mashu','循着气味前行'],[['大家一起上吧',[E('draw',1,'target'),E('draw'),E('shield',1,'target')],'遗计 / Stagecoach'],['探险队的口哨',[E('give_hand',1,'target'),E('heal',1,'target'),E('recover_mind')],'仁德',true]]],
 [3,'沙漠中的冷静判断','cool',5,['survey','冷静的判断','defend_draw','听见风的方向'],[['灼热沙漠的绿洲',[E('heal'),E('heal',1,'target'),E('draw')],'Saloon / 桃园结义'],['耳边的路线图',[E('scry',3),E('draw',2)],'Kit Carlson']]],
 [4,'探险队长出动','warm',6,['bart','全都交给小浣吧','refill','还没有结束呢'],[['小浣的时代',[E('lose_health'),E('draw',3)],'苦肉'],['队长的宝物',[E('draw_discard',2),E('shield')],'Pedro Ramirez']]],
 [6,'河川游戏发明家','cool',6,['longdan_def','十种声音·守','longdan_atk','十种声音·攻'],[['河底的宝物',[E('steal_hand',1,'target'),E('draw')],'Panic! / 顺手牵羊'],['水边大冒险',[E('range',2),E('extra_attacks')],'Rose Doolan / Volcanic']]],
 [7,'渡河的可靠伙伴','warm',5,['paoxiao','直到打赢为止','wine','认真起来的力量'],[['迷幻乱拳',[E('attack',1,'target','warm'),E('draw')],'BANG! / 杀（非决斗）'],['河流的向导',[E('give_hand',1,'target'),E('heal',1,'target'),E('draw')],'仁德 / 青囊']]],
 [8,'山丘茶馆的主人','warm',6,['sid','熟练手艺','supply','请慢慢享用'],[['最好的疗愈',[E('heal',2,'target'),E('draw',1,'target')],'青囊 / 桃'],['刚出炉的点心',[E('heal'),E('draw',2)],'Beer / Stagecoach']]],
 [9,'松弛而可靠的王者','warm',6,['wushuang','王者的威严','sid','午睡时间'],[['咆哮一闪',[E('attack_bonus'),E('range',2)],'酒 / Scope'],['百兽的号令',[E('double_defense'),E('extra_attacks')],'无双 / 咆哮',true]]],
 [10,'森林巡守与挑战者','warm',5,['mashu','森林巡守','paoxiao','再来一场较量'],[['烈风冲击',[E('range',3),E('attack',1,'target','warm')],'马术 / 杀（单目标）'],['把后背交给我',[E('shield',2,'target'),E('draw')],'Barrel（确定防护改编）']]],
 [11,'知识之树的博士','cool',5,['guanxing','博士的作战','pedro','知识不会白费'],[['智慧果实大丰收',[E('draw',3)],'无中生有 / Stagecoach'],['博士的星图',[E('scry',3),E('draw'),E('recover_mind')],'观星',true]]],
 [12,'夜间作战的助手','cool',6,['jizhi','助手的完美作战','guard','作战漏洞检查'],[['夜之猛禽',[E('range',2),E('draw')],'Scope'],['资料分送完毕',[E('draw',2,'target'),E('draw')],'无中生有 / 遗计']]],
 [13,'雪地游戏达人','cool',5,['qingguo','看穿招式','refill','再挑战一次'],[['稀有道具出货',[E('scry',3),E('draw',2)],'Kit Carlson（选牌而非判定）'],['存档点的小憩',[E('heal'),E('shield'),E('recover_mind')],'Beer / Barrel（确定防护）']]],
 [14,'雪山里的发明家','cool',6,['zhiheng','即兴发明','pedro','零件再利用'],[['温暖雪洞G',[E('shield',2,'target'),E('draw')],'Barrel（保护思想）'],['便携连发装置',[E('extra_attacks',2),E('range')],'诸葛连弩 / Volcanic']]],
 [15,'山林之间的武者','warm',6,['longdan_def','百战不殆·守','wusheng','百战不殆·攻'],[['定海神针',[E('double_defense'),E('range',2)],'无双 / Scope（非贯石斧强制命中）'],['棍术演练',[E('attack',1,'target','warm'),E('shield')],'杀 / 防守整备']]],
 [16,'挺身而出的猎手','warm',6,['kurou','最强的自负','wine','全力挥击'],[['熊熊本垒打',[E('double_defense'),E('attack_bonus')],'Slab the Killer / 酒'],['猎手的战前整备',[E('draw',2),E('shield')],'Stagecoach / Barrel']]],
 [21,'让大家露出笑容','warm',6,['qingnang','贴心护理','rest','照顾者的休息'],[['让你露出笑容',[E('heal'),E('heal',1,'target'),E('draw',1,'target')],'结姻（共同恢复，不限性别）'],['专属放松疗程',[E('heal',2,'target'),E('shield',1,'target')],'青囊']]],
 [22,'一丝不苟的老师','cool',5,['yiji','从挫折中学习','supply','因材施教'],[['下一课开始',[E('draw',2,'target'),E('recover_mind')],'遗计（教学支援）'],['探险队的教科书',[E('scry',3),E('draw')],'观星 / 无中生有']]],
 [24,'真实自我的偶像','warm',5,['yingzi','真实的魅力','rende','一起重新开始'],[['一起重新成为偶像',[E('draw'),E('draw',2,'target')],'遗计 / Stagecoach'],['聚光灯下的勇气',[E('shield'),E('extra_attacks')],'Volcanic（舞台进攻改编）']]],
 [25,'可靠的舞台领队','cool',6,['yinghun','领队的担当','guard','队伍集合'],[['围在一起吧',[E('heal',1,'target'),E('shield',2,'target')],'Beer（非濒死救援）'],['领队的特别练习',[E('give_hand',1,'target'),E('draw',2)],'仁德 / 制衡']]],
 [26,'暖和的睡床','cool',6,['steadfast','安全的睡床','rest','再睡一会'],[['还没到起床时间',[E('heal'),E('shield',2)],'Beer / Barrel（无距离加成）'],['和伙伴分享的鱼',[E('give_hand',1,'target'),E('heal',1,'target'),E('draw')],'仁德']]],
 [27,'岩间跃动的节拍','warm',5,['press','摇滚突击','burst','永不停拍'],[['岩间跃动',[E('range',2),E('extra_attacks')],'马术 / 咆哮'],['不服输的安可',[E('draw_to',3),E('shield')],'连营（补牌改编）']]],
 [28,'穿水而来的援手','cool',6,['supply','可靠的补位','qingnang','救援的速度'],[['穿水而来的援手',[E('heal',1,'target'),E('draw',2,'target')],'青囊（非急救窗口）'],['即刻送达',[E('give_hand',2,'target'),E('draw'),E('range')],'仁德 / 马术']]],
 [31,'水边温柔的守护者','warm',6,['ganglie','温柔的威严','guard','水域警戒'],[['水边防卫线',[E('shield',2,'target'),E('draw')],'Barrel（确定护盾，无判定）'],['别欺负我的朋友',[E('attack',1,'target','warm'),E('shield',1,'target')],'杀（进攻后给目标护盾的克制反击）']]],
 [42,'响彻公园的歌声','warm',5,['yingzi','清晨开嗓','jizhi','传出去的心意'],[['请听我的歌',[E('draw'),E('draw',2,'target')],'Stagecoach（双人分享，非五谷选牌）'],['悠远的歌声',[E('range',3),E('attack',1,'target','warm')],'BANG!（单目标，非Gatling）']]],
 [44,'沙下的发现者','cool',6,['jesse','沙下的发现','refill','下一件有趣的事'],[['凉爽的洞穴',[E('shield',2),E('draw')],'Barrel（保护思想）'],['挖到了什么',[E('steal_hand',1,'target'),E('draw_discard')],'Panic! / Pedro Ramirez']]],
 [45,'遗迹的探究者','cool',6,['pedro','遗迹探究者','survey','热源辨路'],[['迷宫的正确道路',[E('scry',3),E('draw',2)],'观星'],['被遗忘的藏品',[E('draw_discard',2),E('recover_mind')],'Pedro Ramirez']]],
 [46,'认真试错的工匠','cool',6,['zhiheng','试错工匠','jizhi','施工的新灵感'],[['梦幻般的水坝',[E('shield',3,'target')],'Barrel（确定防护）'],['施工备料',[E('draw_discard',2),E('draw')],'Pedro Ramirez / Stagecoach']]],
 [47,'地下通道的开拓者','warm',6,['jesse','地下通道','rende','重要的家人'],[['温暖的地洞',[E('heal'),E('heal',1,'target'),E('recover_mind')],'桃园结义（双人改编）'],['挖掘作战',[E('discard_hand',1,'target'),E('range',2)],'Cat Balou / Scope']]],
 [51,'白色的守护誓言','cool',6,['elgringo','守护的尊严','guard','厚重防线'],[['白色闪光',[E('shield'),E('attack',1,'target','cool')],'BANG! / Barrel'],['并肩的约定',[E('give_hand',1,'target'),E('shield',2,'target')],'仁德（守护改编）']]],
 [52,'隐身的忍者','cool',5,['qixi','隐身的秘诀','qingguo','融入景色'],[['纸手里剑',[E('discard_hand',2,'target')],'Cat Balou / 过河拆桥（仅随机手牌）'],['静默潜入',[E('steal_hand',1,'target'),E('shield')],'Panic! / 潜行保护']]],
 [53,'耐心等待的一击','cool',6,['wushuang','超集中','mashu','远望'],[['静止的狙击点',[E('range',3),E('attack_bonus')],'Scope / 酒'],['终于抓到你了',[E('attack',1,'target','cool'),E('discard_hand',1,'target')],'杀 / Cat Balou（拆牌不要求命中）']]],
 [59,'善于包抄的讲述者','cool',5,['tuxi','灰狼的包抄','mashu','追踪步调'],[['远嚎',[E('draw',2,'target'),E('attack_bonus')],'遗计 / 酒（协作思想）'],['下一页的悬念',[E('scry',3),E('draw'),E('shield')],'观星 / 防守']]],
 [61,'探险队的协作能手','warm',6,['bart','临场修正','zhiheng','重新编排计划'],[['连携才是力量',[E('draw'),E('draw',1,'target'),E('shield',1,'target')],'遗计（无连环传伤）'],['队形已经完成',[E('extra_attacks'),E('range'),E('draw')],'咆哮 / 马术']]],
 [69,'风一般的挑战者','warm',5,['mashu','地上最快','burst','加速的节拍'],[['瞬间冲刺',[E('range',3),E('attack',1,'target','warm')],'神速（仍有范围，无跳阶段）'],['短跑后的休息',[E('heal'),E('draw',2)],'Beer / Stagecoach']]],
 [98,'镇守大地的四神','cool',6,['steadfast','不动的守护','guard','坚定结界'],[['多重玄武结界',[E('shield',3,'target'),E('draw')],'Barrel（确定护盾）'],['大地的回声',[E('shield',2),E('recover_mind',2)],'心象回收 / 防守',true]]],
 [99,'清水的救济者','cool',6,['guanxing','清水映照','rest','涓流的恩惠'],[['青龙的救济',[E('heal'),E('heal',1,'target'),E('recover_mind')],'Saloon（双人改编）'],['澄清的水镜',[E('scry',3),E('draw',2),E('shield')],'观星（非洛神判定）',true]]],
 [100,'疾风中的西方守护者','warm',5,['paoxiao','疾风连击','mashu','西方疾行'],[['疾风·白虎拳',[E('range',2),E('attack',1,'target','warm'),E('attack',1,'target','warm')],'马术 / BANG!（两次独立可防御攻击）'],['武艺的真髓',[E('heal',2),E('draw',2)],'Beer / Stagecoach（恢复再整备，无状态净化）',true]]],
 [101,'督促精进的南方守护者','warm',5,['jizhi','每日精进','wine','燃起斗志'],[['天来净化之火',[E('attack',1,'target','warm'),E('discard_hand',1,'target'),E('recover_mind')],'杀 / 过河拆桥（拆牌不要求命中，无异常驱散）'],['每日精进的约定',[E('give_hand',1,'target'),E('draw',2),E('attack_bonus')],'仁德 / 制衡 / 酒（分享资源与蓄势）',true]]],
 [322,'奔向未知的朋友','warm',6,['lianying','狩猎还没结束','burst','下一次跳跃'],[['超级草原利爪',[E('extra_attacks',2),E('range',2)],'咆哮 / Volcanic'],['好厉害呀',[E('draw_to',3),E('recover_mind')],'连营（低手牌补充）',true]]],
];
const base=['attack_cool','attack_cool','attack_warm','attack_warm','evade','haste','evade','miracle','mana','life','amplify','energy','recover'];
const presets=[],mindTemplates=[],arts=[],characterNotes={};
const fourGodDirections={98:'北方',99:'东方',100:'西方',101:'南方'};
const finalReasons={
  13:'长期游戏形成预判和闪避本领，以黑色手牌转化防御表现看穿招式，再用低资源补牌维持挑战。',
  27:'先迎敌且不轻易中断节奏：发动攻击时打乱对方手牌，再准备额外攻击额度，持续施加压力。',
  46:'拆建与改造保留经验，以弃旧摸新表现试作重整，以使用事件后的摸牌表现施工灵感；不从装备区回收。',
  99:'清水流动与循序救济对应梳理未来牌序、休息时恢复，以及给伙伴的共同治疗。',
  100:'爽朗而直接的西方守护者以疾风、高速机动与多段白虎拳开路，连续攻击额度和射程表现她的迅捷武艺；恢复再整备保留原等待技的韧性。',
  101:'南方守护者要求持续精进而非只依赖天赋，使用事件获得新资源、投入手牌蓄势，配合可防御火焰与分享训练资源，表现受控火力和严厉的关心。'
};
for(const [pageId,title,color,hp,skills,cards] of rows){
 const source=research.characters.find(c=>+c.pageId===pageId);if(!source)throw Error('Missing character '+pageId);
 const id=source.id;
 const actualSkills=[0,2].map(i=>({...structuredClone(lookup[skills[i]].skill),name:skills[i+1]}));
 const character={id,name:source.name,title,series:'动物朋友3',color,hp,art:id,flipColor:null,skills:actualSkills};
 const deck=base.map((type,i)=>({rank:i+1,type}));
 cards.forEach(([name,effects,reference,bound],i)=>{
  const card={name,series:character.series,fallback:i?'mana':'recover',effects,...(bound?{characterId:id}:{})};
  const template={id:`${id}_mind_${i+1}`,name,series:character.series,reference,description:`${source.name}主题心象。${bound?'仅同系列且角色编号匹配时生效。':'同系列角色可以共享构筑。'}具体规则以效果积木为准；借鉴来源的改编范围见内容手册。`,tags:[source.name,...new Set(effects.map(e=>e.op))],card,...(bound?{characterId:id}:{})};
  mindTemplates.push(template);deck[i?10:4]={rank:i?11:5,type:'custom',custom:structuredClone(card)};
 });
 presets.push({id:'preset_'+id,rulesVersion:'0.2.0-alpha',name:'加帕里·'+source.name,character,deck});
 arts.push({id,name:source.name,url:`assets/characters/${id}.png`});
 characterNotes[id]={summary:(fourGodDirections[pageId]?`四神之一，${fourGodDirections[pageId]}守护者。`:'')+source.summary,reason:finalReasons[pageId]||source.recommendedSkill.reason,sourceUrl:source.sourceUrl,sourcePath:source.dossier.replace(/^D:\/ProjectK\//,''),skillReferences:[lookup[skills[0]].reference,lookup[skills[2]].reference],cardReferences:cards.map(c=>c[2]),skillTemplateIds:[skills[0],skills[2]],mindTemplateIds:cards.map((_,i)=>`${id}_mind_${i+1}`)};
}
const counts={characters:presets.length,mindCards:mindTemplates.length,skillTemplates:skillTemplates.length,boundCards:mindTemplates.filter(t=>t.card.characterId).length};
const result={version:'0.2.0-alpha',contentPacks:[{id:'kf3-classics-01',name:'加帕里 · 经典交响',description:`${counts.characters}位朋友、${counts.mindCards}张主题心象、${counts.skillTemplates}组可拆解技能范式；其中${counts.boundCards}张心象额外绑定角色。冷暖对抗可在同IP中对战；系列对抗请加入其他IP角色。`,characterIds:presets.map(p=>p.character.id),sources:[bang,sgs,'https://sandstar.site/kf3_db/']}],presets,skillTemplates,mindTemplates,arts,characterNotes};
fs.mkdirSync(path.join(root,'src/content'),{recursive:true});
fs.writeFileSync(path.join(root,'src/content/kf3-classics.json'),JSON.stringify(result,null,2)+'\n');
const guide=[
 '# 加帕里 · 经典交响','',
 `本批包含 ${counts.characters} 位角色、${counts.mindCards} 张心象和 ${counts.skillTemplates} 组命名拼图。全部通过结构化效果解释器执行，不按人物 ID 编写隐藏特例。`,'',
 '角色资料来自 ProjectK 归档；图像由内置 image_gen 参照原立绘与人设逐位重绘。技能名与风味经过本项目改编，不是原作技能的逐字复刻。','',
 '## 角色与搭配','','| 角色 | 人物技能（借鉴） | 心象牌 |','| --- | --- | --- |',
 ...presets.map(p=>`| ${p.character.name} | ${p.character.skills.map((s,i)=>s.name+'（'+characterNotes[p.character.id].skillReferences[i]+'）').join(' / ')} | ${p.deck.filter(c=>c.type==='custom').map(c=>c.custom.name+(c.custom.characterId?'〔绑定角色〕':'')).join(' / ')} |`),'',
 '## 四神的分工','',
 '四神已齐备：玄武（0098）负责防护与回收，青龙（0099）负责观星与救济，白虎（0100）负责疾风连续攻势，朱雀（0101）负责精进与蓄势。白虎是ビャッコ，不是孟加拉白虎。白虎、朱雀均为暖色、5点体力，继续复用现有命名拼图。','',
 '| 追加角色 | 人物技能 | 同 IP 心象 | 额外角色绑定心象 |','| --- | --- | --- | --- |',
 '| 白虎 | 疾风连击：回合开始增加2次普通攻击额度；西方疾行：回合开始攻击范围+1 | 疾风·白虎拳：范围+2，再对同一目标依次进行两次1点暖色虚拟攻击 | 武艺的真髓：回复自己2点，摸2张 |',
 '| 朱雀 | 每日精进：使用事件后摸1张；燃起斗志：弃1张手牌，本回合攻击伤害+1 | 天来净化之火：1点暖色虚拟攻击，之后随机弃目标1张手牌并回收自己1张已耗心象 | 每日精进的约定：赠另一角色1张手牌，自己摸2张并获得本回合攻击伤害+1 |','',
 '各技能仍每个全局回合至多一次。白虎的两次虚拟攻击分别等待防御、遵守范围与颜色，不消耗普通攻击额度；第二次攻击若目标已退场或不再合法则失效。朱雀的后续拆牌不要求攻击命中；净化仅是资源清理与心象恢复的风味表达，不驱散异常、不附加持续灼烧。分享心象须在支付该心象后仍有1张可赠手牌。','',
 '## 创作拼图与改编差异','','| 拼图 ID | 范式 | 差异与边界 |','| --- | --- | --- |',
 ...skillTemplates.map(t=>`| \`${t.id}\` | ${t.reference} | ${t.description} |`),'',
 '## 心象的使用范围','',
 `所有主题牌要求使用者系列为“动物朋友3”。${counts.boundCards}张标注绑定的牌还检查角色编号；改名不改变角色编号。非匹配使用者按卡牌的 fallback 类型使用，经过赠牌、偷牌、装备后仍保持原实体归属。角色编号是创作标识，不是权限或稀有度凭证。`,'',
 `每位预设提供两张主题牌，创作者可从${counts.mindCards}张库中替换其他卡位；仍必须 A～K 各一张、最多4张限定牌、人物预算18、限定总预算24、单卡12。禁止把无双当作不可响应伤害：此包的双重防御仍逐张响应，最后成功才触发防御收益。`,'',
 '## 心象牌的改编口径','','牌名仅提供风味。共享治疗仅作用自己和选中的一人，不是全场桃园；目标取牌/弃牌是随机手牌，不包含装备区；范围加成仍为有限数值；先攻再拆牌不要求命中；歌声攻击为单目标，不是Gatling；没有判定的护盾、排序与摸牌不标为八卦/洛神原机制。具体效果由工坊生成说明逐项显示。','',
 '## 来源与重建','',`- [BANG! 官方基础规则](${bang})`,`- [三国杀官方武将资料](${sgs})`,
 `- ${counts.characters}个角色逐项证据见 [选角研究](KF3_ROSTER_RESEARCH.md) 与 \`kf3-research.json\`。`,
 '- 每张图的实际提示词、参考路径、生成结果路径见 `art-prompts-part1.json`、`art-prompts-part2.json`；四神追加见 `art-prompts-four-gods.json`。',
 '- 编辑 `tools/build-kf3-content.mjs` 的命名拼图及角色搭配，再运行 `node tools/build-kf3-content.mjs`。角色、心象、技能模板和绑定牌统计从实际数组计算；日常网页创作不需要 Node 或 ProjectK。',
 '- 输出 `src/content/kf3-classics.json` 随项目部署，无运行时 ProjectK 依赖。',''
];
fs.writeFileSync(path.join(root,'docs/content/KF3_CLASSICS.md'),guide.join('\n'));
console.log(`Built ${counts.characters} characters, ${counts.mindCards} mind cards, ${counts.skillTemplates} skill templates, ${counts.boundCards} bound cards.`);
