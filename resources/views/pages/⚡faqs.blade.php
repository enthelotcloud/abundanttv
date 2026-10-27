<?php

use App\Models\Faq;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function with(): array
    {
        $faqs = Faq::published()->get();

        return [
            'faqs'  => $faqs,
            'grouped' => $faqs->groupBy(fn ($f) => $f->category ?: 'General'),
        ];
    }
};
?>

<div class="mx-auto max-w-3xl px-4 py-12">
    <h1 class="text-3xl font-bold">Frequently Asked Questions</h1>
    <p class="mt-2 text-zinc-500">Everything you need to know about AbundantTV.</p>

    @foreach($grouped as $category => $items)
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-semibold text-indigo-600 dark:text-indigo-400">{{ $category }}</h2>
            <div class="space-y-3">
                @foreach($items as $faq)
                    <details class="group rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <summary class="flex cursor-pointer items-center justify-between font-medium">
                            {{ $faq->question }}
                            <flux:icon name="chevron-down"
                                       class="h-4 w-4 text-zinc-400 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $faq->answer }}
                        </p>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    @if($faqs->isEmpty())
        <p class="mt-10 rounded-lg border border-dashed border-zinc-300 p-12 text-center text-sm text-zinc-500 dark:border-zinc-700">
            No FAQs published yet.
        </p>
    @endif
</div>
