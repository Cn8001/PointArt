<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Core\Renderer;

#[Router(path: "/", name: "TestController")]
class TestController{

    #[Route("/test/{id}", "GET")]
    public function test($id){
        return Renderer::render("test",["id" => $id]);
    }

    #[Route("","GET")]
    public function get(){
        return "Hello world!";
    }
}
?>