<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;

#[Router(path: "/", name: "TestController")]
class TestController{

    #[Route("/test", "GET")]
    public function test(){
        require_once __DIR__ . "/../views/test.php";
    }

    #[Route("","GET")]
    public function get(){
        return "Hello world!";
    }
}
?>