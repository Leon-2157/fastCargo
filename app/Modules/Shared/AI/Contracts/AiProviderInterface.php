<?php

namespace App\Modules\Shared\AI\Contracts;

interface AiProviderInterface
{
    /**
     * Kirim pesan chat ke AI provider.
     *
     * @param  string  $prompt  Pesan dari user
     * @param  array  $context  Data konteks halaman yang sedang dibuka
     * @param  string  $systemPrompt  System prompt spesifik per role/halaman
     */
    public function chat(string $prompt, array $context = [], string $systemPrompt = ''): string;

    /**
     * Buat ringkasan dari data array (booking list, invoice, dsb).
     */
    public function summarize(array $data, string $context = ''): string;

    /**
     * Nama provider — digunakan untuk logging dan admin UI.
     */
    public function name(): string;
}
