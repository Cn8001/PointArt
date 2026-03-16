<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Core\Renderer;

#[Router(path: "/", name: "TestController")]
class TestController{

    #[Route("/test/{id}", HttpMethod::GET)]
    public function test($id){
        return Renderer::render("test",["id" => $id]);
    }

    #[Route("", HttpMethod::GET)]
    public function get(){
        return "Hello world!";
    }
}
?>