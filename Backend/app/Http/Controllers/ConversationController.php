<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function historial(): View
    {
        $conversations = Conversation::query()
            ->with('contact')
            ->where('assigned_user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('agente.historial', compact('conversations'));
    }
}
