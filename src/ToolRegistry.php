<?php

declare(strict_types=1);

namespace EorBah545\AI;

final class ToolRegistry
{
    /** @var array<string,Tool> */
    private array $tools = [];

    public function register(Tool $tool): self
    {
        $this->tools[$tool->name] = $tool;

        return $this;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return Tool[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function isEmpty(): bool
    {
        return $this->tools === [];
    }
}
