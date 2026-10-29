<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" style="margin: 0;padding: 0;font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;font-size: 100%;line-height: 1.6;">
<head>
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title><?= esc($PROJECT_TITLE) ?></title>
</head>
<body bgcolor="#f6f6f6" style="margin: 0;padding: 0;font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;font-size: 100%;line-height: 1.6;">
    <table class="body-wrap" bgcolor="#f6f6f6" style="margin: 0;padding: 20px;width: 100%;">
        <tr>
            <td></td>
            <td class="container" bgcolor="#FFFFFF" style="margin: 0 auto;padding: 20px;border: 1px solid #f0f0f0;max-width: 600px;">
                <div class="content" style="max-width: 600px;display: block;">
                    <table style="width: 100%;">
                        <tr>
                            <td>
                                <div style="margin: 10px 0 30px;padding: 0;text-align: center;">
                                    <a href="<?= esc($SOFTWARE_URL) ?>" target="_blank" style="text-decoration:none;">
                                        <img src="<?= esc($LOGO_URL, 'attr') ?>" alt="<?= esc($PROJECT_TITLE) ?>" width="200" style="display:inline-block;width:200px;max-width:80%;height:auto;border:0;outline:none;">
                                    </a>
                                </div>
                                <?= $HTML ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td></td>
        </tr>
    </table>
    <table class="footer-wrap" style="width: 100%;clear: both;">
        <tr>
            <td></td>
            <td class="container" style="margin: 0 auto;padding: 0;max-width: 600px;">
                <div class="content" style="max-width: 600px;display: block;">
                    <table style="width: 100%;">
                        <tr>
                            <td align="center">
                                <p style="margin: 0;font-size: 12px;color: #666;">
                                    &copy; <?= esc($YEAR) ?> <a href="<?= esc($SOFTWARE_URL) ?>" target="_blank" style="color: #999;"><?= esc($PROJECT_TITLE) ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td></td>
        </tr>
    </table>
</body>
</html>
