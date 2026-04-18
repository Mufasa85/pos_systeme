<?php

namespace App\Controllers;

class UserController
{
    public function delete()
    {
        $id = $_POST['id'];
        $user = new \App\Models\User();
        if ($user->exist($id)) {
            $user->deleteUser($id);
            echo"utilisateur supprimer avec success";
        } else {
            echo "utilisateur inexistant error 404";
        }


    }

}
