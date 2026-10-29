<?php

if (!function_exists('asset'))
{
  /**
   * @param string $path Path relative to public/assets (e.g. 'css/whpc-brand.css').
   */
  function asset(string $path): string
  {
    $path = ltrim($path, '/');
    $url = base_url('assets/' . $path);
    $full = FCPATH . 'assets/' . $path;
    if (is_file($full))
    {
      $url .= '?v=' . filemtime($full);
    }
    return $url;
  }
}
