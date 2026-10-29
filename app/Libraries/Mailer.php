<?php

namespace App\Libraries;

use Aws\SesV2\SesV2Client;
use Config\Partner;
use RuntimeException;

/**
 * Outgoing mail through the SES API as the instance role (policy whpc_SES_noreply:
 * From must be noreply@whpc.com). No SMTP credentials anywhere.
 *
 * Under `testing`, and when Config\Partner::$mailSend is off, nothing is sent: the
 * message is kept in self::$outbox so a test can read it, and logged by subject.
 */
class Mailer
{
  /** @var list<array{to:string,subject:string,html:string,text:string}> */
  public static array $outbox = [];
  private ?SesV2Client $ses;
  public function __construct(?SesV2Client $ses = null)
  {
    $this->ses = $ses;
  }
  /**
   * Send one HTML message (with a plain-text twin) wrapped in the brand template.
   *
   * @throws RuntimeException when SES refuses it
   */
  public function send(string $to, string $subject, string $html, string $text): void
  {
    $cfg = config('App');
    $body = view('emails/layout', [
      'HTML' => $html,
      'PROJECT_TITLE' => $cfg->brandName,
      'SOFTWARE_URL' => $cfg->baseURL,
      'LOGO_URL' => rtrim($cfg->baseURL, '/') . '/assets/images/brand/whpc-email-logo.png',
      'YEAR' => date('Y'),
    ], ['return' => true]);
    if (ENVIRONMENT === 'testing' || !config(Partner::class)->mailSend)
    {
      self::$outbox[] = ['to' => $to, 'subject' => $subject, 'html' => $body, 'text' => $text];
      log_message('info', '[Mailer] held (not sent): "' . $subject . '" to ' . $to);
      return;
    }
    $email = config('Email');
    $client = $this->ses ?? new SesV2Client(['region' => 'us-east-1', 'version' => 'latest']);
    try
    {
      $client->sendEmail([
        'FromEmailAddress' => $email->fromName !== '' ? sprintf('%s <%s>', $email->fromName, $email->fromEmail) : $email->fromEmail,
        'Destination' => ['ToAddresses' => [$to]],
        'Content' => ['Simple' => [
          'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
          'Body' => [
            'Html' => ['Data' => $body, 'Charset' => 'UTF-8'],
            'Text' => ['Data' => $text, 'Charset' => 'UTF-8'],
          ],
        ]],
      ]);
    }
    catch (\Throwable $e)
    {
      log_message('error', '[Mailer] SES refused "' . $subject . '" to ' . $to . ': ' . $e->getMessage());
      throw new RuntimeException('The email could not be sent.', 0, $e);
    }
  }
}
