<?php
// ═══════════════════════════════════════════
// TaterDash — Signed Proposal PDF
// render_proposal_pdf(PDO $pdo, int $proposal_id): ?string
//
// Font note: Dompdf can't reach Fontshare, so this uses a system font
// stack (Helvetica/Arial). To match the brand's Satoshi typeface, a
// Satoshi TTF would need to be added locally and registered with
// Dompdf's font loader — left as a future step, not done here.
// ═══════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function render_proposal_pdf(PDO $pdo, int $proposal_id): ?string {
    try {
        $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE id = ?");
        $stmt->execute([$proposal_id]);
        $proposal = $stmt->fetch();
        if (!$proposal) return null;

        $sigStmt = $pdo->prepare("SELECT * FROM td_signatures WHERE proposal_id = ? ORDER BY signed_at DESC LIMIT 1");
        $sigStmt->execute([$proposal_id]);
        $signature = $sigStmt->fetch();

        $company = get_settings($pdo);

        $deliverables = json_decode($proposal['deliverables'] ?? '[]', true) ?: [];
        $he       = fn($s) => htmlspecialchars($s ?? '', ENT_QUOTES);
        $fmtDate  = fn($d) => $d ? date('F j, Y', strtotime($d)) : '&mdash;';
        $fmtMoney = fn($n) => '$' . number_format((float)$n, 0);

        $deliverablesHtml = '';
        foreach ($deliverables as $d) {
            $deliverablesHtml .= '<tr><td style="padding:4px 0;font-size:11px;color:#191919;"><span style="color:#e04d80;">-</span>&nbsp; ' . $he($d) . '</td></tr>';
        }
        if ($deliverablesHtml === '') {
            $deliverablesHtml = '<tr><td style="padding:4px 0;font-size:11px;color:#b0b0b0;">No deliverables listed.</td></tr>';
        }

        $sigBlock = '<p style="font-size:11px;color:#b0b0b0;">This proposal has not yet been signed.</p>';
        if ($signature) {
            $ip = $signature['ip_direct'] ?: $signature['ip_address'];
            $sigBlock = '
            <table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #191919;padding-top:10px;margin-top:6px;">
              <tr><td style="font-size:10px;color:#6b6b6b;padding:2px 0;"><strong style="color:#191919;">Signed by:</strong> ' . $he($signature['signer_name']) . '</td></tr>
              <tr><td style="font-size:10px;color:#6b6b6b;padding:2px 0;"><strong style="color:#191919;">Email:</strong> ' . $he($signature['signer_email']) . '</td></tr>
              <tr><td style="font-size:10px;color:#6b6b6b;padding:2px 0;"><strong style="color:#191919;">Signed on:</strong> ' . date('F j, Y \a\t g:i A', strtotime($signature['signed_at'])) . '</td></tr>
              <tr><td style="font-size:10px;color:#6b6b6b;padding:2px 0;"><strong style="color:#191919;">IP address:</strong> ' . $he($ip) . '</td></tr>
            </table>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page { margin: 36px 40px; }
            body { font-family: Helvetica, Arial, sans-serif; color: #191919; margin: 0; }
            .band { background: #f2d0dc; height: 4px; width: 100%; margin-bottom: 20px; }
            .hdr-word { font-size: 26px; font-weight: bold; color: #191919; }
            .hdr-num { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #e04d80; margin-top: 4px; }
            .eyebrow { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #e04d80; margin-bottom: 4px; }
            .section { margin-bottom: 18px; }
            .box { background: #faf0f0; border-radius: 8px; padding: 14px 16px; }
        </style></head><body>
        <div class="band"></div>
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td width="60%" style="vertical-align:top;">
              <div class="eyebrow">Partnership Proposal</div>
              <div class="hdr-word">' . $he($proposal['campaign_name'] ?: $proposal['proposal_num']) . '</div>
              <div class="hdr-num">' . $he($proposal['proposal_num']) . '</div>
            </td>
            <td width="40%" style="vertical-align:top;text-align:right;font-size:10px;color:#6b6b6b;">
              ' . $he($company['company_name']) . '<br>' . $he($company['company_email']) . '<br>' . nl2br($he($company['company_address'])) . '
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" class="section" style="margin-top:20px;">
          <tr>
            <td width="50%" style="vertical-align:top;">
              <div class="eyebrow">Client</div>
              <div style="font-size:13px;font-weight:bold;">' . $he($proposal['client_name']) . '</div>
              <div style="font-size:10px;color:#6b6b6b;">' . $he($proposal['client_email']) . '</div>
            </td>
            <td width="50%" style="vertical-align:top;">
              <div class="eyebrow">Campaign</div>
              <div style="font-size:10px;color:#6b6b6b;">Platform: ' . $he($proposal['platform']) . '</div>
              <div style="font-size:10px;color:#6b6b6b;">' . $fmtDate($proposal['campaign_start']) . ' &ndash; ' . $fmtDate($proposal['campaign_end']) . '</div>
            </td>
          </tr>
        </table>

        <div class="section box">
          <div class="eyebrow">Deliverables</div>
          <table width="100%" cellpadding="0" cellspacing="0">' . $deliverablesHtml . '</table>
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" class="section">
          <tr><td style="border-top:2px solid #191919;padding-top:8px;">
            <span style="font-size:9px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6b6b6b;">Total Investment</span><br>
            <span style="font-size:22px;font-weight:bold;">' . $fmtMoney($proposal['total']) . '</span>
          </td></tr>
        </table>

        <div class="section">
          <div class="eyebrow">Signature</div>
          ' . $sigBlock . '
        </div>

        </body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $dir = __DIR__ . '/generated-pdfs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $proposal['proposal_num']) . '-signed.pdf';
        $path = $dir . '/' . $filename;
        file_put_contents($path, $dompdf->output());

        return $path;

    } catch (Throwable $e) {
        try {
            log_php_error($pdo, 'render_proposal_pdf', $e, ['proposal_id' => $proposal_id]);
        } catch (Throwable $ignored) {
            // Never let logging failure mask the original error, or throw.
        }
        return null;
    }
}
