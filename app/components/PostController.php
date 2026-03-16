<?php
use PointStart\Attributes\Route;
use PointStart\Attributes\Router;
use PointStart\Attributes\HttpMethod;
use PointStart\Attributes\Wired;
use PointStart\Attributes\RequestParam;

#[Router(path: "/submit", name: "Post test")]
class PostController{
    #[Wired]
    public UserService $userService;

    #[Route("/add-user", HttpMethod::POST)]
    public function postName(#[RequestParam] $name){
        http_response_code(201);
        $this->userService->addUser($name);
    }

    #[Route("/check", HttpMethod::GET)]
    public function getAllNames(){
        return $this->userService->getUserList();
    }

    #[Route("/check-user/{username}", HttpMethod::GET)]
    public function checkUser($username){
        return $this->userService->checkUserExists($username);
    }

}
?>