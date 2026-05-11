<?php
/**
 * PDF Helper - Generate account invoices and documents
 */

namespace App\Helpers;

use TCPDF;

class PDFHelper
{
    private TCPDF $pdf;
    
    public function __construct()
    {
        $this->pdf = new TCPDF();
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);
    }
    
    public function generateInvoicePDF(array $cuenta): string
    {
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', 'B', 20);
        
        // Header
        $this->pdf->Cell(0, 10, 'INTÉRMICA S.A.S', 0, 1, 'C');
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, 'Servicios de Termografía Industrial', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Invoice details
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'CUENTA DE COBRO #' . $cuenta['numero'], 0, 1);
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 5, 'Fecha de Emisión:', 0, 0);
        $this->pdf->Cell(0, 5, $cuenta['fecha_emision'], 0, 1);
        $this->pdf->Cell(50, 5, 'Fecha de Vencimiento:', 0, 0);
        $this->pdf->Cell(0, 5, $cuenta['fecha_vencimiento'], 0, 1);
        $this->pdf->Cell(50, 5, 'Estado:', 0, 0);
        $this->pdf->Cell(0, 5, strtoupper($cuenta['estado']), 0, 1);
        
        // Client info
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(0, 5, 'CLIENTE', 0, 1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, $cuenta['cliente']['nombre'], 0, 1);
        $this->pdf->Cell(0, 5, 'NIT: ' . $cuenta['cliente']['nit'], 0, 1);
        $this->pdf->Cell(0, 5, 'Email: ' . $cuenta['cliente']['email'], 0, 1);
        $this->pdf->Cell(0, 5, 'Teléfono: ' . $cuenta['cliente']['telefono'], 0, 1);
        
        // Items table
        $this->pdf->Ln(5);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(200, 200, 200);
        $this->pdf->Cell(80, 7, 'Descripción', 1, 0, 'L', true);
        $this->pdf->Cell(25, 7, 'Cantidad', 1, 0, 'C', true);
        $this->pdf->Cell(30, 7, 'V. Unitario', 1, 0, 'R', true);
        $this->pdf->Cell(30, 7, 'Total', 1, 1, 'R', true);
        
        $this->pdf->SetFont('helvetica', '', 9);
        foreach ($cuenta['items'] as $item) {
            $this->pdf->Cell(80, 6, substr($item['descripcion'], 0, 40), 1, 0);
            $this->pdf->Cell(25, 6, $item['cantidad'], 1, 0, 'C');
            $this->pdf->Cell(30, 6, '\$' . number_format($item['valor_unitario'], 0, ',', '.'), 1, 0, 'R');
            $this->pdf->Cell(30, 6, '\$' . number_format($item['total'], 0, ',', '.'), 1, 1, 'R');
        }
        
        // Totals
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(135, 6, 'Subtotal:', 0, 0, 'R');
        $this->pdf->Cell(30, 6, '\$' . number_format($cuenta['subtotal'], 0, ',', '.'), 0, 1, 'R');
        $this->pdf->Cell(135, 6, 'IVA (19%):', 0, 0, 'R');
        $this->pdf->Cell(30, 6, '\$' . number_format($cuenta['impuesto_iva'], 0, ',', '.'), 0, 1, 'R');
        $this->pdf->SetFillColor(220, 220, 220);
        $this->pdf->Cell(135, 8, 'TOTAL:', 0, 0, 'R', true);
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(30, 8, '\$' . number_format($cuenta['total'], 0, ',', '.'), 0, 1, 'R', true);
        
        // Generate filename
        $filename = 'CC-' . str_replace('-', '', $cuenta['fecha_emision']) . '-' . $cuenta['numero'] . '.pdf';
        $filepath = __DIR__ . '/../../storage/pdfs/cuentas_cobro/' . $filename;
        
        // Ensure directory exists
        @mkdir(dirname($filepath), 0755, true);
        
        // Output PDF
        $this->pdf->Output($filepath, 'F');
        
        return $filepath;
    }
}
