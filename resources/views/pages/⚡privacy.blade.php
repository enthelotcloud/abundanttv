<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Static page
};
?>

<div class="bg-white dark:bg-zinc-950 min-h-screen">

    {{-- ═══════════ HERO ═══════════ --}}
    <div class="relative overflow-hidden border-b border-zinc-200/50 dark:border-zinc-800/50">
        <div class="absolute -left-40 top-0 -z-10 h-[400px] w-[400px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
        <div class="absolute -right-40 top-10 -z-10 h-[400px] w-[400px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Legal
            </span>
            <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl">
                Privacy Policy
            </h1>
            <p class="mt-3 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Last updated: {{ now()->format('F j, Y') }}
            </p>
        </div>
    </div>

    {{-- ═══════════ CONTENT ═══════════ --}}
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8">

            {{-- Left Sidebar: Table of Contents --}}
            <div class="lg:col-span-4">
                <div class="sticky top-28 rounded-2xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-white">On this page</h3>
                    <nav class="mt-4 flex flex-col space-y-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <a href="#info-collect" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">1. Information We Collect</a>
                        <a href="#info-use" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">2. How We Use It</a>
                        <a href="#sharing" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">3. Sharing of Information</a>
                        <a href="#retention" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">4. Data Retention</a>
                        <a href="#cookies" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">5. Cookies & Tracking</a>
                        <a href="#security" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">6. Security</a>
                        <a href="#children" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">7. Children's Privacy</a>
                    </nav>
                </div>
            </div>

            {{-- Right Side: The Legal Text --}}
            <div class="lg:col-span-8 space-y-8 text-zinc-600 dark:text-zinc-400">

                <p class="text-lg leading-relaxed">
                    At <strong class="text-zinc-900 dark:text-white">AbundantTV</strong>, we are committed to protecting your privacy and ensuring that your personal information is handled securely. This Privacy Policy explains what data we collect, how we use it, and your rights.
                </p>

                <section id="info-collect">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">1. Information We Collect</h2>
                    <p class="mb-4 leading-relaxed">We collect information in three main ways:</p>
                    <ul class="list-disc space-y-3 pl-5 leading-relaxed marker:text-zinc-400">
                        <li><strong>Live Chat & Device Identity:</strong> When you post a comment in our live chat, we collect your <em>Name</em>, <em>Phone Number</em>, <em>IP Address</em>, and a <em>Device ID</em> (stored in your browser's local storage). We require a phone number to deter spam and ensure a safe environment for all viewers.</li>
                        <li><strong>Contact Form:</strong> When you reach out via our contact page, we collect your name, email address, phone number (optional), and message.</li>
                        <li><strong>Automated Analytics:</strong> Like most websites, we log standard technical data (IP addresses, browser type, and timestamps) to measure viewership and improve our broadcast quality.</li>
                    </ul>
                </section>

                <section id="info-use">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">2. How We Use Your Information</h2>
                    <p class="mb-4 leading-relaxed">We use the information we collect strictly to:</p>
                    <ul class="list-disc space-y-3 pl-5 leading-relaxed marker:text-zinc-400">
                        <li>Publish your name and message in the public live chat.</li>
                        <li>Enforce our community guidelines, rate limits, and device bans.</li>
                        <li>Respond to your inquiries submitted via the contact form.</li>
                        <li>Generate anonymous statistics (e.g., "500 people watched the Sunday Service") for our internal reporting.</li>
                    </ul>
                </section>

                <section id="sharing">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">3. Sharing of Information</h2>
                    <p class="mb-4 leading-relaxed"><strong class="text-zinc-900 dark:text-white">We do not sell, rent, or trade your personal information.</strong> We only share information under the following circumstances:</p>
                    <ul class="list-disc space-y-3 pl-5 leading-relaxed marker:text-zinc-400">
                        <li><strong>Service Providers:</strong> We may use third-party hosts (like StreamKeys) to deliver the video feed, which may process IP addresses to ensure the stream works.</li>
                        <li><strong>Legal Obligations:</strong> We may disclose information if required to do so by law, or if we believe in good faith that such action is necessary to protect the rights and safety of AbundantTV, our users, or the public.</li>
                    </ul>
                </section>

                <section id="retention">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">4. Data Retention & Deletion</h2>
                    <p class="leading-relaxed">
                        Contact form submissions are retained until their purpose is fulfilled. Live chat messages remain in our database as part of the broadcast archive, though flagged messages are hidden from the public view. If you wish to have a specific message or contact form record deleted, please contact us.
                    </p>
                </section>

                <section id="cookies">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">5. Cookies & Tracking Technologies</h2>
                    <p class="mb-4 leading-relaxed">We use local storage and cookies to:</p>
                    <ul class="list-disc space-y-3 pl-5 leading-relaxed marker:text-zinc-400">
                        <li>Remember your name and phone number so you don't have to re-enter them every time you chat.</li>
                        <li>Store your unique Device ID to enforce our rate limits and moderation policies.</li>
                    </ul>
                    <p class="leading-relaxed">You can clear these cookies at any time through your browser settings, though doing so will reset your device identity and may require you to enter your details again.</p>
                </section>

                <section id="security">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">6. Security</h2>
                    <p class="leading-relaxed">
                        We implement reasonable security measures to protect your personal data. However, please be aware that no method of transmission over the internet is 100% secure. You submit information at your own risk.
                    </p>
                </section>

                <section id="children">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">7. Children's Privacy</h2>
                    <p class="leading-relaxed">
                        AbundantTV is a family-friendly platform. We do not knowingly collect personal information from children under the age of 13. If you are under 13, please do not submit your phone number or email in the chat or contact forms without parental consent.
                    </p>
                </section>

                <div class="border-t border-zinc-200 pt-8 mt-12 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500">
                        If you have any questions about this Privacy Policy, please
                        <a href="{{ route('contact') }}" class="font-semibold text-cyan-600 hover:underline dark:text-cyan-400">contact us</a>.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>
