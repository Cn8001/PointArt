<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;

#[Router(path: "/test", name: "TestController")]
class TestController{

    #[Route("/test", "GET")]
    public function test(){
        return 'test';
    }
}
?>