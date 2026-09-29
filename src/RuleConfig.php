<?php
namespace Imaginary;

/** Server policy, shared by CLI, API, validator and interpreter. Never read from builds. */
final class RuleConfig
{
    private static $override = null;
    public static function defaults(): array
    {
        return ['characterBudget'=>18,'customBudget'=>24,'customCardBudget'=>12,
            'unlimitedUses'=>98,'maxSkills'=>98,'maxEffects'=>98,'maxAmount'=>98,'maxHp'=>98,
            'maxActionsPerTurn'=>512,'maxResolutionSteps'=>4096,'maxTurns'=>300,
            'unlimitedBudgetWeight'=>2,'negativeBudgetFloor'=>-12];
    }
    public static function configure(?array $rules): void
    {
        if($rules!==null) self::validate($rules);
        self::$override=$rules;
    }
    private static function validate(array $rules): void
    {
        foreach($rules as $key=>$value) {
            if(!array_key_exists($key,self::defaults())||!is_int($value)||($key==='negativeBudgetFloor'?($value>0||$value< -10000):($value<1||$value>100000))) {
                throw new \InvalidArgumentException('规则配置无效：'.$key);
            }
        }
    }
    public static function all(): array
    {
        if(self::$override!==null) return array_replace(self::defaults(),self::$override);
        static $saved;
        if($saved===null) {
            $file=dirname(__DIR__).'/config.php'; $local=is_file($file)?require $file:[];
            $rules=$local['rules']??[];
            if(!is_array($rules)) throw new \InvalidArgumentException('rules 配置必须是数组');
            self::validate($rules); $saved=array_replace(self::defaults(),$rules);
        }
        return $saved;
    }
    public static function get(string $key): int { return self::all()[$key]; }
    public static function uses(array $skill): int { return ($skill['limit']??0)===0?self::get('unlimitedUses'):$skill['limit']; }
}
