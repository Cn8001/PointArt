<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Attributes\RequestParam;
use PointStart\Core\Renderer;
use PointStart\Attributes\Wired;

#[Router(name: 'user', path: '/user')]
class UserController {
    #[Wired]
    private UserRepository $userRepository;

    // GET /user/list
    #[Route('/list', HttpMethod::GET)]
    public function index(): string {
        $users = $this->userRepository->findAll();
        return Renderer::render('user.list', ['users' => $users]);
    }

    // GET /user/show/{id}
    #[Route('/show/{id}', HttpMethod::GET)]
    public function show(int $id): string {
        $user = User::find($id);
        if ($user === null) {
            return Renderer::render('user.notfound');
        }
        return Renderer::render('user.show', ['user' => $user]);
    }

    // GET /user/search?name=foo
    #[Route('/search', HttpMethod::GET)]
    public function search(string $name = ''): string {
        $users = $this->userRepository->findByName($name);
        return Renderer::render('user.list', ['users' => $users]);
    }

    // POST /user/create
    #[Route('/create', HttpMethod::POST)]
    public function create(
        #[RequestParam] string $name,
        #[RequestParam] string $email
    ): string {
        $user        = new User();
        $user->name  = $name;
        $user->email = $email;
        $user->save();
        return Renderer::render('user.show', ['user' => $user]);
    }

    // POST /user/update/{id}
    #[Route('/update/{id}', HttpMethod::POST)]
    public function update(
        int $id,
        #[RequestParam] string $name,
        #[RequestParam] string $email
    ): string {
        $user = User::find($id);
        if ($user === null) {
            return Renderer::render('user.notfound');
        }
        $user->name  = $name;
        $user->email = $email;
        $user->save();
        return Renderer::render('user.show', ['user' => $user]);
    }

    // POST /user/delete/{id}
    #[Route('/delete/{id}', HttpMethod::POST)]
    public function delete(int $id): string {
        $this->userRepository->deleteById($id);
        $users = $this->userRepository->findAll();
        return Renderer::render('user.list', ['users' => $users]);
    }

    public function helper(): string {
        return 'no-route';
    }
}
