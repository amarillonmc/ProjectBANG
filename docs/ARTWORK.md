# 图像资源

人物与场景图均使用内置 image_gen 工具生成，发布包直接包含 PNG；部署者不需要 API Key，游戏运行不调用图像生成接口。原有四位通用人物为项目原创。本次新增 38 位《动物朋友3》角色卡面，依据 ProjectK 保存的默认立绘与角色档案逐位 AI 重绘，属于该 IP 角色的二次创作，并非原创角色或原作官方卡图。《BANG!》《三国杀》提供玩法灵感，不使用其卡牌插画。

## 原有四位人物图集

输出：`public/assets/mind-atlas.png`。2×2 四象限图集，左上冷色档案员、右上暖色旅人、左下无色演者、右下绿色工程师。前端按象限选取插画，character.art 的数字 0～3 分别对应这四个位置；自创角色仍可从立绘库选择这些通用图。当前网页不提供用户上传图片或在线 AI 生图。

完整生成提示词：

```text
Use case: stylized-concept
Asset type: original character portrait atlas for the Chinese tabletop web game 时空并错. Primary request: one square bitmap atlas divided into EXACTLY four equally sized rectangular portrait panels arranged in a 2x2 grid, no gutters or borders. Each quadrant is a complete different original adult character waist-up. Top left: a thoughtful young adult female archivist with short silver hair and deep teal coat holding a crystalline blue open book, winter blue archive background. Top right: a confident adult male wandering duelist with messy dark auburn hair and coral scarf, holding a glowing ember, warm red sunset background. Bottom left: an enigmatic androgynous adult theatrical performer with a porcelain half mask and white coat, monochrome indigo-gray mirrored stage. Bottom right: a composed adult female engineer with dark bob hair and olive flight jacket adjusting a small brass mechanical bird, sage green greenhouse workshop. Style: sophisticated hand-painted editorial fantasy card illustration, textured gouache, dramatic graphic silhouettes, fine ink details, restrained anime influence, beautiful cool/warm complementary palette, visible paper grain. Every face well within own quadrant. No words, numbers, watermarks, UI, branding or existing franchise characters. Final art polished and legible at small card sizes.
```

## 动物朋友3：38 张独立重绘卡面

输出目录：`public/assets/characters/`，文件采用稳定角色 ID，例如 `kf3_0001.png`（豺狗）、`kf3_0042.png`（朱鹮）、`kf3_0322.png`（薮猫）。38 张均为独立生成的 1024×1536 PNG，2:3 竖幅；没有从多人图集中裁切，也没有在运行时读取 ProjectK 图片。

每张图先检查对应默认立绘，再结合档案中的性格、能力设计新姿态与场景。统一采用细墨线、柔和上色、浅象牙色高光、青绿阴影、珊瑚色点缀及暖金色光线；人物身份特征、耳朵、发型与默认服装参考原图。画面不含卡框、文字或水印，牌名和规则由网页绘制。朱鹮与薮猫另作留顶空修订，保留完整头羽/长耳。

原始参考图位于 ProjectK 的 `data/friend_dossiers/assets/outfit_first/{pageId}.png`，人设与来源索引见 [选角研究](content/KF3_ROSTER_RESEARCH.md) 和 [结构化档案](content/kf3-research.json)。少量档案外貌文字与默认图不同，已在研究补记与提示词中记录，画面身份以默认图为准。只采用普通形态，不把 HC、外部联动或临时变身混作独立角色。

完整提示词和可追溯生成记录：

- [art-prompts-part1.json](content/art-prompts-part1.json)：24 张，含参考路径、提示词和原生生成输出路径。
- [art-prompts-part2.json](content/art-prompts-part2.json)：首批另 12 张，含同类信息、修订记录、最终项目路径、尺寸与 SHA256。
- [art-prompts-four-gods.json](content/art-prompts-four-gods.json)：追加的白虎（0100）与朱雀（0101）两张，沿用相同规格，含提示词、参考与原生输出路径及 SHA256；与玄武、青龙补齐四神。

提示词中的原生输出路径用于追溯，不是部署依赖。最终图已复制到项目内，生成器默认目录中的原文件保留。无需另行下载远程素材。

### 立绘目录与前端使用

`src/content/kf3-classics.json` 的 `arts` 条目包含 `{id,name,url}`，例如 `{id:"kf3_0042",name:"朱鹮",url:"assets/characters/kf3_0042.png"}`。构筑仅保存 `character.art: "kf3_0042"`，前端按目录精确匹配，并通过统一画像组件用于角色库、人物详情、工坊预览、房间席位与对战。

立绘 ID 与 character.id 分开：借用一张立绘不会自动取得该角色的技能或专属绑定。服务端只接受形状为 `[a-z][a-z0-9_]{0,63}` 的字符串或旧数字 0～3；不是任意图片 URL。未知 ID 在客户端回退到通用图。已保存的旧数字图集构筑继续显示原象限。

## 心象场景

输出：`public/assets/mindscape.png`，用于首页与对战氛围背景。

完整生成提示词：

```text
Use case: stylized-concept
Asset type: wide backdrop illustration for an original Chinese fantasy card-game website, no UI.
Primary request: an evocative imaginary landscape where a blue crystalline archive and a warm sunlit ruined city overlap, a small floating circular stone arena with a glowing ink-blue and coral dual-color geometric core in the foreground, swirling loose blank playing cards and fragments of memory in the air, ancient shelves become distant architecture. Hand-painted editorial fantasy gouache and ink on ivory textured paper, sophisticated restrained palette of storm blue, muted coral, old gold and pale sage. Wide panoramic composition with architecture concentrated in right two thirds, subtle pale fog and space on the left, soft daylight, inviting yet mysterious. Rich craft and small ink details. No characters, no writing, no words, no numbers, no logos, no watermarks, no existing franchise symbols. Landscape aspect ratio around 3:2.
```

## 替换

保持文件路径即可替换背景或现有独立人物 PNG；人物图集仍需保持四个等大象限与现有顺序。添加独立人物图时，在内容包 arts 目录登记 ID、显示名和本地 URL，并让构筑 character.art 使用对应 ID，无需为每个人物写 CSS 象限选择器。新增图片遵循同样的无字竖幅构图和身份复核流程，并追加实际提示词、参考来源及输出记录。发布包包含全部 PNG，部署时不依赖远程素材、外部字体、ProjectK 或生成服务。
