/**
 * Dashboard Specific Logic - Polling based logs with Turbo Frame support
 */

interface DeploymentResponse {
    status: string;
    log_id?: string;
    message?: string;
}

interface LogResponse {
    status: string;
    lines: string[];
    offset: number;
    finished: boolean;
    message?: string;
}

interface ValidationResult {
    rule: string;
    status: string;
    message: string;
    suggestion: string;
}

interface ValidationResponse {
    status: string;
    results: ValidationResult[];
    message?: string;
}

class DashboardUI {
    private static instance: DashboardUI | null = null;
    private pollingInterval: any = null;
    private logOutput: HTMLElement | null = null;
    private logStatus: HTMLElement | null = null;
    private modal: HTMLElement | null = null;
    private addProjectModal: HTMLElement | null = null;
    private envEditorModal: HTMLElement | null = null;
    private configEditorModal: HTMLElement | null = null;
    private historyModal: HTMLElement | null = null;
    private confirmModal: HTMLElement | null = null;
    private toastContainer: HTMLElement | null = null;
    private modalTitle: HTMLElement | null = null;
    private currentOffset: number = 0;
    private githubRepos: any[] = [];
    private shouldSetupWebhook: boolean = false;
    private lastProjectPath: string = '';

    constructor() {
        if (DashboardUI.instance) {
            DashboardUI.instance.init();
            return;
        }
        
        DashboardUI.instance = this;

        // Use a single event listener for turbo:load
        document.addEventListener('turbo:load', () => this.init());
        
        // Initial call for first load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    private init() {
        const isTurboEnabled = (window as any).Turbo !== undefined || document.documentElement.hasAttribute('data-turbo-loaded') || (window as any).TurboReady;
        console.log('DashboardUI Init - Turbo status:', isTurboEnabled ? 'Detected' : 'Not Found');
        this.initElements();
        this.attachGlobalFunctions();
    }

    private initElements() {
        this.logOutput = document.getElementById('log-output');
        this.logStatus = document.getElementById('log-status');
        this.modal = document.getElementById('log-modal');
        this.addProjectModal = document.getElementById('add-project-modal');
        this.envEditorModal = document.getElementById('env-editor-modal');
        this.configEditorModal = document.getElementById('config-editor-modal');
        this.historyModal = document.getElementById('history-modal');
        this.confirmModal = document.getElementById('confirm-modal');
        this.toastContainer = document.getElementById('toast-container');
        this.modalTitle = document.getElementById('modal-title');

        const repoSearch = document.getElementById('github-repo-search') as HTMLInputElement;
        if (repoSearch) {
            repoSearch.addEventListener('input', (e) => {
                this.filterGitHubRepos((e.target as HTMLInputElement).value);
            });
        }
    }

    private attachGlobalFunctions() {
        // We use window assignments because buttons have inline onclick handlers
        (window as any).deployProject = (path: string, event?: Event) => {
            console.log('Global deployProject called for path:', path);
            if (event) event.preventDefault();
            this.deployProject(path);
            return false;
        };
        (window as any).rollbackProject = (path: string, selectId: string, event?: Event) => {
            if (event) event.preventDefault();
            this.rollbackProject(path, selectId);
            return false;
        };
        (window as any).validateProject = (encodedPath: string, event?: Event) => {
            if (event) event.preventDefault();
            this.validateProject(encodedPath);
            return false;
        };
        (window as any).pruneRegistry = (event?: Event) => {
            if (event) event.preventDefault();
            this.pruneRegistry();
            return false;
        };
        (window as any).closeModal = (event?: Event) => {
            if (event) event.preventDefault();
            this.closeModal();
            return false;
        };
        (window as any).showAddProjectModal = (event?: Event) => {
            if (event) event.preventDefault();
            this.showAddProjectModal();
            return false;
        };
        (window as any).closeAddProjectModal = (event?: Event) => {
            if (event) event.preventDefault();
            this.closeAddProjectModal();
            return false;
        };
        (window as any).submitAddProject = (event: Event) => {
            this.submitAddProject(event);
            return false;
        };
        (window as any).openEnvEditor = (encodedPath: string, event?: Event) => {
            if (event) event.preventDefault();
            this.openEnvEditor(encodedPath);
            return false;
        };
        (window as any).closeEnvEditor = (event?: Event) => {
            if (event) event.preventDefault();
            this.closeEnvEditor();
            return false;
        };
        (window as any).openConfigEditor = (encodedPath: string, event?: Event) => {
            if (event) event.preventDefault();
            this.openConfigEditor(encodedPath);
            return false;
        };
        (window as any).closeConfigEditor = (event?: Event) => {
            if (event) event.preventDefault();
            this.closeConfigEditor();
            return false;
        };
        (window as any).submitConfig = (event: Event) => {
            this.submitConfig(event);
            return false;
        };
        (window as any).submitEnv = (event: Event) => {
            this.submitEnv(event);
            return false;
        };
        (window as any).switchAddProjectTab = (tab: 'manual' | 'github') => {
            this.switchAddProjectTab(tab);
        };
        (window as any).selectGitHubRepo = (url: string, branch: string) => {
            this.selectGitHubRepo(url, branch);
        };
        (window as any).showHistoryModal = (encodedData: string, event?: Event) => {
            if (event) event.preventDefault();
            this.showHistoryModal(encodedData);
            return false;
        };
        (window as any).closeHistoryModal = (event?: Event) => {
            if (event) event.preventDefault();
            this.closeHistoryModal();
            return false;
        };
        (window as any).viewStaticLog = (logId: string, event?: Event) => {
            if (event) event.preventDefault();
            this.viewStaticLog(logId);
            return false;
        };
        (window as any).toggleDropdown = (menuId: string, event?: Event) => {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.toggleDropdown(menuId);
            return false;
        };
        (window as any).copyToClipboard = (text: string, event?: Event) => {
            this.copyToClipboard(text, event);
            return false;
        };
        (window as any).regenerateWebhookToken = (encodedPath: string, event?: Event) => {
            this.regenerateWebhookToken(encodedPath, event);
            return false;
        };

        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            if (!target.closest('[id$="-dropdown"]') && !target.closest('[id$="-menu"]')) {
                document.querySelectorAll('[id$="-menu"]').forEach(menu => {
                    menu.classList.add('hidden');
                    const card = menu.closest('.project-card');
                    if (card) card.classList.remove('z-50');
                });
            }
        });
    }

    public toggleDropdown(menuId: string) {
        const menu = document.getElementById(menuId);
        if (menu) {
            const isHidden = menu.classList.contains('hidden');
            
            // Close all other menus and reset their parent card z-indexes
            document.querySelectorAll('[id$="-menu"]').forEach(m => {
                m.classList.add('hidden');
                const card = m.closest('.project-card');
                if (card) card.classList.remove('z-50');
            });
            
            if (isHidden) {
                menu.classList.remove('hidden');
                // Elevate parent card z-index
                const card = menu.closest('.project-card');
                if (card) card.classList.add('z-50');
            }
        }
    }

    private getCsrfHash(): string {
        const name = 'csrf_cookie_name=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return (window as any).csrfHash || '';
    }

    public showModal(title: string) {
        if (this.modalTitle) this.modalTitle.textContent = title;
        if (this.logOutput) this.logOutput.textContent = 'Initializing connection...\n';
        if (this.logStatus) {
            this.logStatus.textContent = 'Active';
            this.logStatus.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-blue-500/10 text-blue-500 border border-blue-500/20 tracking-widest';
        }
        this.currentOffset = 0;
        
        if (this.modal) {
            this.modal.classList.remove('hidden');
            this.modal.classList.add('flex');
            
            // Trigger reflow for transition
            this.modal.offsetHeight;
            
            this.modal.classList.remove('opacity-0', 'scale-95');
            this.modal.classList.add('opacity-100', 'scale-100');
            
            document.body.classList.add('overflow-hidden');
        }
    }

    public closeModal() {
        if (this.modal) {
            this.modal.classList.remove('opacity-100', 'scale-100');
            this.modal.classList.add('opacity-0', 'scale-95');
            
            setTimeout(() => {
                this.modal!.classList.add('hidden');
                this.modal!.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        }
        this.stopPolling();
    }

    public showAddProjectModal() {
        if (this.addProjectModal) {
            this.switchAddProjectTab('manual'); // Reset to manual
            this.addProjectModal.classList.remove('hidden');
            this.addProjectModal.classList.add('flex');
            this.addProjectModal.offsetHeight;
            this.addProjectModal.classList.remove('opacity-0', 'scale-95');
            this.addProjectModal.classList.add('opacity-100', 'scale-100');
            document.body.classList.add('overflow-hidden');
        }
    }

    public closeAddProjectModal() {
        if (this.addProjectModal) {
            this.addProjectModal.classList.remove('opacity-100', 'scale-100');
            this.addProjectModal.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                this.addProjectModal!.classList.add('hidden');
                this.addProjectModal!.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        }
    }

    public switchAddProjectTab(tab: 'manual' | 'github') {
        const manualSec = document.getElementById('manual-section');
        const githubSec = document.getElementById('github-section');
        const manualTab = document.getElementById('tab-manual');
        const githubTab = document.getElementById('tab-github');

        if (tab === 'manual') {
            manualSec?.classList.remove('hidden');
            githubSec?.classList.add('hidden');
            manualTab?.classList.add('border-blue-600', 'text-white');
            manualTab?.classList.remove('border-transparent', 'text-gray-500');
            githubTab?.classList.remove('border-blue-600', 'text-white');
            githubTab?.classList.add('border-transparent', 'text-gray-500');
        } else {
            manualSec?.classList.add('hidden');
            githubSec?.classList.remove('hidden');
            githubTab?.classList.add('border-blue-600', 'text-white');
            githubTab?.classList.remove('border-transparent', 'text-gray-500');
            manualTab?.classList.remove('border-blue-600', 'text-white');
            manualTab?.classList.add('border-transparent', 'text-gray-500');

            if (this.githubRepos.length === 0) {
                this.fetchGitHubRepos();
            }
        }
    }

    private async fetchGitHubRepos() {
        const loadingView = document.getElementById('github-loading-view');
        const connectView = document.getElementById('github-connect-view');
        const listView = document.getElementById('github-list-view');

        loadingView?.classList.remove('hidden');
        connectView?.classList.add('hidden');
        listView?.classList.add('hidden');

        const siteUrl = (window as any).siteUrl || '';

        try {
            const response = await fetch(`${siteUrl}/api/github/repos`);
            const data = await response.json();

            loadingView?.classList.add('hidden');

            if (response.ok && data.connected) {
                this.githubRepos = data.repos;
                connectView?.classList.add('hidden');
                listView?.classList.remove('hidden');
                this.renderGitHubRepos(this.githubRepos);
            } else if (response.status === 401) {
                connectView?.classList.remove('hidden');
                listView?.classList.add('hidden');
            } else {
                this.showToast(data.message || 'Failed to fetch repositories', 'error');
            }
        } catch (err) {
            loadingView?.classList.add('hidden');
            // Probably not connected or error
            connectView?.classList.remove('hidden');
            listView?.classList.add('hidden');
        }
    }

    private renderGitHubRepos(repos: any[]) {
        const container = document.getElementById('github-repos-container');
        if (!container) return;

        container.innerHTML = '';
        if (repos.length === 0) {
            container.innerHTML = '<div class="py-4 text-center text-gray-600 italic text-sm">No matching repositories found.</div>';
            return;
        }

        repos.forEach(repo => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'w-full text-left p-3 rounded-xl bg-black/20 border border-gray-800/50 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all group flex items-center justify-between';
            item.onclick = () => this.selectGitHubRepo(repo.url, repo.branch);

            item.innerHTML = `
                <div class="flex items-center gap-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.372.79 1.103.79 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                    <div class="pointer-events-none">
                        <p class="text-sm font-bold text-gray-200 group-hover:text-white">${repo.name}</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-black">${repo.branch}</p>
                    </div>
                </div>
                ${repo.private ? '<svg class="w-3 h-3 text-gray-600 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>' : ''}
            `;
            container.appendChild(item);
        });
    }

    private filterGitHubRepos(query: string) {
        const filtered = this.githubRepos.filter(r => r.name.toLowerCase().includes(query.toLowerCase()));
        this.renderGitHubRepos(filtered);
    }

    public selectGitHubRepo(url: string, branch: string) {
        const urlInput = document.getElementById('add-git-url') as HTMLInputElement;
        const branchInput = document.getElementById('add-branch') as HTMLInputElement;

        if (urlInput) urlInput.value = url;
        if (branchInput) branchInput.value = branch;

        this.switchAddProjectTab('manual');
        this.showToast('Repository details imported!', 'success');
    }

    public async submitAddProject(event: Event) {
        event.preventDefault();
        const form = event.target as HTMLFormElement;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const githubTab = document.getElementById('tab-github');
        const autoWebhookCheck = document.getElementById('github-auto-webhook') as HTMLInputElement;
        
        // Check if we're in GitHub tab and auto-setup is checked
        this.shouldSetupWebhook = !!(githubTab?.classList.contains('border-blue-600') && autoWebhookCheck?.checked);
        this.lastProjectPath = data.project_path as string;

        this.closeAddProjectModal();
        this.showModal('Initializing ' + data.project_path);

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/init`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify(data)
            });

            const result: DeploymentResponse = await response.json();

            if (response.ok && result.status === 'started' && result.log_id) {
                this.startPolling(result.log_id);
                form.reset();
            } else {
                if (this.logOutput) {
                    this.logOutput.textContent = 'Error: ' + (result.message || 'Failed to initialize project.');
                }
            }
        } catch (err: any) {
            if (this.logOutput) {
                this.logOutput.textContent = 'Failed: ' + (err.message || 'Unknown error');
            }
        }
    }

    public async openEnvEditor(encodedPath: string) {
        const projectPath = atob(encodedPath);
        const titleEl = document.getElementById('env-modal-title');
        if (titleEl) titleEl.textContent = 'Edit .env - ' + projectPath.split('/').pop();

        const inputPath = document.getElementById('env-project-path') as HTMLInputElement;
        const textarea = document.getElementById('env-content') as HTMLTextAreaElement;
        
        if (inputPath) inputPath.value = projectPath;
        if (textarea) {
            textarea.value = 'Loading environment file...';
            textarea.disabled = true;
        }

        this.showEnvEditorModal();

        const siteUrl = (window as any).siteUrl || '';
        try {
            const response = await fetch(`${siteUrl}/projects/env?path=${encodeURIComponent(encodedPath)}`);
            const data = await response.json();
            
            if (response.ok && data.status === 'success') {
                if (textarea) {
                    textarea.value = data.content;
                    textarea.disabled = false;
                }
            } else {
                this.showToast(data.message || 'Failed to load .env file.', 'error');
                this.closeEnvEditor();
            }
        } catch (err: any) {
            this.showToast('Error: ' + (err.message || 'Unknown error'), 'error');
            this.closeEnvEditor();
        }
    }

    private showEnvEditorModal() {
        if (this.envEditorModal) {
            this.envEditorModal.classList.remove('hidden');
            this.envEditorModal.classList.add('flex');
            this.envEditorModal.offsetHeight;
            this.envEditorModal.classList.remove('opacity-0', 'scale-95');
            this.envEditorModal.classList.add('opacity-100', 'scale-100');
            document.body.classList.add('overflow-hidden');
        }
    }

    public closeEnvEditor() {
        if (this.envEditorModal) {
            this.envEditorModal.classList.remove('opacity-100', 'scale-100');
            this.envEditorModal.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                this.envEditorModal!.classList.add('hidden');
                this.envEditorModal!.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        }
    }

    public async openConfigEditor(encodedPath: string) {
        const projectPath = atob(encodedPath);
        const titleEl = document.getElementById('config-modal-title');
        if (titleEl) titleEl.textContent = 'Settings: ' + projectPath.split('/').pop();

        const inputPath = document.getElementById('config-project-path') as HTMLInputElement;
        if (inputPath) inputPath.value = projectPath;

        this.showConfigEditorModal();

        const siteUrl = (window as any).siteUrl || '';
        try {
            const response = await fetch(`${siteUrl}/projects/config?path=${encodeURIComponent(encodedPath)}`);
            const data = await response.json();
            
            if (response.ok && data.status === 'success') {
                const config = data.config;
                
                // Map config values to form fields
                (document.getElementById('config-git-url') as HTMLInputElement).value = config.gitRepoUrl || '';
                (document.getElementById('config-branch') as HTMLInputElement).value = config.branch || 'main';
                (document.getElementById('config-adapter') as HTMLSelectElement).value = config.adapter || '';
                (document.getElementById('config-server') as HTMLSelectElement).value = config.server || '';
                (document.getElementById('config-user') as HTMLInputElement).value = config.user || 'admin';
                (document.getElementById('config-group') as HTMLInputElement).value = config.group || 'admin';
                (document.getElementById('config-retention') as HTMLInputElement).value = config.backup_retention || 5;
                (document.getElementById('config-backup-path') as HTMLInputElement).value = config.backup_path || '';
            } else {
                this.showToast(data.message || 'Failed to load project configuration.', 'error');
                this.closeConfigEditor();
            }
        } catch (err: any) {
            this.showToast('Error: ' + (err.message || 'Unknown error'), 'error');
            this.closeConfigEditor();
        }
    }

    private showConfigEditorModal() {
        if (this.configEditorModal) {
            this.configEditorModal.classList.remove('hidden');
            this.configEditorModal.classList.add('flex');
            this.configEditorModal.offsetHeight;
            this.configEditorModal.classList.remove('opacity-0', 'scale-95');
            this.configEditorModal.classList.add('opacity-100', 'scale-100');
            document.body.classList.add('overflow-hidden');
        }
    }

    public closeConfigEditor() {
        if (this.configEditorModal) {
            this.configEditorModal.classList.remove('opacity-100', 'scale-100');
            this.configEditorModal.classList.add('opacity-0', 'scale-95');
            
            setTimeout(() => {
                this.configEditorModal!.classList.add('hidden');
                this.configEditorModal!.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        }
    }

    public async submitConfig(event: Event) {
        event.preventDefault();
        const form = event.target as HTMLFormElement;
        const saveBtn = document.getElementById('config-save-btn') as HTMLButtonElement;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/config`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                this.closeConfigEditor();
                this.showToast('Configuration updated successfully.', 'success');
                this.refreshProjectList();
            } else {
                this.showToast(result.message || 'Failed to update configuration.', 'error');
            }
        } catch (err: any) {
            this.showToast('Failed: ' + (err.message || 'Unknown error'), 'error');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Project Settings';
            }
        }
    }

    public showHistoryModal(encodedData: string) {
        const data = JSON.parse(atob(encodedData));
        const titleEl = document.getElementById('history-modal-title');
        if (titleEl) titleEl.textContent = 'Activity: ' + data.name;

        const tableBody = document.getElementById('history-table-body');
        if (tableBody) {
            tableBody.innerHTML = '';

            if (!data.history || data.history.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500 italic font-medium">No activity recorded yet.</td></tr>';
            } else {
                data.history.forEach((entry: any) => {
                    const row = document.createElement('tr');
                    const statusClass = entry.outcome === 'success' ? 'text-green-400' : 'text-red-400';
                    const icon = entry.outcome === 'success' 
                        ? '<span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-2 shadow-[0_0_8px_rgba(34,197,94,0.4)]"></span>' 
                        : '<span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2 shadow-[0_0_8px_rgba(239,68,68,0.4)]"></span>';

                    row.innerHTML = `
                        <td class="px-4 py-4 text-gray-300 font-medium text-xs font-mono">${entry.timestamp}</td>
                        <td class="px-4 py-4"><span class="px-2 py-0.5 rounded bg-gray-800 text-gray-400 text-[10px] uppercase font-black tracking-widest border border-gray-700/50">${entry.command}</span></td>
                        <td class="px-4 py-4"><div class="flex items-center ${statusClass} font-black uppercase tracking-tighter text-[10px]">${icon}${entry.outcome}</div></td>
                        <td class="px-4 py-4 text-right">
                            ${entry.log_id ? `
                                <button type="button" class="text-blue-500 hover:text-blue-400 transition-all p-1.5 hover:bg-blue-500/10 rounded-lg active:scale-90" onclick="viewStaticLog('${entry.log_id}', event)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            ` : '<span class="text-gray-700 italic text-[10px]">No log</span>'}
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }
        }

        if (this.historyModal) {
            this.historyModal.classList.remove('hidden');
            this.historyModal.classList.add('flex');
            this.historyModal.offsetHeight;
            this.historyModal.classList.remove('opacity-0', 'scale-95');
            this.historyModal.classList.add('opacity-100', 'scale-100');
            document.body.classList.add('overflow-hidden');
        }
    }

    public closeHistoryModal() {
        if (this.historyModal) {
            this.historyModal.classList.remove('opacity-100', 'scale-100');
            this.historyModal.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                this.historyModal!.classList.add('hidden');
                this.historyModal!.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        }
    }

    public async viewStaticLog(logId: string) {
        this.showModal('Archived Log: ' + logId.split('_')[0]);
        if (this.logStatus) {
            this.logStatus.textContent = 'Archived';
            this.logStatus.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-gray-800 text-gray-500 border border-gray-700 tracking-widest';
        }

        const siteUrl = (window as any).siteUrl || '';
        try {
            const response = await fetch(`${siteUrl}/projects/logs/${logId}?offset=0`);
            const data = await response.json();

            if (data.status === 'success' && this.logOutput) {
                this.logOutput.textContent = '';
                if (data.lines && data.lines.length > 0) {
                    data.lines.forEach((line: string) => {
                        this.logOutput!.textContent += line + '\n';
                    });
                } else {
                    this.logOutput.textContent = 'Log file exists but is empty.\n';
                }
                this.logOutput.textContent += '\n*** Log End ***\n';

                const logContainer = document.getElementById('log-container');
                if (logContainer) {
                    logContainer.scrollTop = 0;
                }
            } else {
                if (this.logOutput) this.logOutput.textContent = 'Error: Log file not found or inaccessible.';
            }
        } catch (err: any) {
            if (this.logOutput) this.logOutput.textContent = 'Failed to load archived log: ' + err.message;
        }
    }

    public async submitEnv(event: Event) {
        event.preventDefault();
        const saveBtn = document.getElementById('env-save-btn') as HTMLButtonElement;
        const projectPath = (document.getElementById('env-project-path') as HTMLInputElement).value;
        const content = (document.getElementById('env-content') as HTMLTextAreaElement).value;

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/env`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify({ project_path: projectPath, content: content })
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                this.closeEnvEditor();
                this.showToast('.env file saved successfully.', 'success');
            } else {
                this.showToast(result.message || 'Failed to save .env file.', 'error');
            }
        } catch (err: any) {
            this.showToast('Failed: ' + (err.message || 'Unknown error'), 'error');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        }
    }

    public async showToast(message: string, type: 'success' | 'error' | 'info' = 'success') {
        if (!this.toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = 'toast-item';
        
        const icon = type === 'success' 
            ? '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>'
            : (type === 'error' 
                ? '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                : '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>');
        
        toast.innerHTML = `${icon}<span class="text-sm font-medium text-gray-100">${message}</span>`;
        this.toastContainer.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('toast-show'), 10);
        
        // Remove after 4 seconds
        setTimeout(() => {
            toast.classList.remove('toast-show');
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }

    public async customConfirm(title: string, message: string, type: 'info' | 'danger' = 'info'): Promise<boolean> {
        return new Promise((resolve) => {
            if (!this.confirmModal) return resolve(false);
            
            const titleEl = document.getElementById('confirm-title');
            const messageEl = document.getElementById('confirm-message');
            const okBtn = document.getElementById('confirm-ok') as HTMLButtonElement;
            const cancelBtn = document.getElementById('confirm-cancel') as HTMLButtonElement;
            const iconContainer = document.getElementById('confirm-icon');
            
            if (titleEl) titleEl.textContent = title;
            if (messageEl) messageEl.textContent = message;
            
            if (okBtn) {
                okBtn.className = type === 'danger' 
                    ? 'flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-sm font-bold transition-all shadow-lg shadow-red-500/20'
                    : 'flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-bold transition-all shadow-lg shadow-blue-500/20';
            }
            
            if (iconContainer) {
                iconContainer.className = type === 'danger'
                    ? 'w-16 h-16 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4'
                    : 'w-16 h-16 bg-blue-500/10 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4';
            }
            
            const cleanup = () => {
                this.confirmModal!.classList.remove('opacity-100', 'scale-100');
                this.confirmModal!.classList.add('opacity-0', 'scale-95');
                setTimeout(() => {
                    this.confirmModal!.classList.add('hidden');
                    this.confirmModal!.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                }, 300);
            };
            
            okBtn.onclick = () => { cleanup(); resolve(true); };
            cancelBtn.onclick = () => { cleanup(); resolve(false); };
            
            this.confirmModal.classList.remove('hidden');
            this.confirmModal.classList.add('flex');
            this.confirmModal.offsetHeight;
            this.confirmModal.classList.remove('opacity-0', 'scale-95');
            this.confirmModal.classList.add('opacity-100', 'scale-100');
            document.body.classList.add('overflow-hidden');
        });
    }

    private stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    private refreshProjectList() {
        const frame = document.getElementById('projects-list') as any;
        if (frame) {
            console.log('Refreshing Turbo Frame: projects-list');
            
            // If the frame has a src, reload it. If not, use the current URL.
            // Appending a small timestamp cache-buster ensures we get fresh data.
            const currentUrl = new URL(frame.src || window.location.href);
            currentUrl.searchParams.set('_t', Date.now().toString());
            
            // Explicitly set src to trigger a fetch
            frame.src = currentUrl.toString();
        } else {
            console.log('Turbo Frame projects-list not found, skipping auto-refresh');
        }
    }

    public async pruneRegistry() {
        const confirmed = await this.customConfirm(
            'Prune Registry?', 
            'Are you sure you want to remove projects with non-existent paths? This cannot be undone.',
            'danger'
        );
        
        if (!confirmed) return;

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/registry/prune`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.refreshProjectList();
            } else {
                this.showToast(data.message || 'Prune failed', 'info');
            }
        } catch (err: any) {
            this.showToast('Failed to prune registry: ' + (err.message || 'Unknown error'), 'error');
        }
    }

    public async copyToClipboard(text: string, event?: Event) {
        if (event) event.preventDefault();
        try {
            await navigator.clipboard.writeText(text);
            this.showToast('URL copied to clipboard!', 'success');
            
            // Visual feedback
            const btn = (event?.currentTarget || event?.target) as HTMLElement;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }

    public async regenerateWebhookToken(encodedPath: string, event?: Event) {
        if (event) event.preventDefault();
        const confirmed = await this.customConfirm(
            'Regenerate Token?',
            'This will break any existing external triggers (e.g. GitHub hooks). Continue?',
            'danger'
        );
        
        if (!confirmed) return;

        const projectPath = atob(encodedPath);
        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/webhook/regenerate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify({ project_path: projectPath })
            });

            const data = await response.json();
            
            if (response.ok && data.status === 'success') {
                this.showToast('Webhook token regenerated.', 'success');
                this.refreshProjectList();
            } else {
                this.showToast(data.message || 'Failed to regenerate token.', 'error');
            }
        } catch (err: any) {
            this.showToast('Error: ' + (err.message || 'Unknown error'), 'error');
        }
    }

    public async validateProject(encodedPath: string) {
        const projectPath = atob(encodedPath);
        this.showModal('Validating ' + projectPath);
        
        const siteUrl = (window as any).siteUrl || '';

        try {
            const response = await fetch(`${siteUrl}/projects/validate?path=${encodeURIComponent(encodedPath)}`);
            const data: ValidationResponse = await response.json();

            if (response.ok && data.status === 'success') {
                if (this.logOutput) {
                    this.logOutput.textContent = 'Configuration Validation Results:\n\n';
                    
                    if (data.results.length === 0) {
                        this.logOutput.textContent += '✅ All checks passed! Configuration is valid.\n';
                    } else {
                        data.results.forEach(res => {
                            const status = res.status.toUpperCase();
                            const icon = status === 'ERROR' ? '❌' : (status === 'WARNING' ? '⚠️' : 'ℹ️');
                            this.logOutput!.textContent += `${icon} [${status}] ${res.rule}\n`;
                            this.logOutput!.textContent += `   Message: ${res.message}\n`;
                            if (res.suggestion) {
                                this.logOutput!.textContent += `   Suggestion: ${res.suggestion}\n`;
                            }
                            this.logOutput!.textContent += '\n';
                        });

                        const hasError = data.results.some(r => r.status.toUpperCase() === 'ERROR');
                        if (hasError) {
                            this.logStatus!.textContent = 'Errors';
                            this.logStatus!.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-red-500/10 text-red-500 border border-red-500/20 tracking-widest';
                            this.logOutput.textContent += '\n❌ Validation found errors. Please fix them before deploying.\n';
                        } else {
                            this.logStatus!.textContent = 'Passed';
                            this.logStatus!.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-green-500/10 text-green-500 border border-green-500/20 tracking-widest';
                            this.logOutput.textContent += '\n✅ Validation passed with some warnings/info.\n';
                        }
                    }
                    
                    this.logOutput.textContent += '\n*** Validation Finished ***\n';
                }
            } else {
                if (this.logOutput) {
                    this.logOutput.textContent = 'Error: ' + (data.message || 'Failed to validate project.');
                }
            }
        } catch (err: any) {
            if (this.logOutput) {
                this.logOutput.textContent = 'Failed: ' + (err.message || 'Unknown error');
            }
        }
    }

    private startPolling(logId: string) {
        this.stopPolling();
        
        const poll = async () => {
            const siteUrl = (window as any).siteUrl || '';
            try {
                const response = await fetch(`${siteUrl}/projects/logs/${logId}?offset=${this.currentOffset}`);
                if (!response.ok) throw new Error('Polling failed');
                
                const data: LogResponse = await response.json();
                
                if (data.status === 'success') {
                    if (this.logOutput && data.lines.length > 0) {
                        data.lines.forEach(line => {
                            this.logOutput!.textContent += line + '\n';
                        });
                        
                        const logContainer = document.getElementById('log-container');
                        if (logContainer) {
                            logContainer.scrollTop = logContainer.scrollHeight;
                        }
                    }
                    
                    this.currentOffset = data.offset;
                    
                    if (data.finished) {
                        if (this.logOutput) {
                            this.logOutput.textContent += '\n*** Execution Finished ***\n';
                        }
                        if (this.logStatus) {
                            this.logStatus.textContent = 'Completed';
                            this.logStatus.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-green-500/10 text-green-500 border border-green-500/20 tracking-widest';
                        }
                        this.stopPolling();
                        
                        // Handle automatic webhook setup if requested
                        if (this.shouldSetupWebhook && this.lastProjectPath) {
                            this.setupGitHubWebhook(this.lastProjectPath);
                            this.shouldSetupWebhook = false;
                        }

                        // We refresh the list but keep the modal open so the user can see the final status
                        console.log('Deployment finished, triggering Turbo Frame refresh...');
                        setTimeout(() => this.refreshProjectList(), 100);
                    }
                }
            } catch (err) {
                console.error('Polling error:', err);
                if (this.logStatus) {
                    this.logStatus.textContent = 'Conn Error';
                    this.logStatus.className = 'px-2 py-0.5 text-[10px] font-black uppercase rounded bg-red-500/10 text-red-500 border border-red-500/20 tracking-widest';
                }
            }
        };

        this.pollingInterval = setInterval(poll, 1000);
        poll();
    }

    private async setupGitHubWebhook(projectPath: string) {
        if (this.logOutput) {
            this.logOutput.textContent += '\n⚙️  Automatically configuring GitHub Webhook...\n';
        }

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/api/github/setup-webhook`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify({ project_path: projectPath })
            });

            const data = await response.json();
            
            if (response.ok && data.status === 'success') {
                if (this.logOutput) {
                    this.logOutput.textContent += '✅ GitHub Webhook configured successfully!\n';
                }
                this.showToast('GitHub Webhook automated.', 'success');
            } else {
                if (this.logOutput) {
                    this.logOutput.textContent += '⚠️  GitHub Webhook Automation: ' + (data.message || 'Unknown error') + '\n';
                }
            }
        } catch (err: any) {
            if (this.logOutput) {
                this.logOutput.textContent += '❌ Failed to automate GitHub Webhook: ' + err.message + '\n';
            }
        }
    }

    public async deployProject(projectPath: string) {
        console.log('DashboardUI.deployProject starting for:', projectPath);
        const confirmed = await this.customConfirm(
            'Ship It?',
            `Are you sure you want to trigger a full deployment for ${projectPath.split('/').pop()}?`,
            'info'
        );

        if (!confirmed) {
            console.log('Deployment cancelled by user');
            return;
        }

        this.showModal('Deploying ' + projectPath);
        
        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/deploy`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify({ project_path: projectPath })
            });

            const data: DeploymentResponse = await response.json();

            if (response.ok && data.status === 'started' && data.log_id) {
                this.startPolling(data.log_id);
            } else {
                if (this.logOutput) {
                    this.logOutput.textContent = 'Error: ' + (data.message || 'Failed to start deployment.');
                }
            }
        } catch (err: any) {
            if (this.logOutput) {
                this.logOutput.textContent = 'Failed: ' + (err.message || 'Unknown error');
            }
        }
    }

    public async rollbackProject(projectPath: string, selectId: string) {
        const selectElement = document.getElementById(selectId) as HTMLSelectElement;
        if (!selectElement) return;

        const backup = selectElement.value;
        if (!backup) {
            this.showToast('Please select a backup.', 'error');
            return;
        }

        const confirmed = await this.customConfirm(
            'Rollback Project?',
            `Are you sure you want to revert ${projectPath.split('/').pop()} to snapshot ${backup}?`,
            'danger'
        );

        if (!confirmed) return;

        this.showModal(`Rolling back ${projectPath} to ${backup}`);

        const siteUrl = (window as any).siteUrl || '';
        const csrfHeader = (window as any).csrfHeader;
        const csrfHash = this.getCsrfHash();

        try {
            const response = await fetch(`${siteUrl}/projects/rollback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [csrfHeader]: csrfHash
                },
                body: JSON.stringify({ project_path: projectPath, backup: backup })
            });

            const data: DeploymentResponse = await response.json();

            if (response.ok && data.status === 'started' && data.log_id) {
                this.startPolling(data.log_id);
            } else {
                if (this.logOutput) {
                    this.logOutput.textContent = 'Error: ' + (data.message || 'Failed to start rollback.');
                }
            }
        } catch (err: any) {
            if (this.logOutput) {
                this.logOutput.textContent = 'Failed: ' + (err.message || 'Unknown error');
            }
        }
    }
}

new DashboardUI();
