<?php
use PointStart\ORM\Repository;
use PointStart\Attributes\Query;

abstract class ProductRepository extends Repository {
    protected string $entityClass = Product::class;

    // ── Custom SQL ────────────────────────────────────────────────────────────

    #[Query("SELECT * FROM products WHERE active = 1 ORDER BY name ASC")]
    abstract public function findAllActive(): array;

    #[Query("SELECT * FROM products WHERE price <= ? AND active = 1 ORDER BY price ASC")]
    abstract public function findAffordable(float $maxPrice): array;

    // ── Dynamic finders via __call ────────────────────────────────────────────

    abstract public function findByName(string $name): array;
    abstract public function findByStockLessThan(int $threshold): array;
    abstract public function countByActive(bool $active): int;
    abstract public function existsByName(string $name): bool;
    abstract public function deleteByActive(bool $active): void;
}
?>
