<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class UserController
{
    public function store(Request $request)
    {
        // Smell: $request->all() passed directly to create
        return User::create($request->all());
    }

    public function forceStore(Request $request)
    {
        // Smell: forceCreate with request->all()
        return User::forceCreate($request->all());
    }

    public function createOrFailStore(Request $request)
    {
        // Smell: createOrFail with request->all()
        return User::createOrFail($request->all());
    }

    public function facadeStore()
    {
        // Smell: Request facade all()
        return User::create(RequestFacade::all());
    }

    public function inputStore(Request $request)
    {
        // Smell: $request->input() without args is equivalent to all()
        return User::create($request->input());
    }

    public function facadeInputStore()
    {
        // Smell: Request facade input() without args
        return User::create(RequestFacade::input());
    }

    public function postStore(Request $request)
    {
        // Smell: $request->post() without args
        return User::create($request->post());
    }

    public function queryStore(Request $request)
    {
        // Smell: $request->query() without args
        return User::create($request->query());
    }

    public function updateStore(Request $request, User $user)
    {
        // Smell: update with raw request input
        return $user->update($request->all());
    }

    public function firstOrCreateStore(Request $request)
    {
        // Smell: firstOrCreate with raw request input
        return User::firstOrCreate($request->all());
    }

    public function updateOrCreateStore(Request $request)
    {
        // Smell: updateOrCreate with raw request input
        return User::updateOrCreate($request->all());
    }
}
