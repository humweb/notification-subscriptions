<?php

namespace Humweb\Notifications\Digest;

class DigestMessage
{
    /**
     * Ordered list of components to render in the digest email.
     * Each component is an associative array with a 'type' key and type-specific payload.
     *
     * Supported types: line, heading, panel, button, list, separator
     */
    protected array $components = [];

    public function components(): array
    {
        return $this->components;
    }

    public function line(string $text): static
    {
        $this->components[] = ['type' => 'line', 'text' => $text];

        return $this;
    }

    public function heading(string $text, int $level = 2): static
    {
        $this->components[] = ['type' => 'heading', 'text' => $text, 'level' => max(1, min(4, $level))];

        return $this;
    }

    public function panel(string $text): static
    {
        $this->components[] = ['type' => 'panel', 'text' => $text];

        return $this;
    }

    public function button(string $text, string $url, string $color = 'primary'): static
    {
        $this->components[] = [
            'type' => 'button',
            'text' => $text,
            'url' => $url,
            'color' => $color,
        ];

        return $this;
    }

    /**
     * Render a simple bullet list.
     *
     * @param  array<int, string>  $items
     */
    public function bulletList(array $items): static
    {
        $this->components[] = ['type' => 'list', 'items' => array_values($items)];

        return $this;
    }

    public function separator(): static
    {
        $this->components[] = ['type' => 'separator'];

        return $this;
    }
}
