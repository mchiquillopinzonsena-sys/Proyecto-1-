<?php
/**
 * QR Helper - Generate dynamic QR codes
 */

namespace App\Helpers;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QRHelper
{
    public static function generateQR(string $data, int $size = 300): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'imageBase64' => false,
            'scale' => 5,
            'quietZone' => 2,
        ]);
        
        $qrCode = new QRCode($options);
        $qrCode->render($data);
        
        return $qrCode->getDataUri();
    }
    
    public static function generateQRBase64(string $data): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'imageBase64' => true,
        ]);
        
        $qrCode = new QRCode($options);
        return $qrCode->render($data);
    }
    
    public static function generateInvoiceQR(string $invoiceNumber, string $clientEmail): string
    {
        $verificationUrl = getenv('APP_URL', 'https://intermica.com') . "/cuenta/{$invoiceNumber}/verify";
        return self::generateQR($verificationUrl);
    }
}
