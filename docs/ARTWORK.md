# 图像资源

所有人物与场景使用本次会话的内置 image_gen 工具生成，不需要部署者配置 API Key，游戏运行也不会请求任何图像生成接口。角色是为本项目创作的原创人物，不使用《三国杀》《BANG!》或其他商业角色原画。

## 人物图集

输出：`public/assets/mind-atlas.png`。2×2 四象限图集，左上冷色档案员、右上暖色旅人、左下无色演者、右下绿色工程师。前端按象限选取插画，自创角色可以选择其中一个头像。当前版本不提供用户上传图片或在线 AI 生图。

完整生成提示词：

```text
Use case: stylized-concept
Asset type: original character portrait atlas for the Chinese tabletop web game 时空并错. Primary request: one square bitmap atlas divided into EXACTLY four equally sized rectangular portrait panels arranged in a 2x2 grid, no gutters or borders. Each quadrant is a complete different original adult character waist-up. Top left: a thoughtful young adult female archivist with short silver hair and deep teal coat holding a crystalline blue open book, winter blue archive background. Top right: a confident adult male wandering duelist with messy dark auburn hair and coral scarf, holding a glowing ember, warm red sunset background. Bottom left: an enigmatic androgynous adult theatrical performer with a porcelain half mask and white coat, monochrome indigo-gray mirrored stage. Bottom right: a composed adult female engineer with dark bob hair and olive flight jacket adjusting a small brass mechanical bird, sage green greenhouse workshop. Style: sophisticated hand-painted editorial fantasy card illustration, textured gouache, dramatic graphic silhouettes, fine ink details, restrained anime influence, beautiful cool/warm complementary palette, visible paper grain. Every face well within own quadrant. No words, numbers, watermarks, UI, branding or existing franchise characters. Final art polished and legible at small card sizes.
```

## 心象场景

输出：`public/assets/mindscape.png`，用于首页与对战氛围背景。

完整生成提示词：

```text
Use case: stylized-concept
Asset type: wide backdrop illustration for an original Chinese fantasy card-game website, no UI.
Primary request: an evocative imaginary landscape where a blue crystalline archive and a warm sunlit ruined city overlap, a small floating circular stone arena with a glowing ink-blue and coral dual-color geometric core in the foreground, swirling loose blank playing cards and fragments of memory in the air, ancient shelves become distant architecture. Hand-painted editorial fantasy gouache and ink on ivory textured paper, sophisticated restrained palette of storm blue, muted coral, old gold and pale sage. Wide panoramic composition with architecture concentrated in right two thirds, subtle pale fog and space on the left, soft daylight, inviting yet mysterious. Rich craft and small ink details. No characters, no writing, no words, no numbers, no logos, no watermarks, no existing franchise symbols. Landscape aspect ratio around 3:2.
```

## 替换

保持文件路径即可替换背景。人物图集需保持四个等大象限和现有顺序；若改为独立人物文件，请同步修改 `public/assets/style.css` 中的画像选择器。发布包已包含原始 PNG，部署时不需要下载远程素材或字体。

