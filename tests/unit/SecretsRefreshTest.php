<?php

use App\Commands\SecretsRefresh;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * A box loads exactly its own secret, chosen by its instance role, and a secret that
 * would take the site off its database is refused.
 *
 * @internal
 */
final class SecretsRefreshTest extends CIUnitTestCase
{
  public function testTheRoleChoosesTheSecretAndNothingElseIsGuessed(): void
  {
    $this->assertSame('partner/app-secrets', SecretsRefresh::secretFor('whpc_partner_Production'));
    $this->assertSame('partner_TEST/app-secrets', SecretsRefresh::secretFor('whpc_partner_test'));
    $this->assertNull(SecretsRefresh::secretFor('whpc_home_test'), "another app's box gets nothing");
    $this->assertNull(SecretsRefresh::secretFor(''));
  }
  public function testASecretMissingWhatTheSiteRunsOnIsRefused(): void
  {
    $good = ['CI_ENVIRONMENT' => 'production', 'app.baseURL' => 'https://partner.whpc.com/', 'database.default.hostname' => 'h', 'database.default.database' => 'partner', 'database.default.username' => 'u', 'database.default.password' => 'p', 'encryption.key' => 'hex2bin:00'];
    $this->assertSame([], SecretsRefresh::problems($good));
    $this->assertSame(['missing database.default.password'], SecretsRefresh::problems(['database.default.password' => ''] + $good));
    $this->assertSame(['CI_ENVIRONMENT is testing, which takes the site off its database'], SecretsRefresh::problems(['CI_ENVIRONMENT' => 'testing'] + $good));
  }
}
