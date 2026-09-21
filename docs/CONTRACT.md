# 实现契约

当前规则版本：`0.2.0-alpha`。运行环境兼容 PHP 7.4+，部署建议 PHP 8.2+；无 Composer、Node 或常驻后台服务要求。网站根目录为 `public/`，`src/`、`var/`、`config.php` 不公开。界面使用 UTF-8 中文，PHP 命名空间为 `Imaginary`。

## 目录、词表与内容包

核心文件是 `src/SkillBlocks.php`、`src/Catalog.php`、`src/ContentPack.php`、`src/Rules.php`、`src/Engine.php`。技能词表以 `SkillBlocks::metadata()` 为单一来源；预算与后端生成说明读同一份元数据，前端不应另维护不一致的白名单。

`Catalog::all()` 返回：

| 字段 | 内容 |
| --- | --- |
| `rulesVersion` | 当前规则版本 |
| `cards` | 普通牌类型 ID → `{id,name,kind,tags,description}` |
| `mindOptions` | 每个点数允许的普通心象类型 |
| `characters` / `presets` | 人物列表 / 可直接使用的完整构筑 |
| `blocks` | triggers、conditions、effects、effectMeta、targets、conversions、limits |
| `limits` | 技能数、效果数、限定牌数和各项预算 |
| `modes` | color / series 两个对局模式 |
| `contentPacks` | 内容包名称、说明、characterIds、来源列表 |
| `skillTemplates` | 命名技能模板，实际技能在 `skill` 字段 |
| `mindTemplates` | 命名心象模板，实际限定牌定义在 `card` 字段 |
| `arts` | `{id,name,url}` 独立人物图片目录 |
| `characterNotes` | 以 character.id 索引的人设摘要、搭配原因和来源 |

`src/content/kf3-classics.json` 包含本次 38 个预设、76 张心象、35 组命名技能拼图和 38 张独立图片引用，与原有 4 个通用预设合并。每位新人物两项技能、两张主题心象，其中 8 张心象额外绑定角色。白虎（kf3_0100）与朱雀（kf3_0101）加入后四神齐备，继续复用已有模板。玩法差异见 [内容手册](content/KF3_CLASSICS.md)。内容由 `tools/build-kf3-content.mjs` 生成，运行网站只读取随项目发布的 JSON，不依赖 ProjectK。

模板是普通数据的复制来源，不是解释器指令。构筑中不会保存模板调用、宏引用或嵌套递归；用户导入不能写入服务端内容包，也不能执行源代码。

## 构筑与验证

`Rules::validateBuild(array $build): array` 返回规范化构筑，非法数据抛出 `InvalidArgumentException`。未知字段、错误类型、数组形状、目标、数值、费用、版本和预算都由服务端校验。用户文本仅用于显示，不作为脚本、普通牌类型或规则名称执行。

```text
Build {
  id?, name, rulesVersion?,
  character: Character,
  deck: DeckEntry[13]
}
Character {
  id, name, title, series,
  color: cool | warm | neutral,
  hp: integer 4..7,
  art: integer 0..3 | art ID string,
  flipColor: null | cool | warm,
  skills: Skill[0..2]
}
Skill {
  name, trigger, condition,
  cost: {hand: integer 0..2, mind: integer 0..2},
  limit: integer 1..2,
  effects: Effect[],
  conversion?: {from, to}
}
Effect {
  op, target: self | target,
  amount: integer, color: cool | warm | neutral
}
DeckEntry {
  rank: integer 1..13,
  type: allowed normal type | custom,
  custom?: {name, series, fallback, effects, characterId?}
}
```

13 个点数必须完整且唯一，`deck` 数组次序从牌顶到牌底，验证不会按点数排序。普通心象只能采用该点数的 `mindOptions`；最多 4 张限定牌，每张 1～3 个效果。限定牌的 fallback 可选任何已定义普通牌类型。非限定条目不得携带 custom。

`character.art` 的字符串形状为 `[a-z][a-z0-9_]{0,63}`，不是 URL。客户端按它查询 `catalog.arts`，例如 `kf3_0042` 对应 `assets/characters/kf3_0042.png`；没有目录匹配时回退到通用图。数字 0～3 继续表示原图集四象限。人物 ID、显示名称和立绘 ID 是独立字段。

`custom.series` 匹配当前使用者系列，且可选 `custom.characterId` 匹配其人物 ID 时，自定义效果才生效；否则应用 fallback。角色编号是可编辑创作标识，不是身份授权。实体卡的普通/心象来源、UID 和原心象所有者不因失配、赠送、偷取、转化或装备而丢失。

缺少版本或携带 `0.1.0-alpha` 的旧构筑可进入当前校验；规范化结果统一写 `0.2.0-alpha`，缺省次数为 1。未知版本拒绝，旧图集编号兼容。保存/导入是校验与规范化，不是按卡名重新解释或静默排序；已开局游戏保留当时的构筑快照。旧房间每技能“已用回合号”的整数存档由引擎读取为该回合已使用一次。

新对局自身也持久化 `rulesVersion`。缺省版本或 `0.1.0-alpha` 的游戏状态由 act/tick 显式迁移，补齐新增字段，保留实体牌、伤害、次数与构筑快照，记录 `migratedFromRulesVersion` 和单次日志；非法动作连同迁移一起回滚。只读 view 返回存档的真实 `rulesVersion`、当前 `engineRulesVersion` 和布尔 `rulesUpgradePending`，不在读取时暗改状态。未知对局版本统一拒绝。

`Rules::budget()` 返回 `used,max,character,characterMax,custom,customMax,cards,warnings`。上限为人物 18、限定合计 24、单张 12。技能成本包含效果、自动触发加价、费用折减、次数乘数；转化基础成本依据来源，任意手牌转化高于指定牌型。无色 `damage`/`attack` 每点另计预算。`describeSkill()` 与 `describeEffects()` 输出同一规范语义。

## 技能词汇与结算

10 个 trigger：`active`、`turn_start`、`turn_end`、`after_damage`、`after_attack`、`on_defend`、`after_play_attack`、`after_play_event`、`hand_empty`、`convert`。

6 个 condition：`always`、`wounded`、`hand_low`、`hand_empty`、`hand_full`、`healthy`。次数按**全局回合**分别计数，limit 允许 1～2。自动技能检查关联目标、资源与心坏后执行，费用默认从左侧手牌/心象顶支付。

17 个 op 的完整目标约束和语义见 [SKILLS.md](SKILLS.md)，接口元数据中 `effectMeta[op]` 含 `targets,min,max,description,cost`：

| 范围 | op |
| --- | --- |
| 摸牌与转移 | draw、draw_to、draw_discard、steal_hand、discard_hand、give_hand |
| 生命与心象 | heal、damage、lose_health、recover_mind |
| 战斗 | shield、range、attack_bonus、extra_attacks、attack、double_defense |
| 私密牌序 | scry |

通常 amount 为 1～3；draw_to 为 1～5，double_defense 固定为 1。steal_hand/discard_hand/give_hand/attack 的 target 必须是另一存活角色；前三项中的偷弃只处理随机手牌，不包括公开装备。attack 还检查距离、颜色，允许响应且不消耗普通攻击额度；damage 没有攻击防御窗。lose_health 忽略护盾、不触发 after_damage，但仍处理濒死和胜负。护盾、范围、增伤、额外次数及双重防御在受益者下次自己的回合开始清空。

convert 的 effects 必须为空；普通技能必须包含 1～3 个效果且不能附 conversion。from 允许 attack、defense、red、black、hand；to 允许 attack_neutral、defense。来源按实体手牌及当前有效牌型检查，red/black 仅识别实际花色。先取出转化实体，再支付额外费用；次数照常计费，不能重复消耗同一实体。转化攻击在出牌阶段使用 play 并占普通攻击额度，也可在“潜能爆发”要求攻击时通过 respond 打出；转化防御使用 respond。

效果数组严格有序。attack 将剩余效果存入队列，等待攻击响应后继续；scry 将剩余效果保留到私密排序确认后继续。事件进入事件队列，限定心象也服从回合外目标的“攻守同盟”取消窗口。人物技能不因包含效果而自动成为事件。hand_empty 在行动及即时效果结算边界触发，不能在支付中途插入摸牌；turn_end 在必要弃牌完成后触发。

give_hand 在支付费用后检查选牌。selection 必须包含所有赠牌效果所需数量的不重复、现存手牌 UID；无 selection 时从左侧依次交付。攻击/事件等待期间牌可能被其他反应移走，待恢复的赠牌已无足量资源或指定实体时终止该组剩余效果，不让响应者困在失效选择上。尚未经过等待的非法主动操作整体回滚。

scry 暂时取出至多 amount 张普通牌，响应者必须按顺序提交全部 UID，第一张回到最上方，不支持放底。其他玩家视角没有这些牌、UID、顺序或选项。默认/超时响应保留原顺序；查看者退场或对局结束时未提交牌归还原牌序。双重防御每次成功只减一次需求；直到全部抵消才触发 on_defend，否则承受完整攻击伤害。

## 引擎调用与安全视角

- `Engine::create(array $players,string $mode): array`：2～6 个玩家，每项为 `{id,name,build,bot}`；mode 为 color 或 series。
- `Engine::act(array &$game,string $playerId,array $action): void`：检查席位、时机、期限、合法目标、次数、费用和牌序；失败恢复动作前状态。
- `Engine::view(array $game,string $playerId): array`：只接受参与者，返回安全视角。
- `Engine::tick(array &$game): bool`：推进过期等待/回合与有界机器人行动；`botStep()` 也使用合法动作。
- 每动作 96 个事件/效果、每回合 120 次主动行动、300 回合平局上限均用于保证有界执行。

游戏视角包含 rulesVersion、status、mode、turn、phase、turnNumber、deadline、serverTime、players、me、pending、legalActions、log、winner、deckCount、discardCount、draft、discardRequired。

公开 players 项包括人物档案、有效/最大体力、颜色系列、存活/心坏/翻面、标记、各区数量、已耗心象、装备/延时牌、护盾、范围、attackBonus、attacksRemaining。me 另含本人的 hand、mind、spent、skills；每项技能附 index、description、available、usesRemaining。不能直接序列化原始 game 给客户端。

实体牌包含 `uid,type,name,rank,suit?,origin:normal|mind,owner?,...`。pending 公开 kind、player、source、prompt；只有指定响应者收到私密 cards/count 和可操作 options，公开选牌 draft 例外。legalActions 为当前席位实际合法的动作候选，前端据此提供操作，不以客户端推演代替服务器检查。

主要动作：

```text
{type:"draw", mind:0|1|2}
{type:"play", card:uid, target?:id, mode?:string,
 costCard?:uid, selection?:[uid], conversion?:skillIndex, costCards?:[uid]}
{type:"skill", index:skillIndex, target?:id, costCards?:[uid], selection?:[uid]}
{type:"equip_use", card:uid, target?:id}
{type:"respond", choice:string, card?:uid, cards?:[uid], selection?:[uid],
 conversion?:skillIndex, costCards?:[uid]}
{type:"end"}
{type:"discard", cards:[uid]}
```

conversion 只接受整数技能下标且仅用于 play/respond。scry 响应为 `{type:"respond",choice:"confirm",cards:[全部UID按回顶顺序]}`，兼容 selection；省略列表使用原次序。不可提交重复、缺失或不在暂存区的 UID。双重防御每个响应只提交一张合法防御。

## HTTP API 与持久化

相关文件：`src/Store.php`、`src/bootstrap.php`、`public/api.php`、`config.example.php`、`var/.htaccess`。JSON POST 到 `api.php?action=...`；认证为 `Authorization: Bearer <guest token>`，不用 cookie。GET 只用于读取，JSON 请求不超过 128 KiB。

响应为 `{ok:true,data:{...}}` 或 `{ok:false,error:string}`。SQLite 默认位于 `var/game.sqlite`，支持 MySQL 配置与自动建表。服务器只存令牌哈希；写入检查所有者和房主权限，不提供公开用户/房间目录。房间六位码与访客身份共同使用。大厅不泄露其他人的有序心象，对局通过 Engine::view。事务/行锁串行化游戏更新；act 使用预期 revision 与 requestId 实现并发检查、幂等请求，异常回滚。

| action | 请求 / 结果 |
| --- | --- |
| guest | `{name}` → `{token,user:{id,name}}` |
| catalog | → Catalog::all() |
| me | → `{user,builds}` |
| save_build / validate_build | `{build}` → `{build,budget}` |
| delete_build | `{id}` → 空结果 |
| create_room | `{name,mode,buildId?,presetId?,allowCustom,turnSeconds?}` → 房间 |
| join_room | `{code,buildId?,presetId?}` → 房间 |
| room | `{code}` → 房间，并推进有界自动行动 |
| choose_build | `{code,buildId?,presetId?}` → 房间，仅开局前 |
| add_bot | `{code,presetId?}` → 房间，仅房主 |
| leave_room | `{code}`，仅大厅；房主离开关闭房间 |
| start | `{code}`，仅房主、至少两人且有可对抗阵营 |
| act | `{code,revision,requestId,action}` → 房间 |
| feedback | `{code?,text}` → 空结果 |
| export | `{code}` → 房间/事件记录，仅参与者；未结束时隐藏秘密 |

房间快照为 `{code,name,mode,hostId,status,allowCustom,revision,players,game}`；status 为 lobby/playing/finished，game 为 null 或安全视角。HTTP 错误采用 400/401/403/404/409/429 等。刷新重连需要原访客令牌。

## 前端与创作拼图

前端为 `public/index.html`、`public/assets/app.js`、`public/assets/style.css`，无框架/构建器。视图包含大厅、房间、工坊、角色库、规则和反馈；2 秒轮询，处理旧 revision、重连与服务端期限。用户内容通过安全文本/转义显示。

角色库可分页搜索并按系列过滤；仅载入角色会保留当前有序心象。工坊从服务端 blocks 绘制触发、条件、次数、转化和效果字段，从 arts 绘制立绘库。普通用户可选择、修改、收藏并复用模板；角色与 IP 心象可以分开创作。赠牌与技能费用分别选牌，scry 显示按点击次序形成的私密回顶顺序。

本地拼图库存于 `imaginary.puzzles.v1`，每类最多 100 项，不等同云端构筑保存。交换格式为：

```json
{
  "format": "imaginary-puzzles-v1",
  "rulesVersion": "0.2.0-alpha",
  "skillTemplates": [],
  "mindTemplates": []
}
```

技能模板载荷字段为 skill，心象模板为 card；可附 id、name、reference、description、tags 等展示元数据。导入逐项放入验证用构筑并请求 validate_build，全部通过后才加入收藏。模板在应用时深拷贝，仍须遵守实际作品的技能数、限定数与整体预算；不把本地内容注册成服务器脚本或递归宏。

## 规则范围

普通牌池固定 104 张；默认开局 5 张普通手牌、标准摸 2 张且可替换至多 2 张心象、默认每回合一次攻击/范围 1，新增额度按解释器增减。永续牌下个自己的回合成熟。防御属于防守，回避属于躲避。冷暖与系列两模式、心坏、翻面、体力、心象归属等基础裁定见 [RULES.md](RULES.md)。未实现的原版《BANG!》《三国杀》机制不能由同名文字取得，改编差异以 [KF3_CLASSICS.md](content/KF3_CLASSICS.md) 明示。
