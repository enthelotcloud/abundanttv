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
                Terms of Service
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
                        <a href="#service" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">1. Use of Service</a>
                        <a href="#intellectual-property" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">2. Intellectual Property</a>
                        <a href="#user-content" class="font-semibold text-red-700 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition">3. Chat & User Content</a>
                        <a href="#donations" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">4. Donations</a>
                        <a href="#liability" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">5. Limitation of Liability</a>
                        <a href="#third-party" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">6. Links to Third Parties</a>
                        <a href="#changes" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">7. Changes to Terms</a>
                        <a href="#law" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition">8. Governing Law</a>
                    </nav>
                </div>
            </div>

            {{-- Right Side: The Legal Text --}}
            <div class="lg:col-span-8 space-y-8 text-zinc-600 dark:text-zinc-400">

                <p class="text-lg leading-relaxed">
                    Welcome to <strong class="text-zinc-900 dark:text-white">AbundantTV</strong>. By accessing or using our website, live stream, and interactive features, you agree to comply with and be bound by the following Terms of Service. If you do not agree to these terms, please do not use our services.
                </p>

                <section id="service">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">1. Use of Service</h2>
                    <p class="leading-relaxed">
                        AbundantTV provides Gospel-centered broadcasting, entertainment, and community features. You agree to use our services only for lawful, personal, and non-commercial purposes. You may not use our broadcast for any unauthorized commercial ventures without prior written consent from AbundantTV.
                    </p>
                </section>

                <section id="intellectual-property">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">2. Intellectual Property</h2>
                    <p class="leading-relaxed">
                        All content broadcasted on AbundantTV—including sermons, music, graphics, logos, and recorded video—is the property of AbundantTV, Gospel Celebration Church, or its licensors. You may not download, reproduce, or distribute our content without explicit permission.
                    </p>
                </section>

                <section id="user-content" class="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900/50 dark:bg-red-950/20">
                    <h2 class="mb-4 text-2xl font-bold text-red-800 dark:text-red-400">3. User-Generated Content & Live Chat Policy</h2>
                    <p class="mb-4 leading-relaxed text-red-900 dark:text-red-200">
                        Our live stream features a public chat function. By participating in the chat, you acknowledge and agree to the following:
                    </p>
                    <ul class="list-disc space-y-3 pl-5 leading-relaxed text-red-900 dark:text-red-200 marker:text-red-500">
                        <li><strong>Zero Tolerance for Abuse:</strong> You may not post content that is defamatory, racially or tribally abusive, sexually explicit, hateful, or generally offensive.</li>
                        <li><strong>Automated Moderation:</strong> To protect our community, we utilize automated filters (a banned words list) to flag and grey out offensive messages. <em>However, you acknowledge that these filters are not 100% foolproof and may not catch all inappropriate language.</em></li>
                        <li><strong>Disclaimer of Liability:</strong> AbundantTV is an interactive platform. The comments and messages posted by users do not reflect the views of AbundantTV, its founders, or staff. We are not legally responsible or liable for any offense, distress, or damages caused by the content posted by other users.</li>
                        <li><strong>Right to Moderate:</strong> We reserve the absolute right, at our sole discretion, to remove any comment, ban users, or restrict access to the chat function for any reason, without prior notice.</li>
                        <li><strong>Device Identification:</strong> To enforce our bans and rate limits, we may log your IP address and assign a unique device identifier to your browser.</li>
                    </ul>
                </section>

                <section id="donations">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">4. Donations and Financial Contributions</h2>
                    <p class="leading-relaxed">
                        Any financial contributions made through our website are voluntary. If we implement digital payment gateways, you agree to provide accurate and complete payment information. AbundantTV is not responsible for any bank or transaction fees incurred.
                    </p>
                </section>

                <section id="liability">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">5. Limitation of Liability</h2>
                    <p class="leading-relaxed">
                        Our live stream is provided "as is" and "as available." We do not guarantee that the stream will be uninterrupted, error-free, or free of technical glitches. AbundantTV shall not be liable for any direct, indirect, or incidental damages arising from your inability to access the stream.
                    </p>
                </section>

                <section id="third-party">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">6. Links to Third Parties</h2>
                    <p class="leading-relaxed">
                        Our website may contain links to external websites (e.g., social media, payment processors). We do not control and are not responsible for the content or privacy practices of these third-party sites.
                    </p>
                </section>

                <section id="changes">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">7. Changes to Terms</h2>
                    <p class="leading-relaxed">
                        We may update these Terms from time to time. Your continued use of the website after any changes signifies your acceptance of the new Terms.
                    </p>
                </section>

                <section id="law">
                    <h2 class="mb-4 text-2xl font-bold text-zinc-900 dark:text-white">8. Governing Law</h2>
                    <p class="leading-relaxed">
                        These Terms shall be governed by and construed in accordance with the laws of the Republic of Kenya.
                    </p>
                </section>

                <div class="border-t border-zinc-200 pt-8 mt-12 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500">
                        If you have any questions about these Terms, please
                        <a href="{{ route('contact') }}" class="font-semibold text-cyan-600 hover:underline dark:text-cyan-400">contact us</a>.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>
