<?php
namespace Imaginary;

/** Bundled, reviewed data. User imports never write this file or execute source. */
final class ContentPack
{
    /** The default remains the first pack for existing tools and replay fixtures. */
    public static function data(string $id='kf3-classics'): array
    {
        static $packs=[];
        $files=['kf3-classics'=>'kf3-classics.json','magireco-expansions'=>'magireco-expansions.json','adventure-king'=>'adventure-king.json','vtuber-summons'=>'vtuber-summons.json'];
        if (!isset($files[$id])) throw new \InvalidArgumentException('未知内置内容包：'.$id);
        if (!isset($packs[$id])) {
            $text = file_get_contents(__DIR__ . '/content/' . $files[$id]);
            if ($text === false) throw new \RuntimeException('无法读取内置内容包：'.$id);
            $data = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
            foreach (['presets','skillTemplates','mindTemplates','arts','characterNotes','contentPacks'] as $key) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    throw new \RuntimeException('内置内容包 '.$id.' 缺少字段：' . $key);
                }
            }
            $packs[$id]=$data;
        }
        return $packs[$id];
    }

    /** Merge reviewed packs in stable order; never allow one pack to shadow another. */
    public static function all(): array
    {
        static $combined;
        if ($combined !== null) return $combined;
        $out=['presets'=>[],'skillTemplates'=>[],'mindTemplates'=>[],'arts'=>[],'characterNotes'=>[],'contentPacks'=>[]];
        $seen=[];
        foreach (['kf3-classics','magireco-expansions','adventure-king','vtuber-summons'] as $id) {
            $pack=self::data($id);
            foreach (array_keys($out) as $section) {
                foreach ($pack[$section] as $key=>$item) {
                    $identity=$section==='characterNotes'?$key:($item['id']??null);
                    if (!is_string($identity)||$identity===''||isset($seen[$section][$identity])) {
                        throw new \RuntimeException('内置内容编号缺失或重复：'.$section.' / '.(string)$identity);
                    }
                    $seen[$section][$identity]=true;
                    if ($section==='characterNotes') $out[$section][$identity]=$item;
                    else $out[$section][]=$item;
                }
            }
        }
        return $combined=$out;
    }
}
