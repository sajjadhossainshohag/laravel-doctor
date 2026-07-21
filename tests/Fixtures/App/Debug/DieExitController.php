<?php

namespace App\Http\Controllers;

class DieExitController
{
    public function index()
    {
        $users = ['Alice', 'Bob'];

        if (empty($users)) {
            die('No users found');
        }

        exit('Done');
    }
}
