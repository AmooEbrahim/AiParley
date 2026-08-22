<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'globalPrompt' => Setting::get('global_prompt'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'global_prompt' => ['nullable', 'string', 'max:50000'],
        ]);

        Setting::put('global_prompt', $data['global_prompt'] ?? null);

        return redirect()->route('settings.edit')->with('success', 'Global prompt saved. It applies from the next generated message.');
    }
}
