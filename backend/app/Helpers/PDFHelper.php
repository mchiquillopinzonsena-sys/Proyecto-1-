<?php
/**
 * PDF Helper - Genera documentos PDF de cuentas de cobro
 *
 * Estrategia de degradación:
 *  1. TCPDF  → si está instalado vía Composer
 *  2. Dompdf → si está instalado vía Composer
 *  3. HTML   → fallback sin dependencias externas (siempre disponible)
 */

namespace App\Helpers;

class PDFHelper
{
    /**
     * Envía el PDF (o HTML) de una cuenta de cobro directamente al cliente HTTP.
     *
     * @param array<string, mixed>            $cuenta  Fila de cuentas_cobro
     * @param array<int, array<string, mixed>> $items   Filas de cuentas_items
     */
    public static function streamCuentaCobro(array $cuenta, array $items): void
    {
        $filename = self::filename($cuenta);

        // 1. TCPDF
        if (class_exists('TCPDF')) {
            self::streamViaTcpdf($cuenta, $items, $filename);
            return;
        }

        // 2. Dompdf
        if (class_exists('\\Dompdf\\Dompdf')) {
            self::streamViaDompdf($cuenta, $items, $filename);
            return;
        }

        // 3. HTML fallback
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '.html"');
        echo self::buildHtml($cuenta, $items);
    }

    // -------------------------------------------------------------------------
    // Drivers
    // -------------------------------------------------------------------------

    private static function streamViaTcpdf(array $cuenta, array $items, string $filename): void
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Intérmica S.A.S');
        $pdf->SetTitle('Cuenta de Cobro ' . ($cuenta['numero'] ?? ''));
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildHtml($cuenta, $items), true, false, true, false, '');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');

        echo $pdf->Output('', 'S');
    }

    private static function streamViaDompdf(array $cuenta, array $items, string $filename): void
    {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true]);
        $dompdf->loadHtml(self::buildHtml($cuenta, $items), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');

        echo $dompdf->output();
    }

    // -------------------------------------------------------------------------
    // HTML template compartido
    // -------------------------------------------------------------------------

    private static function buildHtml(array $cuenta, array $items): string
    {
        $numero      = htmlspecialchars((string) ($cuenta['numero'] ?? '—'));
        $emision     = htmlspecialchars((string) ($cuenta['fecha_emision'] ?? '—'));
        $vencimiento = htmlspecialchars((string) ($cuenta['fecha_vencimiento'] ?? '—'));
        $estado      = strtoupper(htmlspecialchars((string) ($cuenta['estado'] ?? '')));
        $moneda      = htmlspecialchars((string) ($cuenta['moneda'] ?? 'COP'));
        $subtotal    = number_format((float) ($cuenta['subtotal'] ?? 0), 2, ',', '.');
        $iva         = number_format((float) ($cuenta['impuesto_iva'] ?? 0), 2, ',', '.');
        $total       = number_format((float) ($cuenta['total'] ?? 0), 2, ',', '.');

        // Logo SVG codificado en base64 para evitar dependencias externas en el PDF
        $logoBase64 = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMDAgNjAiPjxwYXRoIGZpbGw9IiMzYjgyZjYiIGQ9Ik0zMCwzMCBtLTE1LDAgYTE1LDE1IDAgMSwxIDMwLDAgYTE1LDE1IDAgMSwxIC0zMCwwIi8+PHBhdGggZmlsbD0iI2ZiYmYyNCIgZD0iTTM1LDI1IGwtMTAsMTAgbDEwLDEwIi8+PHRleHQgeD0iNTUiIHk9IjM4IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZvbnQtd2VpZ2h0PSJib2xkIiBmaWxsPSIjMWUzYThhIj5JTlTDiVJNSUNBPC90ZXh0Pjwvc3ZnPg==';

        $rowsHtml = '';
        foreach ($items as $item) {
            $desc   = htmlspecialchars((string) ($item['descripcion'] ?? ''));
            $qty    = htmlspecialchars((string) ($item['cantidad'] ?? 1));
            $unidad = htmlspecialchars((string) ($item['unidad'] ?? ''));
            $vu     = number_format((float) ($item['valor_unitario'] ?? 0), 2, ',', '.');
            $vtotal = number_format((float) ($item['total'] ?? 0), 2, ',', '.');
            $rowsHtml .= "
            <tr>
              <td>{$desc}</td>
              <td class='center'>{$qty} {$unidad}</td>
              <td class='right'>{$moneda} {$vu}</td>
              <td class='right'><strong>{$moneda} {$vtotal}</strong></td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
  
  body { 
    font-family: 'Inter', Helvetica, sans-serif; 
    font-size: 13px; color: #374151; margin: 0; padding: 40px; 
    background-color: #ffffff;
  }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; }
  .logo { width: 180px; }
  .invoice-title { text-align: right; }
  .invoice-title h1 { color: #1e3a8a; font-size: 28px; margin: 0; font-weight: 800; letter-spacing: -0.5px; }
  .invoice-title p { color: #6b7280; font-size: 14px; margin: 5px 0 0; }
  
  .details-container { display: flex; justify-content: space-between; margin-bottom: 40px; }
  .details-box { background: #f9fafb; padding: 15px 20px; border-radius: 8px; width: 45%; border: 1px solid #e5e7eb; }
  .details-box h3 { color: #111827; margin: 0 0 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
  .details-box p { margin: 4px 0; font-size: 13px; line-height: 1.5; }
  .badge { background: #dbeafe; color: #1d4ed8; padding: 3px 8px; border-radius: 12px; font-weight: 600; font-size: 11px; }

  table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
  th { background: #1e3a8a; color: #ffffff; padding: 12px 15px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 12px 15px; border-bottom: 1px solid #f3f4f6; color: #4b5563; }
  tr:last-child td { border-bottom: none; }
  tr:nth-child(even) td { background-color: #f8fafc; }
  
  .center { text-align: center; }
  .right { text-align: right; }
  
  .totals-container { display: flex; justify-content: flex-end; }
  .totals-table { width: 320px; border: none; }
  .totals-table td { padding: 8px 15px; background: transparent; border: none; color: #374151; }
  .totals-table tr.total-row td { background: #1e3a8a; color: #ffffff; font-size: 16px; font-weight: 800; border-radius: 4px; }
  
  .footer { margin-top: 60px; text-align: center; color: #9ca3af; font-size: 11px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
</style>
</head>
<body>
  <div class="header">
    <img src="{$logoBase64}" alt="Intérmica Logo" class="logo"/>
    <div class="invoice-title">
      <h1>CUENTA DE COBRO</h1>
      <p>Comprobante Oficial</p>
    </div>
  </div>

  <div class="details-container">
    <div class="details-box">
      <h3>Datos de Emisión</h3>
      <p><strong>N° Documento:</strong> {$numero}</p>
      <p><strong>Fecha Emisión:</strong> {$emision}</p>
      <p><strong>Vencimiento:</strong> {$vencimiento}</p>
      <p><strong>Estado:</strong> <span class="badge">{$estado}</span></p>
    </div>
    <div class="details-box">
      <h3>Intérmica S.A.S</h3>
      <p>NIT: 900.123.456-7</p>
      <p>Especialistas en Termografía</p>
      <p>Bogotá, Colombia</p>
      <p>contacto@intermica.com.co</p>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Descripción del Servicio / Artículo</th>
        <th class="center">Cant.</th>
        <th class="right">V. Unitario</th>
        <th class="right">Total</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div class="totals-container">
    <table class="totals-table">
      <tr><td>Subtotal:</td><td class="right">{$moneda} {$subtotal}</td></tr>
      <tr><td>IVA (19%):</td><td class="right">{$moneda} {$iva}</td></tr>
      <tr class="total-row"><td>TOTAL A PAGAR:</td><td class="right">{$moneda} {$total}</td></tr>
    </table>
  </div>

  <div class="footer">
    <p>Este documento es una representación impresa de una cuenta de cobro.</p>
    <p>Agradecemos su confianza en los servicios de Intérmica S.A.S.</p>
  </div>
</body>
</html>
HTML;
    }

    private static function filename(array $cuenta): string
    {
        $numero = preg_replace('/[^A-Za-z0-9\-]/', '_', (string) ($cuenta['numero'] ?? 'CC'));
        return "cuenta_cobro_{$numero}.pdf";
    }
}
