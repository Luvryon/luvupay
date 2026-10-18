<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DocsController
{
    public function index(): View
    {
        return view('docs.index');
    }

    public function api(): View
    {
        return view('docs.api');
    }

    public function webhooks(): View
    {
        return view('docs.webhooks');
    }

    public function postman(): View
    {
        return view('docs.postman');
    }

    public function cookbook(): View
    {
        return view('docs.cookbook');
    }

    public function errors(): View
    {
        return view('docs.errors');
    }

    public function octane(): View
    {
        return view('docs.octane');
    }
}
