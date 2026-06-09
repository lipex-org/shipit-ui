<?php
/** @var $this \CodeIgniter\View\View */
?>
<?php $this->extend('layouts/app.layout.php') ?>

<?php $this->section('header') ?>
    <title>Home - ShipIt Control Panel</title>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="min-h-[90vh] flex flex-col items-center justify-center text-center px-4 bg-gray-950">
    <div class="space-y-8 max-w-3xl">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold tracking-wider uppercase">
            <span>✨ Version 0.0.3-alpha Ready</span>
        </div>
        <h1 class="text-6xl md:text-7xl font-extrabold text-white tracking-tight">
            Deploy with <span class="text-blue-500 drop-shadow-[0_0_15px_rgba(59,130,246,0.3)]">ShipIt</span>
        </h1>
        <p class="text-xl text-gray-400 leading-relaxed max-w-2xl mx-auto">
            The missing bridge between Git and your server. Simple, secure, and zero-downtime deployments for Shared Hosting & VPS.
        </p>
        <div class="flex flex-wrap justify-center gap-6 pt-6">
            <a href="<?= site_url('login') ?>" class="px-10 py-4 bg-blue-600 text-white font-bold rounded-xl shadow-[0_10px_20px_-5px_rgba(37,99,235,0.4)] hover:bg-blue-500 transition-all transform hover:-translate-y-1 active:scale-95">
                Get Started
            </a>
            <a href="https://github.com/gilads-otiannoh254/shipit" target="_blank" data-turbo="false" class="px-10 py-4 bg-gray-900 text-white font-bold rounded-xl border border-gray-800 shadow-xl hover:bg-gray-800 transition-all transform hover:-translate-y-1 active:scale-95">
                View on GitHub
            </a>
        </div>
    </div>

    <div class="mt-32 grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl w-full">
        <div class="p-8 bg-gray-900/50 rounded-2xl border border-gray-800 shadow-2xl backdrop-blur-sm group hover:border-blue-500/30 transition-colors">
            <div class="w-14 h-14 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-400 mb-6 mx-auto group-hover:scale-110 transition-transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">Zero-Downtime</h3>
            <p class="text-sm text-gray-400 leading-relaxed">Automatic backups and safe merge logic ensure your site stays up during every update.</p>
        </div>
        <div class="p-8 bg-gray-900/50 rounded-2xl border border-gray-800 shadow-2xl backdrop-blur-sm group hover:border-green-500/30 transition-colors">
            <div class="w-14 h-14 bg-green-500/10 rounded-xl flex items-center justify-center text-green-400 mb-6 mx-auto group-hover:scale-110 transition-transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">Environment Aware</h3>
            <p class="text-sm text-gray-400 leading-relaxed">Built-in support for CI4, Laravel, and React with automated server permission handling.</p>
        </div>
        <div class="p-8 bg-gray-900/50 rounded-2xl border border-gray-800 shadow-2xl backdrop-blur-sm group hover:border-purple-500/30 transition-colors">
            <div class="w-14 h-14 bg-purple-500/10 rounded-xl flex items-center justify-center text-purple-400 mb-6 mx-auto group-hover:scale-110 transition-transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">Dead Simple</h3>
            <p class="text-sm text-gray-400 leading-relaxed">No complex YAML or Docker required. Just PHP, Git, and professional deployment results.</p>
        </div>
    </div>
</div>
<?php $this->endSection() ?>
