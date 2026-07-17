<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(?Conversation $conversation = null): View
    {
        $conversations = Conversation::query()
            ->with(['contact', 'latestMessage', 'assignee:id,name,last_name'])
            ->orderByDesc('last_message_at')->orderByDesc('id')->get();
        $selected = $conversation ?: $conversations->first();
        $selected?->load(['contact', 'messages' => fn ($query) => $query->orderBy('created_at'), 'messages.sender']);

        return view('chats.index', compact('conversations', 'selected'));
    }
}
