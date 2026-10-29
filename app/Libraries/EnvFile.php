<?php

namespace App\Libraries;

use CodeIgniter\Config\DotEnv;
use InvalidArgumentException;

/**
 * A .env file that CodeIgniter's own DotEnv parser reads back exactly.
 *
 * Every value is double-quoted with `\` and `"` escaped: unquoted, DotEnv drops anything
 * after " #" and rejects whitespace. A value it cannot carry is refused, never written
 * wrong — a line break, or `${…}`, which it would expand.
 */
class EnvFile
{
  /**
   * Why $vars cannot be written, one entry per problem; empty when they can.
   *
   * @param array<array-key,mixed> $vars
   * @return list<string>
   */
  public static function problems(array $vars): array
  {
    $out = [];
    foreach ($vars as $name => $value)
    {
      $name = (string)$name;
      if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name))
      {
        $out[] = "unusable name: {$name}";
      }
      elseif (!is_string($value))
      {
        $out[] = "{$name}: not a string";
      }
      elseif (preg_match('/[\r\n\0]/', $value))
      {
        $out[] = "{$name}: contains a line break";
      }
      elseif (str_contains($value, '${'))
      {
        $out[] = "{$name}: contains \${, which DotEnv would expand";
      }
    }
    return $out;
  }
  /**
   * The file: $header as comments, CI_ENVIRONMENT first, then every other name in order.
   *
   * @param array<string,string> $vars
   * @param list<string> $header
   */
  public static function render(array $vars, array $header = []): string
  {
    $problems = self::problems($vars);
    if ($problems !== [])
    {
      throw new InvalidArgumentException(implode('; ', $problems));
    }
    $names = array_map('strval', array_keys($vars));
    usort($names, static fn(string $a, string $b): int => [$a !== 'CI_ENVIRONMENT', $a] <=> [$b !== 'CI_ENVIRONMENT', $b]);
    $lines = array_map(static fn(string $line): string => '# ' . $line, $header);
    foreach ($names as $name)
    {
      $lines[] = $name . ' = "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $vars[$name]) . '"';
    }
    return implode("\n", $lines) . "\n";
  }
  /**
   * What CodeIgniter reads from $path — the values the app will run with.
   *
   * @return array<string,string>
   */
  public static function read(string $path): array
  {
    return (new DotEnv(dirname($path), basename($path)))->parse() ?? [];
  }
}
