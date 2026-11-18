<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SubtitleTemplateController extends Controller
{
    public function index(): View
    {
        return view('subtitle-templates.index');
    }
}
