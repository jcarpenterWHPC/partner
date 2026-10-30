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
  /** The intranet's read-only view of live property contacts, in the same group. */
  public string $contactsView = 'partner_property_contacts';
  /**
   * Assets' contact role → the roster position it seeds. A role not listed here is not
   * PMC staff (WHPC's asset managers, HUD and RD people) or is new, and is reported.
   *
   * @var array<string,string>
   */
  public array $contactRoles = [
    'Site Manager' => 'Site Manager',
    'Assistant Manager' => 'Assistant Manager',
    'Regional Manager' => 'Regional Manager',
    'Regional Vice President' => 'Regional Vice President',
    'Maintenance' => 'Maintenance Technician',
    'Leasing Agent' => 'Leasing Agent',
    'Service Coordinator' => 'Service Coordinator',
    'Service Coord Supervisor' => 'Service Coord Supervisor',
    'FSS Coordinator' => 'FSS Coordinator',
    'Executive' => 'Other',
  ];
  /**
   * The positions every property is expected to have filled, by organisation kind:
   * the columns of the Properties page, and what counts as a vacancy.
   *
   * @var array<string,list<string>>
   */
  public array $propertyRoles = [
    'management' => ['Site Manager', 'Maintenance Technician', 'Regional Manager'],
    'services' => ['Service Coordinator', 'Service Coord Supervisor'],
  ];
  /** Who a partner writes to when they cannot sign in. */
  public string $supportMail = 'BusinessSystems@whpc.com';
  /** WHPC's mail domain. Its people sign in with Microsoft, and only $admins may. */
  public string $whpcDomain = 'whpc.com';
  /**
   * The WHPC admins, and the only WHPC people who can sign in: `Name <address>` or bare
   * addresses, comma-separated. Secret key `partner.admins`.
   */
  public string $admins = 'Joe Carpenter <jcarpenter@whpc.com>, Brian Coda <bcoda@whpc.com>';
  /**
   * Microsoft Entra sign-in. The tenant is WHPC's directory id; the client is the portal's
   * app registration. Empty values switch Microsoft sign-in off. The authority is
   * `organizations` for a multi-tenant registration (PMC tenants can be enrolled later)
   * or the tenant id for a single-tenant one.
   */
  public string $microsoftTenant = '';
  public string $microsoftClientId = '';
  public string $microsoftClientSecret = '';
  public string $microsoftAuthority = 'organizations';
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
    'Service coordination' => ['Service Coordinator', 'Service Coord Supervisor', 'FSS Coordinator'],
    'Leasing, compliance and office' => ['Leasing Agent', 'Compliance Specialist', 'Other'],
  ];
  /** @return list<string> every position, flat */
  public function positionNames(): array
  {
    return array_merge(...array_values($this->positions));
  }
  /** @return array<string,string> admin address => name ('' when only an address was given) */
  public function admins(): array
  {
    $out = [];
    foreach (explode(',', $this->admins) as $entry)
    {
      if (preg_match('/^\s*(?:(.*?)\s*<([^<>\s]+)>|([^<>\s]+))\s*$/', $entry, $m))
      {
        $out[mb_strtolower($m[2] !== '' ? $m[2] : $m[3])] = trim($m[1]);
      }
    }
    return $out;
  }
  public function isWhpc(string $email): bool
  {
    return str_ends_with(mb_strtolower(trim($email)), '@' . mb_strtolower($this->whpcDomain));
  }
  public function microsoftEnabled(): bool
  {
    return $this->microsoftTenant !== '' && $this->microsoftClientId !== '' && $this->microsoftClientSecret !== '' && $this->microsoftAuthority !== '';
  }
}
