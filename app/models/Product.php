<?php
use PointStart\ORM\Model;
use PointStart\Attributes\Entity;
use PointStart\Attributes\Column;
use PointStart\Attributes\Id;

#[Entity('products')]
class Product extends Model {
    #[Id]
    public ?int $id = null;

    #[Column('name', 'varchar')]
    public string $name;

    #[Column('price', 'decimal')]
    public float $price;

    #[Column('stock', 'int')]
    public int $stock;

    #[Column('active', 'tinyint')]
    public bool $active = true;
}
?>
