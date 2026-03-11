<?php
use PointStart\Attributes\Service;

#[Service(name: "UserService")]
class UserService{
    public function auth(){
        echo "Authentication successfull!";
    }
}
?>