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
              <td style='text-align:center'>{$qty} {$unidad}</td>
              <td style='text-align:right'>{$moneda} {$vu}</td>
              <td style='text-align:right'>{$moneda} {$vtotal}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<style>
  body  { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111827; margin: 40px; }
  h1   { font-size: 20px; color: #1e40af; margin: 0 0 2px; }
  hr   { border: none; border-top: 2px solid #1e40af; margin: 12px 0; }
  .meta     { display: flex; justify-content: space-between; margin-bottom: 16px; }
  .badge    { display: inline-block; padding: 2px 10px; border-radius: 12px;
              background: #dbeafe; color: #1e3a8a; font-weight: bold; font-size: 11px; }
  table     { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th        { background: #1e40af; color: #fff; padding: 8px 10px; text-align: left; }
  td        { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f9fafb; }
  .totals          { margin-top: 20px; }
  .totals table    { width: 280px; margin-left: auto; border: none; }
  .totals td       { border: none; padding: 3px 8px; }
  .total-row td    { font-weight: bold; font-size: 13px;
                     border-top: 2px solid #1e40af; padding-top: 6px; }
</style>
</head>
<body>
  <h1>Intérmica S.A.S</h1>
  <small>Sistema de Gestión de Servicios Termográficos</small>
  <hr/>
  <div class="meta">
    <div>
      <strong>Cuenta de Cobro:</strong> {$numero}<br/>
      <strong>Fecha emisión:</strong> {$emision}<br/>
      <strong>Vencimiento:</strong> {$vencimiento}
    </div>
    <div><strong>Estado:</strong><br/><span class="badge">{$estado}</span></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Descripción</th>
        <th style="text-align:center">Cantidad</th>
        <th style="text-align:right">Valor unitario</th>
        <th style="text-align:right">Total línea</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td>Subtotal:</td><td style="text-align:right">{$moneda} {$subtotal}</td></tr>
      <tr><td>IVA (19 %):</td><td style="text-align:right">{$moneda} {$iva}</td></tr>
      <tr class="total-row"><td>TOTAL:</td><td style="text-align:right">{$moneda} {$total}</td></tr>
    </table>
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
