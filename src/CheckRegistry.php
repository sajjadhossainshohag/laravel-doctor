<?php

namespace SajjadHossain\Doctor;

class CheckRegistry
{
    private array $checks = [];

    public function register(string $checkClass): void
    {
        if (!in_array($checkClass, $this->checks, true)) {
            $this->checks[] = $checkClass;
        }
    }

    public function all(): array
    {
        return $this->checks;
    }

    public function byCategory(string $category): array
    {
        return array_values(
            array_filter($this->checks, fn (string $class) => $class::category() === $category)
        );
    }

    public function reset(): void
    {
        $this->checks = [];
    }
}
