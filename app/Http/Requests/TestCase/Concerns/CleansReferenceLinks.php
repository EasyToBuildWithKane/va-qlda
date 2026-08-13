<?php

namespace App\Http\Requests\TestCase\Concerns;

trait CleansReferenceLinks
{
    /**
     * @return array<string, mixed>
     */
    protected function referenceLinkRules(): array
    {
        return [
            'reference_links' => ['nullable', 'array', 'max:20'],
            'reference_links.*.label' => ['nullable', 'string', 'max:120'],
            'reference_links.*.url' => ['required', 'string', 'max:2000', 'url', 'regex:/^https?:\/\/.+/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function referenceLinkMessages(): array
    {
        return [
            'reference_links.*.url.required' => 'URL liên kết không được để trống.',
            'reference_links.*.url.url' => 'URL liên kết không hợp lệ.',
            'reference_links.*.url.regex' => 'URL liên kết phải bắt đầu bằng http:// hoặc https://.',
        ];
    }

    /**
     * @return list<array{label: ?string, url: string}>|null
     */
    protected function cleanedReferenceLinks(): ?array
    {
        $raw = $this->input('reference_links');
        if (! is_array($raw)) {
            return null;
        }

        $links = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $links[] = [
                'label' => trim((string) ($row['label'] ?? '')) ?: null,
                'url' => $url,
            ];
        }

        return $links ?: null;
    }
}
