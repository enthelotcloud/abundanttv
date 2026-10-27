<?php

use App\Models\Contact;
use App\Models\Contactform;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name    = '';
    public string $email   = '';
    public string $phone   = '';
    public string $subject = '';
    public string $message = '';

    public bool $showSuccess = false;
    public bool $isRateLimited = false;
    public string $limitMessage = '';

    public function mount(): void
    {
        $this->checkRateLimit();
    }

    private function checkRateLimit(): void
    {
        $key = 'contact-form:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            $hours = ceil($seconds / 3600);

            $this->isRateLimited = true;
            $this->showSuccess = true;
            $this->limitMessage = "You have reached the limit of 2 messages per day. Please try again in {$hours} hours.";
        }
    }

    public function submit(): void
    {
        $key = 'contact-form:' . request()->ip();

        // 1. Rate Limiting (Max 2 submissions per 24 hours)
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $this->checkRateLimit();
            return;
        }

        // 2. Validation
        $this->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // 3. Save to Database
        Contactform::create([
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'subject'    => $this->subject,
            'message'    => $this->message,
            'ip_address' => request()->ip(),
            'is_read'    => false,
        ]);

        // 4. Hit the Rate Limiter (Lockout for 24 hours = 86400 seconds)
        RateLimiter::hit($key, 86400);

        // 5. Hide Form & Notify
        $this->reset(['name', 'email', 'phone', 'subject', 'message']);
        $this->showSuccess = true;
        Flux::toast('Message sent! We will get back to you soon.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'socials' => Contact::active()->get(),
        ];
    }
};

?>

<div class="bg-white dark:bg-zinc-950 min-h-screen">

    {{-- ═══════════ HERO: MATCHING THEME ═══════════ --}}
    <div class="relative overflow-hidden">
        {{-- Asymmetrical Theme Glows --}}
        <div class="absolute -left-40 top-0 -z-10 h-[500px] w-[500px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
        <div class="absolute -right-40 top-20 -z-10 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>

        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-24 lg:px-8">
            {{-- Gold Badge --}}
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Get in touch
            </span>

            {{-- Heading --}}
            <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl lg:text-6xl">
                Contact us.
            </h1>

            {{-- Subheading --}}
            <p class="mt-6 max-w-7xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                Have a prayer request, a question about our shows, or want to partner with the ministry? Send us a message and our team will get back to you.
            </p>
        </div>
    </div>

    {{-- ═══════════ MAIN CONTENT (Form + Info) ═══════════ --}}
    <div class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-5 lg:gap-8">

            {{-- Left Side: Contact Info --}}
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-zinc-900 dark:text-white">Contact Information</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Reach us directly through any of these channels.</p>

                    <div class="mt-8 space-y-6">
                        @forelse($socials as $social)
                            <a href="{{ $social->href }}" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-4 transition">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-50 ring-1 ring-zinc-200 transition group-hover:bg-cyan-50 group-hover:ring-cyan-200 dark:bg-zinc-800 dark:ring-zinc-700 dark:group-hover:bg-cyan-950/50 dark:group-hover:ring-cyan-900">
                                    @if($social->icon_path)
                                        <img src="{{ asset('storage/'.$social->icon_path) }}" alt="{{ $social->label }}" class="h-6 w-6 object-contain dark:brightness-200" />
                                    @else
                                        <flux:icon name="link" class="h-5 w-5 text-zinc-500 group-hover:text-cyan-600 dark:text-zinc-400 dark:group-hover:text-cyan-400" />
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-900 transition group-hover:text-cyan-600 dark:text-white dark:group-hover:text-cyan-400">{{ $social->label }}</p>
                                    <p class="text-xs text-zinc-500">{{ $social->value }}</p>
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-zinc-500 italic">No contact channels configured yet.</p>
                        @endforelse
                    </div>

                    <hr class="my-8 border-zinc-200 dark:border-zinc-800" />

                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Broadcast Studio</h4>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                            Gospel Celebration Church<br/>
                            Nairobi, Kenya
                        </p>
                    </div>
                </div>
            </div>

            {{-- Right Side: Form or Success State --}}
            <div class="lg:col-span-3">
                <div class="rounded-3xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">

                    @if($showSuccess)
                        {{-- SUCCESS STATE --}}
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div @class([
                                'flex h-20 w-20 items-center justify-center rounded-full mb-6',
                                'bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-400' => ! $isRateLimited,
                                'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400' => $isRateLimited,
                            ])>
                                @if($isRateLimited)
                                    <flux:icon name="clock" class="h-10 w-10" />
                                @else
                                    <flux:icon name="check-circle" class="h-10 w-10" />
                                @endif
                            </div>

                            <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">
                                {{ $isRateLimited ? 'Daily Limit Reached' : 'Message Sent!' }}
                            </h3>

                            <p class="mt-4 max-w-md text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                                @if($isRateLimited)
                                    {{ $limitMessage }}
                                @else
                                    Thank you for reaching out. We have received your message and you'll be contacted shortly.
                                @endif
                            </p>

                            @if(! $isRateLimited)
                                <div class="mt-8 rounded-xl bg-zinc-50 p-4 text-sm text-zinc-500 dark:bg-zinc-950/50">
                                    Want to send another message? You have <strong class="text-zinc-900 dark:text-white">1</strong> remaining for today.
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- CONTACT FORM --}}
                        <form wire:submit="submit" class="space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2">
                                {{-- Name --}}
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-zinc-900 dark:text-white">Your Name</label>
                                    <input type="text" wire:model="name" placeholder="John Doe" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-600 dark:focus:bg-zinc-900" required>
                                    @error('name') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                {{-- Email --}}
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-zinc-900 dark:text-white">Email Address</label>
                                    <input type="email" wire:model="email" placeholder="john@example.com" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-600 dark:focus:bg-zinc-900" required>
                                    @error('email') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                {{-- Phone --}}
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-zinc-900 dark:text-white">Phone (Optional)</label>
                                    <input type="tel" wire:model="phone" placeholder="+254 712 345 678" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-600 dark:focus:bg-zinc-900">
                                    @error('phone') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                {{-- Subject --}}
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-zinc-900 dark:text-white">Subject (Optional)</label>
                                    <input type="text" wire:model="subject" placeholder="Prayer Request / Partnership" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-600 dark:focus:bg-zinc-900">
                                    @error('subject') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Message --}}
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-zinc-900 dark:text-white">Your Message</label>
                                <textarea wire:model="message" rows="6" placeholder="How can we help you?" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-600 dark:focus:bg-zinc-900" required></textarea>
                                @error('message') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            {{-- Submit Button --}}
                            <div class="flex items-center justify-between pt-2">
                                <p class="text-xs text-zinc-500">Max 2 messages per day.</p>

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-8 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-400 focus:outline-none focus:ring-4 focus:ring-cyan-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove>Send Message</span>
                                    <span wire:loading class="flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="4"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Sending...
                                    </span>
                                </button>
                            </div>
                        </form>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
