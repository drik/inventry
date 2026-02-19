<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EncodingMode: string implements HasLabel
{
    // 2D Codes
    case QrCode = 'qr_code';
    case DataMatrix = 'data_matrix';
    case PDF417 = 'pdf417';
    case Aztec = 'aztec';

    // 1D Barcodes
    case EAN13 = 'ean_13';
    case EAN8 = 'ean_8';
    case UPCA = 'upc_a';
    case Code128 = 'code_128';
    case Code39 = 'code_39';
    case ITF = 'itf';

    // Wireless
    case RFID = 'rfid';
    case NFC = 'nfc';

    public function getLabel(): string
    {
        return match ($this) {
            self::QrCode => 'QR Code',
            self::DataMatrix => 'Data Matrix',
            self::PDF417 => 'PDF417',
            self::Aztec => 'Aztec',
            self::EAN13 => 'EAN-13',
            self::EAN8 => 'EAN-8',
            self::UPCA => 'UPC-A',
            self::Code128 => 'Code 128',
            self::Code39 => 'Code 39',
            self::ITF => 'ITF (Interleaved 2 of 5)',
            self::RFID => 'RFID',
            self::NFC => 'NFC',
        };
    }
}
