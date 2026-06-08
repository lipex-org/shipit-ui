<?php
/** @var $this \CodeIgniter\View\View */
?>
<?php $this->extend('layouts/app.layout.php') ?>

<?php $this->section('header') ?>
<title>Dashboard - ShipIt Control Panel</title>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="min-h-screen bg-gray-950 pb-20">
    <!-- Header -->
    <header class="bg-gray-900/50 backdrop-blur-md border-b border-gray-800 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 max-w-5xl flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-linear-to-br from-blue-600 to-blue-800 flex items-center justify-center shadow-lg shadow-blue-900/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-black text-white tracking-tight leading-none uppercase">ShipIt</h1>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">Control Panel</p>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div class="hidden md:flex items-center gap-3 text-sm border-r border-gray-800 pr-6">
                    <div class="text-right">
                        <p class="text-gray-400 text-[10px] uppercase font-bold tracking-wider">Operator</p>
                        <p class="text-white font-semibold leading-none"><?= esc($username) ?></p>
                    </div>
                    <div
                        class="w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-xs font-bold text-gray-400">
                        <?= strtoupper(substr($username, 0, 2)) ?>
                    </div>
                </div>

                <a href="<?= site_url('logout') ?>" data-turbo="false"
                    class="p-2 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all duration-300"
                    title="Logout session">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-12 max-w-5xl">
        <!-- Sub-header with Search & Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">ShipIt Dashboard</h2>
                <p class="text-gray-500 mt-1">Manage and orchestrate your deployments across the server.</p>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <form action="<?= site_url('dashboard') ?>" method="GET" class="relative group grow md:flex-none">
                    <input type="text" name="search" placeholder="Search projects..."
                        value="<?= esc(service('request')->getGet('search')) ?>"
                        class="w-full sm:w-64 pl-10 pr-4 py-2.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-gray-300 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition-all group-hover:border-gray-700">
                    <svg class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </form>

                <!-- Actions Dropdown -->
                <div class="relative inline-block text-left" id="actions-dropdown">
                    <button type="button"
                        class="p-2.5 bg-gray-900 border border-gray-800 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition-all duration-300 shadow-sm"
                        onclick="toggleDropdown('actions-menu', event)">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </button>

                    <div id="actions-menu"
                        class="hidden absolute right-0 mt-2 w-56 origin-top-right bg-gray-900 border border-gray-800 rounded-xl shadow-2xl z-50 py-1 backdrop-blur-xl">
                        <button type="button"
                            class="w-full text-left px-4 py-3 text-sm text-gray-300 hover:bg-blue-600 hover:text-white transition-colors flex items-center gap-3"
                            onclick="showAddProjectModal(event)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Add New Project
                        </button>
                        <div class="border-t border-gray-800 my-1"></div>
                        <button type="button"
                            class="w-full text-left px-4 py-3 text-sm text-red-400 hover:bg-red-600 hover:text-white transition-colors flex items-center gap-3"
                            onclick="pruneRegistry(event)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142a2 2 0 01-16.138 21h7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h14">
                                </path>
                            </svg>
                            Prune Dead Projects
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <turbo-frame id="projects-list">
            <?php if (empty($projects)): ?>
                <div class="bg-gray-900/40 border border-gray-800 rounded-2xl shadow-2xl backdrop-blur-sm py-24 text-center">
                    <div
                        class="w-16 h-16 bg-gray-800 rounded-2xl flex items-center justify-center mx-auto mb-6 text-gray-600 border border-gray-700/50">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-300">No Projects Registered</h3>
                    <p class="text-gray-500 mt-2 max-w-sm mx-auto px-6">We couldn't find any deployments in the
                        registry. Initialize a project using the CLI to see it here.</p>
                    <div class="mt-8">
                        <code
                            class="bg-black/60 px-4 py-2 rounded-lg text-sm text-blue-400 font-mono border border-gray-800">shipit init</code>
                    </div>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($projects as $path => $project): ?>
                        <?= view('projects/card', ['path' => $path, 'project' => $project]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </turbo-frame>
    </main>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Confirmation Modal -->
<div id="confirm-modal"
    class="hidden fixed inset-0 z-[60] bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-sm flex flex-col border border-gray-800 shadow-2xl">
        <div class="p-6 text-center space-y-4">
            <div id="confirm-icon"
                class="w-16 h-16 bg-blue-500/10 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 id="confirm-title" class="text-xl font-bold text-white tracking-tight">Are you sure?</h3>
            <p id="confirm-message" class="text-sm text-gray-400 leading-relaxed px-4">This action cannot be undone.</p>
        </div>
        <div class="p-6 bg-black/20 rounded-b-2xl flex gap-3">
            <button type="button" id="confirm-cancel"
                class="flex-1 px-4 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-sm font-bold transition-all">Cancel</button>
            <button type="button" id="confirm-ok"
                class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-bold transition-all shadow-lg shadow-blue-500/20">Confirm</button>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div id="add-project-modal"
    class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-xl flex flex-col border border-gray-800 shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <h2 class="text-sm font-black uppercase tracking-widest text-gray-100">Register New Project</h2>
            </div>
            <button type="button" class="text-gray-500 hover:text-white p-1 hover:bg-gray-800 rounded-lg transition-all"
                onclick="closeAddProjectModal(event)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tab Toggle -->
        <div class="flex border-b border-gray-800">
            <button type="button" id="tab-manual" onclick="switchAddProjectTab('manual')"
                class="flex-1 py-3 text-xs font-black uppercase tracking-widest border-b-2 border-blue-600 text-white transition-all">
                Manual Entry
            </button>
            <button type="button" id="tab-github" onclick="switchAddProjectTab('github')"
                class="flex-1 py-3 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500 hover:text-gray-300 transition-all">
                Import from GitHub
            </button>
        </div>

        <form id="add-project-form" class="p-6 space-y-5" onsubmit="submitAddProject(event)">
            <!-- GitHub Import Section (Hidden by default) -->
            <div id="github-section" class="hidden space-y-5 animate-in fade-in duration-300">
                <div id="github-loading-view" class="py-12 text-center space-y-4">
                    <div
                        class="w-10 h-10 border-4 border-blue-600/20 border-t-blue-600 rounded-full animate-spin mx-auto">
                    </div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Checking GitHub Connection...
                    </p>
                </div>

                <div id="github-connect-view" class="hidden py-8 text-center space-y-4">
                    <div
                        class="w-16 h-16 bg-gray-800 rounded-full flex items-center justify-center mx-auto text-gray-400">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.042-1.416-4.042-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="font-bold text-white">Connect your GitHub Account</h3>
                        <p class="text-xs text-gray-500">Securely browse and import your repositories.</p>
                    </div>
                    <a href="<?= site_url('integrations/github/connect') ?>"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-gray-900 rounded-xl text-sm font-black uppercase tracking-wider hover:bg-gray-200 transition-all">
                        Connect GitHub
                    </a>
                </div>

                <div id="github-list-view" class="hidden space-y-4">
                    <div class="relative">
                        <input type="text" id="github-repo-search" placeholder="Search your repositories..."
                            class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        <svg class="w-4 h-4 text-gray-600 absolute left-3 top-1/2 -translate-y-1/2" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <div id="github-repos-container" class="max-h-64 overflow-y-auto custom-scrollbar space-y-2 pr-2">
                        <!-- repos will be injected here -->
                    </div>

                    <!-- Auto-setup Webhook Checkbox -->
                    <div class="flex items-center gap-3 p-3 bg-blue-500/5 border border-blue-500/10 rounded-xl">
                        <input type="checkbox" id="github-auto-webhook" checked
                               class="w-4 h-4 rounded border-gray-800 bg-gray-900 text-blue-600 focus:ring-blue-600 focus:ring-offset-gray-950 transition-all cursor-pointer">
                        <label for="github-auto-webhook" class="text-xs font-bold text-gray-400 cursor-pointer select-none">Automatically setup GitHub Webhook for CI/CD</label>
                    </div>
                    </div>
            </div>

            <div id="manual-section" class="space-y-5 animate-in fade-in duration-300">
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500 ml-1">Project
                        Filesystem Path</label>
                    <input type="text" name="project_path" id="add-project-path" required
                        placeholder="/home/user/public_html/my-project"
                        class="w-full px-4 py-3 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                    <p class="text-[10px] text-gray-600 italic">The absolute path to the directory where you want to
                        deploy.</p>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500 ml-1">Git Repository
                        URL (SSH/HTTPS)</label>
                    <input type="text" name="git_url" id="add-git-url" required
                        placeholder="git@github.com:username/repo.git"
                        class="w-full px-4 py-3 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500 ml-1">Default
                        Branch</label>
                    <input type="text" name="branch" id="add-branch" value="main" required
                        class="w-full px-4 py-3 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button"
                    class="flex-1 px-6 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm font-bold text-gray-400 hover:bg-gray-700 hover:text-white transition-all duration-300"
                    onclick="closeAddProjectModal(event)">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-2 px-6 py-3 bg-blue-600 border border-blue-700 rounded-xl text-sm font-black uppercase tracking-widest text-white hover:bg-blue-500 transition-all duration-300 shadow-lg shadow-blue-900/20">
                    Initialize Project
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Environment Editor Modal -->
<div id="env-editor-modal"
    class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-2xl flex flex-col border border-gray-800 shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <h2 id="env-modal-title" class="text-sm font-black uppercase tracking-widest text-gray-100">Edit
                    Environment</h2>
            </div>
            <button type="button" class="text-gray-500 hover:text-white p-1 hover:bg-gray-800 rounded-lg transition-all"
                onclick="closeEnvEditor(event)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="env-editor-form" class="p-6 space-y-4" onsubmit="submitEnv(event)">
            <input type="hidden" name="project_path" id="env-project-path">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-500 ml-1">Environment File
                    Content (.env)</label>
                <textarea name="content" id="env-content" rows="12"
                    class="w-full px-4 py-3 bg-black/40 border border-gray-800 rounded-xl text-sm text-blue-400 font-mono focus:ring-2 focus:ring-blue-600 outline-none transition-all resize-none custom-scrollbar"
                    placeholder="KEY=VALUE"></textarea>
                <p class="text-[10px] text-gray-600 italic">Be careful: changes take effect immediately upon saving.</p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="button"
                    class="flex-1 px-6 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm font-bold text-gray-400 hover:bg-gray-700 hover:text-white transition-all duration-300"
                    onclick="closeEnvEditor(event)">
                    Cancel
                </button>
                <button type="submit" id="env-save-btn"
                    class="flex-2 px-6 py-3 bg-blue-600 border border-blue-700 rounded-xl text-sm font-black uppercase tracking-widest text-white hover:bg-blue-500 transition-all duration-300 shadow-lg shadow-blue-900/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Project Settings Modal -->
<div id="config-editor-modal"
    class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div
        class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-3xl flex flex-col max-h-[90vh] border border-gray-800 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <h2 id="config-modal-title" class="text-sm font-black uppercase tracking-widest text-gray-100">Project
                    Settings</h2>
            </div>
            <button type="button" class="text-gray-500 hover:text-white p-1 hover:bg-gray-800 rounded-lg transition-all"
                onclick="closeConfigEditor(event)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="config-editor-form" class="flex flex-col flex-grow overflow-hidden" onsubmit="submitConfig(event)">
            <input type="hidden" name="project_path" id="config-project-path">

            <div class="p-6 overflow-y-auto flex-grow custom-scrollbar space-y-8">
                <!-- Section: Source Control -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-800/50">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-500">Source
                            Control</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Git Repository URL</label>
                            <input type="text" name="gitRepoUrl" id="config-git-url" required
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Deployment Branch</label>
                            <input type="text" name="branch" id="config-branch" required
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        </div>
                    </div>
                </div>

                <!-- Section: Orchestration -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-800/50">
                        <span
                            class="text-[10px] font-black uppercase tracking-widest text-blue-500">Orchestration</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Framework Adapter</label>
                            <select name="adapter" id="config-adapter"
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all appearance-none cursor-pointer">
                                <option value="">Standard (None)</option>
                                <option value="laravel">Laravel</option>
                                <option value="ci4">CodeIgniter 4</option>
                                <option value="vite">Vite / React / Vue</option>
                                <option value="custom">Custom Adapter</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Server Profile</label>
                            <select name="server" id="config-server"
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all appearance-none cursor-pointer">
                                <option value="">Standard (None)</option>
                                <option value="directadmin">DirectAdmin</option>
                                <option value="cpanel">cPanel</option>
                                <option value="custom">Custom Profile</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section: System & Backups -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-800/50">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-500">System &
                            Environment</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Run as User</label>
                            <input type="text" name="user" id="config-user" required
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Run as Group</label>
                            <input type="text" name="group" id="config-group" required
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Backup Retention</label>
                            <input type="number" name="backup_retention" id="config-retention" min="1" max="50" required
                                class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase text-gray-500 ml-1">Backup Path</label>
                        <input type="text" name="backup_path" id="config-backup-path" required
                            class="w-full px-4 py-2.5 bg-black/40 border border-gray-800 rounded-xl text-sm text-gray-100 focus:ring-2 focus:ring-blue-600 outline-none transition-all">
                        <p class="text-[10px] text-gray-600 italic">Absolute path to store deployment snapshots.</p>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-black/20 flex gap-3 border-t border-gray-800">
                <button type="button"
                    class="flex-1 px-6 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm font-bold text-gray-400 hover:bg-gray-700 hover:text-white transition-all duration-300"
                    onclick="closeConfigEditor(event)">
                    Cancel
                </button>
                <button type="submit" id="config-save-btn"
                    class="flex-[2] px-6 py-3 bg-blue-600 border border-blue-700 rounded-xl text-sm font-black uppercase tracking-widest text-white hover:bg-blue-500 transition-all duration-300 shadow-lg shadow-blue-900/20">
                    Save Project Settings
                </button>
            </div>
        </form>
    </div>
</div>

<!-- History Modal -->
<div id="history-modal"
    class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div
        class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-2xl flex flex-col max-h-[85vh] border border-gray-800 shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-12 0 9 9 0 0112 0z" />
                </svg>
                <h2 id="history-modal-title"
                    class="text-sm font-black uppercase tracking-widest text-gray-100 uppercase">Deployment History</h2>
            </div>
            <button type="button" class="text-gray-500 hover:text-white p-1 hover:bg-gray-800 rounded-lg transition-all"
                onclick="closeHistoryModal(event)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto flex-grow custom-scrollbar">
            <div class="rounded-xl border border-gray-800 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-black/40 text-[10px] uppercase font-black tracking-widest text-gray-500 border-b border-gray-800">
                        <tr>
                            <th class="px-4 py-3">Timestamp</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Log</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body" class="divide-y divide-gray-800/50">
                        <!-- Rows will be dynamic -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Log Viewer Modal -->
<div id="log-modal"
    class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 scale-95 overflow-hidden">
    <div
        class="bg-gray-900 text-gray-100 rounded-2xl w-full max-w-4xl flex flex-col max-h-[85vh] border border-gray-800 shadow-[0_0_50px_-12px_rgba(37,99,235,0.25)]">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>
                <h2 id="modal-title" class="text-sm font-black uppercase tracking-widest text-gray-100">Orchestration
                    Logs</h2>
            </div>
            <button type="button" class="text-gray-500 hover:text-white p-1 hover:bg-gray-800 rounded-lg transition-all"
                onclick="closeModal(event)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 overflow-hidden grow flex flex-col bg-black/60">
            <div class="grow overflow-y-auto rounded-xl bg-black/40 border border-gray-800 p-4 font-mono text-xs leading-relaxed custom-scrollbar shadow-inner"
                id="log-container">
                <pre id="log-output"
                    class="whitespace-pre-wrap break-all text-blue-400/90 drop-shadow-sm">Initializing log connection...</pre>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <p class="text-[10px] text-gray-600 font-bold uppercase tracking-tighter">System Output (Real-time)</p>
                <div class="flex gap-2">
                    <span id="log-status"
                        class="px-2 py-0.5 text-[10px] font-black uppercase rounded bg-blue-500/10 text-blue-500 border border-blue-500/20 tracking-widest">Active</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection() ?>