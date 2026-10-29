<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** The CapEx site the portal hands partners into. `capex.url` in the secret. */
class Capex extends BaseConfig
{
  public string $url = 'https://capex.whpc.com/';
}
