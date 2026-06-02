<?php

namespace App\Support\Enums;

enum ProjectAttachmentCategory: string
{
    case Customer = 'customer';
    case UiUx = 'uiux';
    case Ba = 'ba';
    case CustomerData = 'customer_data';
    case Images = 'images';

    public function labelVi(): string
    {
        return match ($this) {
            self::Customer => 'Tài liệu khách hàng',
            self::UiUx => 'Tài liệu UI/UX',
            self::Ba => 'Tài liệu BA',
            self::CustomerData => 'Data khách hàng',
            self::Images => 'Hình ảnh & media',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Customer => 'Tài liệu, brief, email xác nhận từ phía khách hàng.',
            self::UiUx => 'Wireframe, mockup, design system, prototype UI/UX.',
            self::Ba => 'BRD, SRS, FRS, use case, flow nghiệp vụ.',
            self::CustomerData => 'File Excel/CSV, mẫu dữ liệu, import từ khách hàng.',
            self::Images => 'Screenshot, ảnh hiện trạng, sơ đồ, tài liệu hình ảnh liên quan.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Customer => 'building',
            self::UiUx => 'design-system',
            self::Ba => 'template',
            self::CustomerData => 'download',
            self::Images => 'image',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Customer => 'sky',
            self::UiUx => 'violet',
            self::Ba => 'amber',
            self::CustomerData => 'emerald',
            self::Images => 'rose',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, description:string, icon:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
            'description' => $c->description(),
            'icon' => $c->icon(),
            'color' => $c->color(),
        ], self::cases());
    }
}
