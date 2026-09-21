<?php
namespace Imaginary;

/** Bundled, reviewed data. User imports never write this file or execute source. */
final class ContentPack
{
    public static function data(): array
    {
        static $data;
        if ($data === null) {
            $text = file_get_contents(__DIR__ . '/content/kf3-classics.json');
            $data = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
            foreach (['presets','skillTemplates','mindTemplates','arts','characterNotes','contentPacks'] as $key) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    throw new \RuntimeException('内置内容包缺少字段：' . $key);
                }
            }
        }
        return $data;
    }
}
