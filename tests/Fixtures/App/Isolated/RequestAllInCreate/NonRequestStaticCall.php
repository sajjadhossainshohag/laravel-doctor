<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;

class ProductController
{
    public function store()
    {
        // Not a smell: Product::all() is Eloquent, not Request
        return User::create(Product::all());
    }
}
