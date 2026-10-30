<?php

/**
 * Dates and times as people read them. Stored values are the app's timezone already.
 */
if (!function_exists('nice_date'))
{
  /** '2026-10-31' => 'Oct 31, 2026'; '' for nothing. */
  function nice_date(?string $ymd): string
  {
    if ($ymd === null || trim($ymd) === '')
    {
      return '';
    }
    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($ymd), 0, 10));
    return $d === false ? $ymd : $d->format('M j, Y');
  }
}
if (!function_exists('nice_time'))
{
  /** '2026-10-30 10:57:54' => 'Today at 10:57 AM', 'Yesterday at 3:12 PM', else 'Oct 28, 2026 at 9:00 AM'. */
  function nice_time(?string $datetime, string $never = ''): string
  {
    if ($datetime === null || trim($datetime) === '')
    {
      return $never;
    }
    try
    {
      $t = new \DateTimeImmutable($datetime);
    }
    catch (\Exception $e)
    {
      return $datetime;
    }
    $today = new \DateTimeImmutable('today');
    $day = $t->setTime(0, 0);
    $clock = $t->format('g:i A');
    if ($day == $today)
    {
      return 'Today at ' . $clock;
    }
    if ($day == $today->modify('-1 day'))
    {
      return 'Yesterday at ' . $clock;
    }
    return $t->format('M j, Y') . ' at ' . $clock;
  }
}
