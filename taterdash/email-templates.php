<?php
// ═══════════════════════════════════════════
// TaterDash — Branded Email Templates
// Table-based markup (email clients need tables, not flexbox) and a
// Satoshi → web-safe fallback stack, since Fontshare won't load in
// most mail clients.
// ═══════════════════════════════════════════

function email_shell(string $bodyHtml): string {
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mallow Frenchie</title>
</head>
<body style="margin:0;padding:0;background:#faf0f0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf0f0;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;">
<tr><td style="height:4px;background:#f2d0dc;line-height:4px;font-size:4px;">&nbsp;</td></tr>
<tr><td style="padding:32px 36px 8px;text-align:center;">
<img src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png" width="64" height="64" alt="Mallow Frenchie" style="display:block;margin:0 auto 12px;border-radius:50%;">
<div style="font-size:16px;font-weight:700;color:#191919;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">MallowFrenchie</div>
</td></tr>
<tr><td style="padding:16px 36px 36px;">
'.$bodyHtml.'
</td></tr>
<tr><td style="background:#111111;padding:18px 36px;text-align:center;">
<span style="font-size:11px;color:rgba(255,255,255,0.5);font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Mallow Frenchie &middot; @mallowfrenchie</span>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

function email_button(string $url, string $label): string {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 4px;">
<tr><td style="background:#e04d80;border-radius:999px;">
<a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-block;padding:13px 32px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#ffffff;text-decoration:none;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">'.htmlspecialchars($label).'</a>
</td></tr>
</table>';
}

function email_invoice_sent(array $invoice, string $url): string {
    $amount = '$' . number_format((float)$invoice['total'], 2);
    $body = '
<p style="font-size:15px;color:#191919;line-height:1.6;margin:0 0 4px;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Hi '.htmlspecialchars($invoice['client_name']).',</p>
<p style="font-size:14px;color:#6b6b6b;line-height:1.65;margin:0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">A new invoice is ready for your review. Total due: <strong style="color:#191919;">'.$amount.'</strong>.</p>
'.email_button($url, 'View Invoice').'
<p style="font-size:12px;color:#b0b0b0;line-height:1.6;margin:16px 0 0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Invoice '.htmlspecialchars($invoice['invoice_num']).'</p>';
    return email_shell($body);
}

function email_proposal_sent(array $proposal, string $url): string {
    $campaign = trim($proposal['campaign_name'] ?? '');
    $intro = $campaign
        ? 'We put together a partnership proposal for you &mdash; <strong style="color:#191919;">'.htmlspecialchars($campaign).'</strong>.'
        : 'We put together a partnership proposal for you.';
    $body = '
<p style="font-size:15px;color:#191919;line-height:1.6;margin:0 0 4px;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Hi '.htmlspecialchars($proposal['client_name']).',</p>
<p style="font-size:14px;color:#6b6b6b;line-height:1.65;margin:0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">'.$intro.'</p>
'.email_button($url, 'View Proposal').'
<p style="font-size:12px;color:#b0b0b0;line-height:1.6;margin:16px 0 0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Proposal '.htmlspecialchars($proposal['proposal_num']).'</p>';
    return email_shell($body);
}

function email_proposal_signed_client(array $proposal): string {
    $campaign = $proposal['campaign_name'] ?: $proposal['proposal_num'];
    $body = '
<p style="font-size:15px;color:#191919;line-height:1.6;margin:0 0 4px;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Hi '.htmlspecialchars($proposal['client_name']).',</p>
<p style="font-size:14px;color:#6b6b6b;line-height:1.65;margin:0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Thanks for signing! We\'re excited to get started on <strong style="color:#191919;">'.htmlspecialchars($campaign).'</strong>. We\'ll be in touch shortly with next steps.</p>';
    return email_shell($body);
}

function email_proposal_signed_notify(array $proposal, string $signer): string {
    $body = '
<p style="font-size:15px;color:#191919;line-height:1.6;margin:0 0 4px;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">Proposal signed!</p>
<p style="font-size:14px;color:#6b6b6b;line-height:1.65;margin:0;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;"><strong style="color:#191919;">'.htmlspecialchars($signer).'</strong> just signed <strong style="color:#191919;">'.htmlspecialchars($proposal['proposal_num']).'</strong> for '.htmlspecialchars($proposal['client_name']).'.</p>';
    return email_shell($body);
}
