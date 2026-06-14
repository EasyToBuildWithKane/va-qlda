<?php

namespace App\Support\Enums;

enum CoachingMaterialType: string
{
    case Canva = 'canva';
    case GoogleDocs = 'google_docs';
    case Pdf = 'pdf';
    case Pptx = 'pptx';
    case Youtube = 'youtube';
    case Loom = 'loom';
    case Gdrive = 'gdrive';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Canva => 'Canva',
            self::GoogleDocs => 'Google Docs',
            self::Pdf => 'PDF',
            self::Pptx => 'PowerPoint',
            self::Youtube => 'YouTube',
            self::Loom => 'Loom',
            self::Gdrive => 'Google Drive',
            self::File => 'Tệp đính kèm',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
