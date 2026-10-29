<?php

use App\Libraries\EnvFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * .env is generated from the secret, so what is written must come back byte-identical
 * through CodeIgniter's own parser.
 *
 * @internal
 */
final class EnvFileTest extends CIUnitTestCase
{
  public function testEveryValueSurvivesTheRoundTrip(): void
  {
    $vars = [
      'database.default.password' => 'p#ss "quoted" back\\slash and spaces',
      'CI_ENVIRONMENT' => 'production',
      'app.baseURL' => 'https://partner.whpc.com/',
      'encryption.key' => 'hex2bin:00ff',
    ];
    $path = sys_get_temp_dir() . '/envfile-' . getmypid();
    file_put_contents($path, EnvFile::render($vars, ['GENERATED']));
    $back = array_intersect_key(EnvFile::read($path), $vars);
    unlink($path);
    ksort($vars);
    ksort($back);
    $this->assertSame($vars, $back);
    $this->assertStringStartsWith("# GENERATED\nCI_ENVIRONMENT = ", EnvFile::render($vars, ['GENERATED']), 'CI_ENVIRONMENT is written first');
  }
  public function testWhatDotEnvCannotCarryIsRefusedNotWrittenWrong(): void
  {
    $this->assertSame([], EnvFile::problems(['a.b' => 'ok']));
    $this->assertSame(['x: contains a line break', 'y: contains ${, which DotEnv would expand', 'unusable name: 1bad', 'z: not a string'], EnvFile::problems(['x' => "a\nb", 'y' => 'a${b}', '1bad' => 'v', 'z' => 1]));
    $this->expectException(InvalidArgumentException::class);
    EnvFile::render(['x' => "a\nb"]);
  }
}
