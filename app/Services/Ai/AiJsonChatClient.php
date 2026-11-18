<?php

namespace App\Services\Ai;

interface AiJsonChatClient
{
    /**
     * Kirim prompt dan kembalikan konten mentah dari model.
     */
    public function chat(string $prompt): string;

    public function driver(): string;

    public function endpoint(): string;

    public function model(): string;

    public function hasCredential(): bool;
}
