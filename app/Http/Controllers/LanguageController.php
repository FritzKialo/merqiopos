<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): \Illuminate\Http\RedirectResponse
    {
        if (in_array($locale, ['en', 'sw'])) {
            session(['locale' => $locale]);
            app()->setLocale($locale);
        }
        return back();
    }
}
