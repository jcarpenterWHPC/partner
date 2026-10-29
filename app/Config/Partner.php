<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Portal policy. Every value can be overridden from the secret as `partner.<name>`.
 */
class Partner extends BaseConfig
{
  /** Digits in an emailed sign-in code. */
  public int $codeLength = 6;
  /** Minutes a code and its sign-in link stay valid. */
  public int $codeTtlMinutes = 10;
  /** Wrong guesses before the code is burned and a new one must be requested. */
  public int $codeMaxAttempts = 5;
  /** Seconds between two codes for the same person. */
  public int $resendCooldownSeconds = 60;
  /** Codes one sign-in attempt may ask for before starting over. */
  public int $maxResends = 5;
  /** Minutes a half-finished sign-in (email entered, code not yet given) is kept. */
  public int $pendingTtlMinutes = 15;
  /** Sign-in requests one address may make per minute. */
  public int $requestsPerMinute = 10;
  /** Set false to log outgoing mail instead of sending it. */
  public bool $mailSend = true;
  /** Database group the property sync reads the intranet's `assets` table through. */
  public string $assetsGroup = 'home';
  /** Who a partner writes to when they cannot sign in. */
  public string $supportMail = 'BusinessSystems@whpc.com';
  /**
   * Positions a roster entry can hold, grouped as the form offers them. The names are
   * Assets' contact-role names where one exists, so the later sync is a straight mapping.
   *
   * @var array<string, list<string>>
   */
  public array $positions = [
    'Property / site management' => ['Site Manager', 'Assistant Manager'],
    'Maintenance' => ['Maintenance Supervisor', 'Maintenance Technician'],
    'Regional / portfolio management' => ['Regional Manager', 'Regional Vice President'],
    'Leasing, compliance and office' => ['Leasing Agent', 'Compliance Specialist', 'Service Coordinator', 'Other'],
  ];
  /** @return list<string> every position, flat */
  public function positionNames(): array
  {
    return array_merge(...array_values($this->positions));
  }
}
