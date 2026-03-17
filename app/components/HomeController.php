<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Core\Renderer;

#[Router(name: 'home', path: '/')]
class HomeController {

    // GET /
    #[Route('/', HttpMethod::GET)]
    public function index(): string {
        return Renderer::render('home');
    }
}
