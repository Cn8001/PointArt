<?php
use PointStart\ORM\Model;
use PointStart\Attributes\Entity;
use PointStart\Attributes\Column;
use PointStart\Attributes\Id;

#[Entity('users')]
class User extends Model{
    #[Id]
    public ?int $id = null;

    #[Column('name', 'varchar')]
    public string $name;

    #[Column('email', 'varchar')]
    public string $email;
}
?>
