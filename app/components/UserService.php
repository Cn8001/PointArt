<?php
use PointStart\Attributes\Service;

#[Service(name: "UserService")]
class UserService{
    private string $dataFile = __DIR__ . '/../../data/users.json';

    public function auth(){
        echo "Authentication successfull!";
    }

    public function getUserList(): array{
        if(!file_exists($this->dataFile)){
            return [];
        }
        return json_decode(file_get_contents($this->dataFile), true) ?? [];
    }

    public function addUser($name): string{
        $users = $this->getUserList();
        $users[] = $name;
        if(!is_dir(dirname($this->dataFile))){
            mkdir(dirname($this->dataFile), 0777, true);
        }
        file_put_contents($this->dataFile, json_encode($users));
        return "User added successfully!";
    }

    public function checkUserExists($name): bool{
        $users = $this->getUserList();
        return in_array($name, $users);
    }
}
?>