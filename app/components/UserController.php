<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\Service;

#[Router]
class UserController {

    #[Route('/user-list')]
    public function index(): string {
        return 'user.list';
    }

    #[Route('/user-show')]
    public function show(): string {
        return 'user.show';
    }

    public function helper(): string {
        return 'no-route';
    }
}
