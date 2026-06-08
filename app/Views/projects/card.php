<?php
/**
 * @var string $path Project absolute path
 * @var array $project Project data from registry
 */
$projectName = esc(basename($path));
$encodedPath = esc(base64_encode($path));
$status = $project['latest_outcome'] ?? 'none';
$lastShipped = $project['last_shipped_at'] ?? 'Never';
$backups = $project['backups'] ?? [];
?>

<div class="project-card group relative z-10 bg-gray-900/40 border border-gray-800 rounded-2xl p-6 transition-all duration-500 hover:border-blue-500/50 hover:bg-gray-800/40 hover:shadow-[0_20px_50px_-12px_rgba(30,58,138,0.25)] hover:-translate-y-1 overflow-visible">
    <!-- Sophisticated Background Glow (Hidden by default, shows on hover) -->
    <div
        class="absolute -inset-0.5 bg-linear-to-r from-blue-600 to-indigo-600 rounded-2xl blur opacity-0 group-hover:opacity-10 transition duration-500 pointer-events-none">
    </div>

    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-8">
        <!-- Project Info -->
        <div class="grow space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20 group-hover:bg-blue-500/20 transition-all duration-300 shadow-inner">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white tracking-tight leading-none"><?= $projectName ?></h3>

                    <!-- Status Badge -->
                    <div class="mt-2 flex items-center has-tooltip">
                        <?php if ($status === 'success'): ?>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-green-500/10 text-green-400 border border-green-500/20 shadow-[0_0_15px_rgba(34,197,94,0.1)]">
                                <span class="w-1.5 h-1.5 mr-2 rounded-full bg-green-500 animate-pulse"></span>
                                Online
                            </span>
                            <span class="tooltip text-[10px]">Last operation was successful</span>
                        <?php elseif ($status === 'failed'): ?>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/20">
                                <span class="w-1.5 h-1.5 mr-2 rounded-full bg-red-500"></span>
                                Failed
                            </span>
                            <span class="tooltip text-[10px]">Last operation encountered errors</span>
                        <?php else: ?>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-gray-800 text-gray-500 border border-gray-700">
                                <span class="w-1.5 h-1.5 mr-2 rounded-full bg-gray-600"></span>
                                Idle
                            </span>
                            <span class="tooltip text-[10px]">Project initialized but not yet shipped</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm text-gray-400">
                <!-- Location -->
                <div class="flex items-center gap-3 group/meta has-tooltip">
                    <div class="p-1.5 rounded-md bg-gray-800/50 group-hover/meta:bg-blue-500/10 transition-colors">
                        <svg class="w-4 h-4 text-gray-500 group-hover/meta:text-blue-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span
                        class="truncate max-w-55 font-mono text-xs opacity-70 group-hover/meta:opacity-100 transition-opacity"><?= esc($path) ?></span>
                    <span class="tooltip text-[10px]">FileSystem Root: <?= esc($path) ?></span>
                </div>

                <!-- Repository -->
                <div class="flex items-center gap-3 group/meta has-tooltip">
                    <div class="p-1.5 rounded-md bg-gray-800/50 group-hover/meta:bg-blue-500/10 transition-colors">
                        <svg class="w-4 h-4 text-gray-500 group-hover/meta:text-blue-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <span
                        class="truncate max-w-55 font-mono text-xs opacity-70 group-hover/meta:opacity-100 transition-opacity"><?= esc($project['gitRepoUrl'] ?? 'N/A') ?></span>
                    <span class="tooltip text-[10px]">Remote: <?= esc($project['gitRepoUrl'] ?? 'N/A') ?></span>
                </div>

                <!-- Branch -->
                <div class="flex items-center gap-3 group/meta has-tooltip">
                    <div class="p-1.5 rounded-md bg-gray-800/50 group-hover/meta:bg-blue-500/10 transition-colors">
                        <svg class="w-4 h-4 text-gray-500 group-hover/meta:text-blue-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-gray-300"><?= esc($project['branch'] ?? 'main') ?></span>
                    <span class="tooltip text-[10px]">Active Deployment Branch</span>
                </div>

                <!-- Last Shipped -->
                <div class="flex items-center gap-3 group/meta has-tooltip">
                    <div class="p-1.5 rounded-md bg-gray-800/50 group-hover/meta:bg-blue-500/10 transition-colors">
                        <svg class="w-4 h-4 text-gray-500 group-hover/meta:text-blue-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-12 0 9 9 0 0112 0z" />
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-gray-300"><?= esc($lastShipped) ?></span>
                    <span class="tooltip text-[10px]">UTC Timestamp of last ship</span>
                </div>
            </div>

            <!-- Webhook Info -->
            <div class="pt-4 flex items-center gap-x-6 border-t border-gray-800/50">
                <div class="flex items-center gap-3 relative has-tooltip group/webhook">
                    <span
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 group-hover/webhook:text-blue-500 transition-colors">Webhook
                        Trigger</span>
                    <div
                        class="flex items-center gap-2 lowercase font-mono text-[11px] text-blue-400 bg-blue-500/5 px-3 py-1.5 rounded-lg border border-blue-500/10 shadow-inner group/url">
                        <span
                            class="truncate max-w-37.5 sm:max-w-[320px] opacity-60"><?= site_url('api/webhook/' . ($project['webhook_token'] ?? 'none')) ?></span>
                        <button type="button" class="hover:text-white transition-all p-1 active:scale-90"
                            onclick="copyToClipboard('<?= site_url('api/webhook/' . ($project['webhook_token'] ?? 'none')) ?>', event)"
                            title="Copy trigger URL">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                        </button>
                    </div>
                    <span class="tooltip normal-case font-medium">Automatic deployment trigger URL</span>
                </div>
            </div>
        </div>

        <!-- Project Actions -->
        <div
            class="flex flex-row md:flex-col items-center md:items-end gap-4 min-w-[240px] md:self-stretch justify-between md:justify-center">
            <!-- Primary Action -->
            <div class="has-tooltip w-full md:w-auto">
                <button type="button"
                    onclick="deployProject('<?= esc($path, 'js') ?>', event)"
                    class="w-full px-8 py-3.5 text-sm font-black uppercase tracking-widest rounded-xl bg-blue-600 text-white hover:bg-blue-500 transition-all border border-blue-700 shadow-[0_10px_20px_-5px_rgba(37,99,235,0.4)] flex items-center justify-center gap-3 active:scale-95 group/ship">
                    <svg class="w-5 h-5 group-hover:animate-bounce" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Ship It
                </button>
                <span class="tooltip text-[10px]">Full Deployment Lifecycle</span>
            </div>

            <!-- Secondary Actions Dropdown -->
            <div class="relative" id="project-actions-<?= md5($path) ?>-dropdown">
                <button type="button"
                    class="p-3 bg-gray-900 border border-gray-800 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 hover:border-gray-700 transition-all duration-300 shadow-sm flex items-center justify-center"
                    onclick="toggleDropdown('project-actions-<?= md5($path) ?>-menu', event)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                </button>

                <div id="project-actions-<?= md5($path) ?>-menu"
                    class="hidden absolute right-0 mt-3 w-64 origin-top-right bg-gray-950 border border-gray-800 rounded-2xl shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] z-60 py-2 backdrop-blur-2xl">
                    <!-- Validate -->
                    <button type="button"
                        class="w-full text-left px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-gray-400 hover:bg-blue-600 hover:text-white transition-all flex items-center gap-3"
                        onclick="validateProject('<?= $encodedPath ?>', event)">
                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Validate Configuration
                    </button>

                    <!-- .env Editor -->
                    <button type="button"
                        class="w-full text-left px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-gray-400 hover:bg-blue-600 hover:text-white transition-all flex items-center gap-3"
                        onclick="openEnvEditor('<?= $encodedPath ?>', event)">
                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Environment (.env)
                    </button>

                    <!-- Config Editor -->
                    <button type="button"
                        class="w-full text-left px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-gray-400 hover:bg-blue-600 hover:text-white transition-all flex items-center gap-3"
                        onclick="openConfigEditor('<?= $encodedPath ?>', event)">
                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Project Settings
                    </button>

                    <!-- History -->
                    <button type="button"
                        class="w-full text-left px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-gray-400 hover:bg-blue-600 hover:text-white transition-all flex items-center gap-3"
                        onclick="showHistoryModal('<?= esc(base64_encode(json_encode(['name' => $projectName, 'path' => $path, 'history' => $project['history'] ?? []])), 'js') ?>', event)">
                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-12 0 9 9 0 0112 0z" />
                        </svg>
                        Activity Log
                    </button>

                    <!-- Webhook Token -->
                    <button type="button"
                        class="w-full text-left px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-red-400 hover:bg-red-600 hover:text-white transition-all flex items-center gap-3 border-t border-gray-800/50 mt-1"
                        onclick="regenerateWebhookToken('<?= $encodedPath ?>', event)">
                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Reset Webhook
                    </button>

                    <!-- Rollback -->
                    <div class="px-4 py-3 bg-black/40 mt-1 rounded-b-2xl border-t border-gray-800/50">
                        <label
                            class="block text-[9px] font-black uppercase tracking-widest text-gray-600 mb-2">Available
                            Snapshots</label>
                        <?php if (!empty($backups)): ?>
                            <div class="flex gap-1 items-center">
                                <select id="backup-select-<?= md5($path) ?>"
                                    class="grow px-2 py-1.5 text-[10px] font-mono rounded-lg border border-gray-800 bg-gray-950 text-gray-400 focus:ring-1 focus:ring-blue-500 outline-none transition-all appearance-none cursor-pointer">
                                    <?php foreach ($backups as $backup): ?>
                                        <option value="<?= esc($backup) ?>"><?= esc($backup) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button"
                                    class="px-3 py-1.5 text-[10px] font-black uppercase rounded-lg bg-gray-800 text-white hover:bg-red-600 transition-all border border-gray-700 active:scale-95"
                                    onclick="rollbackProject('<?= esc($path, 'js') ?>', 'backup-select-<?= md5($path) ?>', event)">
                                    Go
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-[10px] text-gray-700 italic">No snapshots available</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>