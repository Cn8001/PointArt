<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\Service;
use PointStart\Attributes\Wired;

#[Router(name: 'user', path: '/user')]
class UserController {
    #[Wired]
    private UserService $userService;

    #[Route('/user-list')]
    public function index() {
        return $this->userService->getUserList();
    }

    #[Route('/user-show/{id}')]
    public function show($id): string {
        return 'user.show ' . $id;
    }

    public function helper(): string {
        return 'no-route';
    }
}
