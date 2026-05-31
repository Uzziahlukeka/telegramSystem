<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

/**
 * Web-chat widget for uzhlaravel/telegramsystem.
 *
 * Publish it with `php artisan vendor:publish --tag=telegramsystem-views` and
 * register/mount it as a Volt component (or copy it into your Volt directory).
 * All Telegram I/O is delegated to {@see WebChatService}.
 */
new class extends Component {
    public bool   $open         = false;
    public string $sessionToken = '';
    public ?int   $ticketId     = null;
    public bool   $closed       = false;

    /** @var list<array{direction:string, content:string, time:string}> */
    public array $conversation = [];

    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('nullable|email|max:180')]
    public string $email = '';

    #[Validate('required|string|min:5|max:1000')]
    public string $message = '';

    #[Validate('nullable|string|max:0')]
    public string $website = '';

    public function mount(): void
    {
        $this->sessionToken = session('telegramsystem_web_chat_token') ?? '';

        if ($this->sessionToken === '') {
            $this->sessionToken = (string) Str::uuid();
            session(['telegramsystem_web_chat_token' => $this->sessionToken]);
        }

        if (auth()->check()) {
            $user = auth()->user();
            $this->name  = $user->name  ?? '';
            $this->email = $user->email ?? '';
        }

        $ticket = $this->webChat()->ticketForSession($this->sessionToken);

        if ($ticket) {
            $this->ticketId = $ticket->id;
            $this->loadConversation();
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function poll(): void
    {
        if ($this->ticketId) {
            $this->loadConversation();
        }
    }

    public function send(): void
    {
        // Honeypot: silently drop bot submissions.
        if ($this->website !== '') {
            return;
        }

        $key = 'telegramsystem-webchat:'.(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'message' => __('Too many requests. Please wait :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        RateLimiter::hit($key, 300);

        $this->validate();

        if ($this->closed) {
            throw ValidationException::withMessages([
                'message' => __('This conversation is closed. Please start a new message.'),
            ]);
        }

        $ticket = $this->webChat()->send(
            $this->sessionToken,
            $this->name,
            $this->email ?: null,
            $this->message,
        );

        $this->ticketId = $ticket->id;
        $this->reset(['message']);
        $this->loadConversation();
    }

    private function loadConversation(): void
    {
        if (! $this->ticketId) {
            return;
        }

        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $this->closed = $ticket->status->isClosed();
        $this->conversation = $this->webChat()->conversation($ticket);
    }

    private function webChat(): WebChatService
    {
        return app(WebChatService::class);
    }
}; ?>

<div class="fixed bottom-15 right-6 z-50 flex flex-col items-end gap-3"
     wire:poll.5s="poll">
    @if($open)
    <div class="w-80 sm:w-96 bg-white dark:bg-[#013840] rounded-2xl shadow-2xl ring-1 ring-black/10 dark:ring-white/10 overflow-hidden flex flex-col max-h-128"
         style="animation: chatSlideUp .18s ease-out both;">

        <div class="flex items-center justify-between px-4 py-3.5 bg-[#274DEA] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-white leading-none">{{ __('Support') }}</p>
                    <div class="flex items-center gap-1 mt-0.5">
                        @if($closed)
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                            <span class="text-white/60 text-xs">{{ __('Closed') }}</span>
                        @else
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full" style="animation: pulse 2s ease-in-out infinite"></span>
                            <span class="text-white/75 text-xs">{{ __('Online') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <button wire:click="toggle"
                    class="text-white/60 hover:text-white transition p-1 rounded-lg hover:bg-white/10">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        @if($ticketId)
        <div class="flex-1 overflow-y-auto p-4 space-y-3 scroll-smooth"
             id="chat-messages"
             x-data
             x-init="$el.scrollTop = $el.scrollHeight"
             x-on:livewire:updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">

            <div class="flex gap-2.5">
                <div class="w-6 h-6 rounded-full bg-[#274DEA]/10 dark:bg-[#274DEA]/20 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-xs">👋</span>
                </div>
                <div class="bg-[#EBFFDC] dark:bg-[#011F26] rounded-2xl rounded-tl-none px-3 py-2 text-xs text-black/70 dark:text-white/75 max-w-[80%]">
                    {{ __("Hi! We've got your message and will reply here shortly.") }}
                </div>
            </div>

            @foreach($conversation as $msg)
                @if($msg['direction'] === \Uzhlaravel\TelegramSystem\Tickets\TicketMessage::DIRECTION_FROM_USER)
                <div class="flex justify-end gap-2">
                    <div class="max-w-[80%]">
                        <div class="bg-[#274DEA] text-white rounded-2xl rounded-tr-none px-3 py-2 text-sm leading-relaxed">
                            {{ $msg['content'] }}
                        </div>
                        <p class="text-right text-[10px] text-black/35 dark:text-white/30 mt-1 pr-1">{{ $msg['time'] }}</p>
                    </div>
                </div>
                @else
                <div class="flex gap-2.5">
                    <div class="w-6 h-6 rounded-full bg-[#274DEA] flex items-center justify-center shrink-0 mt-0.5 text-white text-[10px] font-bold">A</div>
                    <div class="max-w-[80%]">
                        <div class="bg-[#EBFFDC] dark:bg-[#011F26] rounded-2xl rounded-tl-none px-3 py-2 text-sm text-black/80 dark:text-white/85 leading-relaxed">
                            {{ $msg['content'] }}
                        </div>
                        <p class="text-[10px] text-black/35 dark:text-white/30 mt-1 pl-1">{{ $msg['time'] }}</p>
                    </div>
                </div>
                @endif
            @endforeach
            @if(!$closed && count($conversation) > 0 && ($conversation[array_key_last($conversation)]['direction'] ?? '') === \Uzhlaravel\TelegramSystem\Tickets\TicketMessage::DIRECTION_FROM_USER)
            <div class="flex gap-2.5">
                <div class="w-6 h-6 rounded-full bg-[#274DEA] flex items-center justify-center shrink-0 mt-0.5 text-white text-[10px] font-bold">A</div>
                <div class="bg-[#EBFFDC] dark:bg-[#011F26] rounded-xl px-3 py-2.5">
                    <span class="flex gap-1">
                        <span class="w-1.5 h-1.5 bg-black/30 dark:bg-white/40 rounded-full" style="animation: bounce 1s infinite 0s"></span>
                        <span class="w-1.5 h-1.5 bg-black/30 dark:bg-white/40 rounded-full" style="animation: bounce 1s infinite .2s"></span>
                        <span class="w-1.5 h-1.5 bg-black/30 dark:bg-white/40 rounded-full" style="animation: bounce 1s infinite .4s"></span>
                    </span>
                </div>
            </div>
            @endif

        </div>

        @if(!$closed)
        <div class="border-t border-black/5 dark:border-white/10 p-3 shrink-0">
            <form wire:submit="send" class="flex gap-2">
                <div class="flex-1">
                    <input type="text"
                           wire:model="message"
                           placeholder="{{ __('Reply…') }}"
                           autocomplete="off"
                           class="w-full px-3.5 py-2 rounded-xl text-sm bg-[#EBFFDC] dark:bg-[#011F26] text-black/85 dark:text-white border border-black/10 dark:border-white/10 focus:border-[#274DEA] focus:ring-2 focus:ring-[#274DEA]/20 outline-none transition placeholder:text-black/35 dark:placeholder:text-white/30" />
                    @error('message')
                    <p class="text-xs text-[#FF513D] mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send"
                        class="w-9 h-9 flex items-center justify-center bg-[#274DEA] hover:bg-[#1A3BC8] disabled:opacity-50 text-white rounded-xl transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>
        @else
        <div class="border-t border-black/5 dark:border-white/10 p-3 text-center shrink-0">
            <p class="text-xs text-black/45 dark:text-white/40">{{ __('This conversation is closed.') }}</p>
        </div>
        @endif


        @else
        <div class="p-4 flex-1 overflow-y-auto">
            <div class="flex gap-2.5 mb-4">
                <div class="w-7 h-7 rounded-full bg-[#274DEA]/10 dark:bg-[#274DEA]/20 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-sm leading-none">👋</span>
                </div>
                <div class="bg-[#EBFFDC] dark:bg-[#011F26] rounded-2xl rounded-tl-none px-3.5 py-2.5 text-sm text-black/75 dark:text-white/80 leading-relaxed max-w-[85%]">
                    {{ __('How can we help you?') }}
                </div>
            </div>

            <form wire:submit="send" class="space-y-3">
                <div aria-hidden="true" style="position:absolute;left:-9999px;overflow:hidden;opacity:0;pointer-events:none;">
                    <input type="text" wire:model="website" name="website" tabindex="-1" autocomplete="off"/>
                </div>

                <div>
                    <input type="text"
                           wire:model="name"
                           placeholder="{{ __('Name') }}"
                           class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-[#EBFFDC] dark:bg-[#011F26] text-black/85 dark:text-white border border-black/10 dark:border-white/10 focus:border-[#274DEA] focus:ring-2 focus:ring-[#274DEA]/20 outline-none transition placeholder:text-black/35 dark:placeholder:text-white/30" />
                    @error('name') <p class="text-xs text-[#FF513D] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <input type="email"
                           wire:model="email"
                           placeholder="{{ __('Email — so we can reach you') }}"
                           class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-[#EBFFDC] dark:bg-[#011F26] text-black/85 dark:text-white border border-black/10 dark:border-white/10 focus:border-[#274DEA] focus:ring-2 focus:ring-[#274DEA]/20 outline-none transition placeholder:text-black/35 dark:placeholder:text-white/30" />
                    @error('email') <p class="text-xs text-[#FF513D] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <textarea wire:model="message"
                              rows="3"
                              placeholder="{{ __('What do you need help with?') }}"
                              class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-[#EBFFDC] dark:bg-[#011F26] text-black/85 dark:text-white border border-black/10 dark:border-white/10 focus:border-[#274DEA] focus:ring-2 focus:ring-[#274DEA]/20 outline-none transition resize-none placeholder:text-black/35 dark:placeholder:text-white/30">
                    </textarea>
                    @error('message') <p class="text-xs text-[#FF513D] mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send"
                        class="w-full inline-flex items-center justify-center gap-2 bg-[#274DEA] hover:bg-[#1A3BC8] disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-all duration-200">
                    <span wire:loading.remove wire:target="send">{{ __('Send message') }}</span>
                    <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                </button>
            </form>
        </div>
        @endif

    </div>
    @endif

    <button wire:click="toggle"
            title="{{ __('Chat with us') }}"
            class="relative w-14 h-14 bg-[#274DEA] hover:bg-[#1A3BC8] text-white rounded-full shadow-2xl flex items-center justify-center transition-all duration-200 hover:scale-110">
        @if($open)
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        @else
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        @endif
        <span class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white {{ $closed ? 'bg-gray-400' : 'bg-green-400' }}"></span>
    </button>

    <style>
        @keyframes chatSlideUp {
            from { opacity: 0; transform: translateY(10px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)   scale(1);    }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0);    }
            50%       { transform: translateY(-4px); }
        }
    </style>

</div>
