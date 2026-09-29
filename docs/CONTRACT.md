# 实现契约

当前规则版本：`0.5.0-alpha`。运行环境兼容 PHP 7.4+，部署建议 PHP 8.2+；无 Composer、Node 或常驻后台服务要求。网站根目录为 `public/`，`src/`、`var/`、`config.php` 不公开。界面使用 UTF-8 中文，PHP 命名空间为 `Imaginary`。

## 目录、词表与内容包

核心文件是 `src/SkillBlocks.php`、`src/Catalog.php`、`src/ContentPack.php`、`src/Rules.php`、`src/Engine.php`；`Mechanics.php`、`Events.php`、`DynamicSkills.php` 分担可保存的选择、事件窗口与动态技能。技能词表以 `SkillBlocks::metadata()` 为单一来源，服务器政策由 `RuleConfig` 从 `config.php` 的 `rules` 读取。预算与后端生成说明读同一份元数据，前端不应另维护不一致的白名单。

`Catalog::all()` 返回：

| 字段 | 内容 |
| --- | --- |
| `rulesVersion` | 当前规则版本 |
| `cards` | 普通牌类型 ID → `{id,name,kind,tags,description}` |
| `mindOptions` | 每个点数允许的普通心象类型 |
| `characters` / `presets` | 人物列表 / 可直接使用的完整构筑 |
| `blocks` | triggers、conditions、effects、effectMeta、equipmentSlots、equipmentEffects、targets、conversions、limits |
| `limits` | 技能数、效果数、限定牌数和各项预算 |
| `modes` | color / series 两个对局模式 |
| `contentPacks` | 内容包名称、说明、characterIds、来源列表 |
| `skillTemplates` | 命名技能模板，实际技能在 `skill` 字段 |
| `mindTemplates` | 命名心象模板，实际限定牌定义在 `card` 字段 |
| `arts` | `{id,name,url}` 独立人物图片目录 |
| `characterNotes` | 以 character.id 索引的人设摘要、搭配原因和来源 |

`src/content/kf3-classics.json` 包含本次 38 个预设、76 张心象、35 组命名技能拼图和 38 张独立图片引用，与原有 4 个通用预设合并。每位新人物两项技能、两张主题心象，其中 8 张心象额外绑定角色。白虎（kf3_0100）与朱雀（kf3_0101）加入后四神齐备，继续复用已有模板。玩法差异见 [内容手册](content/KF3_CLASSICS.md)。内容由 `tools/build-kf3-content.mjs` 生成，运行网站只读取随项目发布的 JSON，不依赖 ProjectK。

模板是普通数据的复制来源，不是解释器指令。构筑可保存受校验的有限效果树与获得技能定义，不能保存模板调用、递归宏或可执行代码；用户导入不能写入服务端内容包。三个内容包的 107 个技能模板之外，`SkillPuzzles.php` 提供 32 个通用机制示例，不增加预设角色。

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
  hp: integer 1..rules.maxHp,
  art: integer 0..3 | art ID string,
  flipColor: null | cool | warm,
  skills: Skill[0..rules.maxSkills]
}
Skill {
  name, key?, trigger, condition: string | Predicate,
  cost: {hand: integer | chosen | all, mind: integer, zone?: hand | hand_equipment},
  limit: integer 0..rules.unlimitedUses,
  limitScope?: turn | owner_turn | game,
  optional?: boolean,
  effects: Effect[],
  conversion?: {from, to, zone?, pile?, count?, match?}
}
Effect {
  op, target: allowed target reference,
  amount: integer | allowed dynamic expression, color: cool | warm | neutral,
  ...fields specifically allowed for this op
}
DeckEntry {
  rank: integer 1..13,
  type: allowed normal type | custom,
  maturityTurns?: integer,
  custom?: {name, series, fallback, effects, characterId?, kind?, slot?, maturityTurns?}
}
```

13 个点数必须完整且唯一，`deck` 数组次序从牌顶到牌底，验证不会按点数排序。普通心象只能采用该点数的 `mindOptions`；允许 13 张均为限定牌，各自效果树遵守 `maxEffects` 与最多 12 层嵌套的资源上限。限定牌的 fallback 可选任何已定义普通牌类型。非限定条目不得携带 custom。`maturityTurns` 仅用于延时基本牌及延时心象；拳击默认 1，其余当前延时牌默认 0，按持有者自身回合计数。

`character.art` 的字符串形状为 `[a-z][a-z0-9_]{0,63}`，不是 URL。客户端按它查询 `catalog.arts`，例如 `kf3_0042` 对应 `assets/characters/kf3_0042.png`；没有目录匹配时回退到通用图。数字 0～3 继续表示原图集四象限。人物 ID、显示名称和立绘 ID 是独立字段。

`custom.series` 匹配当前使用者系列，且可选 `custom.characterId` 匹配其人物 ID 时，自定义效果才生效；否则应用 fallback。角色编号是可编辑创作标识，不是身份授权。实体卡的普通/心象来源、UID 和原心象所有者不因失配、赠送、偷取、转化或装备而丢失。

缺少版本或携带 `0.1.0-alpha` 至 `0.4.0-alpha` 的旧构筑可进入当前校验；规范化结果统一写 `0.5.0-alpha`。已有显式次数保留，缺省次数为 0（不限）。未知版本拒绝，旧图集编号兼容。保存/导入是校验与规范化，不是按卡名重新解释或静默排序；已开局游戏保留当时的构筑快照。旧房间每技能“已用回合号”的整数存档由引擎读取为该回合已使用一次。

新对局自身也持久化 `rulesVersion`。缺省版本或 0.1～0.4 的游戏状态由 act/tick 显式迁移，补齐新增字段，保留实体牌、伤害、次数与构筑快照，记录 `migratedFromRulesVersion` 和单次日志；非法动作连同迁移一起回滚。旧延时基本牌未携带明确成熟参数且不是拳击时，迁移减去原来额外的一回合等待，重复迁移不再改动。只读 view 返回存档的真实 `rulesVersion`、当前 `engineRulesVersion` 和布尔 `rulesUpgradePending`，不在读取时暗改状态。未知对局版本统一拒绝。

`Rules::budget()` 返回 `used,max,character,characterMax,custom,customMax,cards,warnings`。默认上限为人物 18、限定合计 24、单张 12，均可配置。技能成本包含效果、自动触发加价、费用折减、次数乘数；转化基础成本依据来源，任意手牌转化高于指定牌型。无色 `damage`/`attack` 每点另计预算。明确强制的自身负面可计负预算，可选负面、可能为零的动态负面不能刷预算；限定牌的负面只抵扣同一张牌的收益，不抵扣其他牌。动态获得的完整技能也参与计价。详见 [SKILLS.md](SKILLS.md)。

## 技能词汇与结算

目前有 26 个触发时机、26 个简单条件、73 个效果。完整目标约束与结构参数见 [SKILLS.md](SKILLS.md)，`effectMeta[op]` 含 `targets,min,max,description,cost,equipmentOnly,passiveOnly`。普通数值上限读 `maxAmount`；布尔或控制流程积木固定为 1。动态数值只接受白名单表达式，不能执行代码。

`limit=0` 表示不限设计次数，触发/主动/转化的实际次数受 `unlimitedUses` 保护（默认 98）。`limitScope` 可按全局回合、自身回合周期、整局计数。`passive` 持续能力查询时生效，不消耗次数；`optional=false` 的触发技能自动执行，可选技能提供确认窗口。费用支持固定数量、任选数量、全部手牌，也可包含装备；实付数量在支付前绑定，不能把效果执行期间新获得的牌算作费用。

伤害窗口依次执行来源前置、旁观前置、目标前置、减伤/护盾、扣血、濒死、整次后置与逐点后置。事件框架保存来源、目标、数值和续接位置，可嵌套、保存重连；修改伤害的 op 只允许出现在前置伤害技能内。濒死技能、奇迹与冷暖翻面完成后才确定退场；死亡遗赠在清理牌区前执行，全局死亡监听在清理后执行，事件结束后才判断胜负。失去体力不是伤害，不继承外层伤害来源。

attack 检查距离与颜色，允许响应且不消耗普通攻击额度；damage 没有攻击防御窗。lose_health 忽略护盾、不触发 after_damage，但仍处理濒死和胜负。普通护盾、范围、增伤、额外次数及双重防御在受益者下次自己的回合开始清空；持续能力则根据当前有效技能重新计算。

convert 的 effects 必须为空；其他技能必须有至少一个效果且不能附 conversion。from 支持牌型、红黑和花色；来源可为手牌、装备或命名专属牌堆，count/match 支持多张同色/同花色素材，to 仍为 attack_neutral 或 defense。先取出实体素材，再支付额外费用，不能重复消耗同一实体。转化攻击在出牌阶段使用 play 并占普通攻击额度，也可在要求攻击的响应窗口通过 respond 打出；转化防御使用 respond。

gain_skill/lose_skill/seal_skill/copy_skill 按稳定标识操作人物技能，保存下标与用次，不因失去再获得而刷新限定技。同标识的原生、永久、不同临时来源分别保留；到期只移除对应来源。被移除的槽位仍计入服务器 maxSkills 资源上限，避免无限获取造成存档增长。动态技能状态与期限均持久化。

效果数组严格有序。attack 将剩余效果存入队列，等待攻击响应后继续；scry 将剩余效果保留到私密排序确认后继续。事件进入事件队列，事件心象也服从回合外目标的“攻守同盟”取消窗口。人物技能不因包含效果而自动成为事件。hand_empty 在行动及即时效果结算边界触发，不能在支付中途插入摸牌；turn_end 在必要弃牌完成后触发。

give_hand 在支付费用后检查选牌。selection 必须包含所有赠牌效果所需数量的不重复、现存手牌 UID；无 selection 时从左侧依次交付。攻击/事件等待期间牌可能被其他反应移走，待恢复的赠牌已无足量资源或指定实体时终止该组剩余效果，不让响应者困在失效选择上。尚未经过等待的非法主动操作整体回滚。

scry 暂时取出至多 amount 张普通牌，响应者必须按顺序提交全部 UID，第一张回到最上方，不支持放底。其他玩家视角没有这些牌、UID、顺序或选项。默认/超时响应保留原顺序；查看者退场或对局结束时未提交牌归还原牌序。双重防御每次成功只减一次需求；直到全部抵消才触发 on_defend，否则承受完整攻击伤害。

## 0.4 装备与私密窗口补充

三个内容包合计 104 位 IP 角色、208 张主题心象、107 组技能模板，另有四个基础示例。新增内容源为 src/content/adventure-king.json，生成器为 tools/build-adventure-content.mjs。

custom.kind 缺省为 event；equipment 要求 slot 为 weapon / armor / gadget。equipmentEffects 返回槽位白名单；equipmentOnly 禁止把持续加成放进人物技能或事件，重复装备 op 被拒绝。装备只对自己使用，进入 equipment 区，触发 after_equip / after_play_card，不触发 after_play_event，也不进入事件取消窗口。同槽替换走既有失装队列。

普通技能新增 inspect_hand、scry_mind、cycle_mind、draw_mind、recall_equipment、break_shield；装备专用 equip_range、equip_damage、equip_shield、equip_draw、equip_distance。范围与受攻距离在查询时汇总，增伤次数保存在玩家 equipmentDamageTurn 并与自己的 turns 比较，反复拆装不能刷新；周期收益在 beginTurn 清除旧加成后、人物 turn_start 前执行。

inspect_hand / scry_mind 的 pending.cards 是私密快照，实体留在手牌/心象原区。响应为 respond + choice: confirm；调序可附全量且不重复的 cards UID，第一张成为顶部。其他玩家不收到快照或选项。默认调查确认，默认心象保持原序，随后恢复剩余效果。现有 scry 仍使用普通牌临时保管区。

## 引擎调用与安全视角

- `Engine::create(array $players,string $mode): array`：2～6 个玩家，每项为 `{id,name,build,bot}`；mode 为 color 或 series。
- `Engine::act(array &$game,string $playerId,array $action): void`：检查席位、时机、期限、合法目标、次数、费用和牌序；失败恢复动作前状态。
- `Engine::view(array $game,string $playerId): array`：只接受参与者，返回安全视角。
- `Engine::tick(array &$game): bool`：推进过期等待/回合与有界机器人行动；`botStep()` 也使用合法动作。
- 默认每条结算链 4096 步、每回合 512 次主动行动、300 回合平局；读 `rules.maxResolutionSteps/maxActionsPerTurn/maxTurns`。结算链预算跨响应与重连保留，达到上限会结束剩余效果、归还临时保管牌并记录原因，不能用连续选择重置保险。

游戏视角包含 rulesVersion、status、mode、turn、phase、turnNumber、deadline、serverTime、players、me、pending、legalActions、log、winner、deckCount、discardCount、draft、discardRequired。

公开 players 项包括人物档案、有效/最大体力、颜色系列、存活/心坏/翻面、标记、各区数量、已耗心象、装备/延时牌、护盾、范围、attackBonus、attacksRemaining。命名专属牌堆公开张数；私密牌堆只向持有者展示牌面。me 另含本人的 hand、mind、spent、skills；技能附 index、description、disabled、available、usesRemaining，结构说明由前端根据同一技能定义生成。不能直接序列化原始 game、事件栈或私密选项给客户端。

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
| create_room | `{name,mode,buildId?,presetId?,allowCustom,turnSeconds?,bots?:[{buildId?,presetId?}],requestId?}` → 房间；提供 bots 时须为 1～5 项列表，逐项验证并原子创建 |
| join_room | `{code,buildId?,presetId?}` → 房间 |
| room | `{code}` → 房间，并推进有界自动行动 |
| choose_build | `{code,buildId?,presetId?}` → 房间，仅开局前 |
| add_bot | `{code,buildId?,presetId?}` → 房间，仅房主；仅可读取房主自己的保存构筑，遵守 allowCustom；未选择时自动选对手预设 |
| leave_room | `{code}`，仅大厅；房主离开关闭房间 |
| start | `{code}`，仅房主、至少两人且有可对抗阵营 |
| act | `{code,revision,requestId,action}` → 房间 |
| feedback | `{code?,text}` → 空结果 |
| export | `{code}` → 房间/事件记录，仅参与者；未结束时隐藏秘密 |

房间快照为 `{code,name,mode,hostId,status,allowCustom,revision,players,game}`；status 为 lobby/playing/finished，game 为 null 或安全视角。HTTP 错误采用 400/401/403/404/409/429 等。刷新重连需要原访客令牌。

## 前端与创作拼图

前端为 `public/index.html`、`public/assets/app.js`、`public/assets/style.css`，无框架/构建器。视图包含大厅、房间、工坊、角色库、规则和反馈；2 秒轮询，处理旧 revision、重连与服务端期限。用户内容通过安全文本/转义显示。

角色库可分页搜索并按系列过滤；仅载入角色会保留当前有序心象。工坊从服务端 blocks 绘制触发、条件、次数、转化和效果字段，从 arts 绘制立绘库；支持编辑嵌套分支与获得技能定义。角色库/牌桌可切换人话与结构说明并保存本地偏好，工坊同时展示两种说明。`skill-text.js` 负责客户端说明，服务端 `describeSkill/describeEffects` 仍提供安全视角描述。普通用户可选择、修改、收藏并复用模板；角色与 IP 心象可以分开创作。赠牌与技能费用分别选牌，scry 显示按点击次序形成的私密回顶顺序。

本地拼图库存于 `imaginary.puzzles.v1`，每类最多 100 项，不等同云端构筑保存。交换格式为：

```json
{
  "format": "imaginary-puzzles-v1",
  "rulesVersion": "0.5.0-alpha",
  "skillTemplates": [],
  "mindTemplates": []
}
```

技能模板载荷字段为 skill，心象模板为 card；可附 id、name、reference、description、tags 等展示元数据。导入逐项放入验证用构筑并请求 validate_build，全部通过后才加入收藏。模板在应用时深拷贝，仍须遵守实际作品的技能数、限定数与整体预算；不把本地内容注册成服务器脚本或递归宏。

## 规则范围

普通牌池固定 104 张；默认开局 5 张普通手牌、标准摸 2 张且可替换至多 2 张心象、默认每回合一次攻击/范围 1，新增额度按解释器增减。延时牌按 maturityTurns 成熟。防御属于防守，回避同时属于防守与躲避：可从手牌防御通常攻击（不摸牌），或卸除成熟装备防御并摸一张；拳击仍要求成熟装备回避。机器人按当前颜色过滤有害目标及会波及伙伴的群体行动，系列模式还避开同系列目标；真人动作和模式胜利条件不变。冷暖与系列两模式、心坏、翻面、体力、心象归属等基础裁定见 [RULES.md](RULES.md)。本轮按用户确认保留基础规则、尽量等价还原技能机制；不是完整三国杀模式。当前 OL 来源、能力与缺口见 [覆盖审计](content/SGS_OL_COVERAGE.md)。
