<?php
/** @var $this \CodeIgniter\View\View */
?>
<?php $this->extend('layouts/app.layout.php') ?>

<?php $this->section('header') ?>
    <title>Login - ShipIt Control Panel</title>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="min-h-screen flex items-center justify-center bg-gray-950 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
                ShipIt Control Panel
            </h2>
            <p class="mt-2 text-center text-sm text-gray-400">
                Authenticate with your system credentials
            </p>
        </div>
        
        <?php $error = session()->getFlashdata('error'); ?>
        <?php if ($error): ?>
            <div class="bg-red-900/20 border-l-4 border-red-500 p-4 mb-4 shadow-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-200">
                            <?= esc($error) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6 bg-gray-900 p-8 rounded-xl shadow-2xl border border-gray-800" action="<?= site_url('login') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-700 bg-gray-800 placeholder-gray-500 text-white rounded-t-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:z-10 sm:text-sm transition-all" placeholder="Username" value="<?= old('username') ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-700 bg-gray-800 placeholder-gray-500 text-white rounded-b-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:z-10 sm:text-sm transition-all" placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-blue-500 transition-all shadow-lg active:scale-95">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3 text-blue-300">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>
<?php $this->endSection() ?>
