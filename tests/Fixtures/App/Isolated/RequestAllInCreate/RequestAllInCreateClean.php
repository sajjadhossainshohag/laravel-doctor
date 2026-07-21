<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController
{
    public function storeSafe(Request $request)
    {
        // Safe: only() limits fields
        return User::create($request->only(['name', 'email']));
    }

    public function storeValidated(Request $request)
    {
        // Safe: validated() from FormRequest
        return User::create($request->validated());
    }

    public function storeSafeWithSafe(Request $request)
    {
        // Safe: safe() from FormRequest
        return User::create($request->safe());
    }

    public function storeFiltered(Request $request)
    {
        // Safe: all() with key filter
        return User::create($request->all(['name', 'email']));
    }

    public function storeVariable()
    {
        // Safe: data comes from a variable, not directly from request
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        return User::create($data);
    }

    public function storeExcept(Request $request)
    {
        // Safe: except() removes specific fields
        return User::create($request->except(['_token']));
    }

    public function storeCollect(Request $request)
    {
        // Safe: collect() wraps in collection, not raw all()
        return User::create($request->collect(['name', 'email'])->all());
    }
}
