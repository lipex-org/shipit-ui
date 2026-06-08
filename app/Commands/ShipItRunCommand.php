<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use ShipIt\ShipIt;

class ShipItRunCommand extends BaseCommand
{
    protected $group = 'ShipIt';
    protected $name = 'shipit:run';
    protected $description = 'Runs a ShipIt command internally for a specific project.';
    protected $usage = 'shipit:run --project=/path/to/project --command=deploy [--backup=timestamp]';
    protected $options = [
        '--project' => 'The absolute path to the project directory.',
        '--command' => 'The ShipIt command to run (deploy, rollback, validate, etc). Default is deploy.',
        '--backup'  => 'The backup timestamp (only used with rollback command).',
        '--git-url' => 'Git repository URL (used with init command).',
        '--branch'  => 'Git branch name (used with init command).',
        '--user'    => 'System username for project ownership (used with init command).',
        '--force'   => 'Force overwrite existing files (used with init command).',
        '--log-id'  => 'Specific log identifier for tracking history.'
    ];

    public function run(array $params)
    {
        $projectPath = CLI::getOption('project');
        $command = CLI::getOption('command') ?? 'deploy';
        $backup = CLI::getOption('backup');
        $gitUrl = CLI::getOption('git-url');
        $branch = CLI::getOption('branch');
        $user = CLI::getOption('user');
        $force = CLI::getOption('force');
        $logId = CLI::getOption('log-id');

        CLI::write("Running ShipIt command: {$command} for project: {$projectPath}");

        if (empty($projectPath)) {
            CLI::error('Project path is required.');
            return;
        }

        try {
            // Instantiate ShipIt and set root
            $shipit = new ShipIt();
            
            // Allow missing directory if we are initializing
            $shipit->setRoot($projectPath, $command === 'init');

            // Build arguments for the ShipIt::run() method
            $argv = ['shipit', $command];

            if ($command === 'rollback' && $backup) {
                $argv[] = $backup;
            }

            if ($command === 'init') {
                if ($gitUrl) $argv[] = "--git-url={$gitUrl}";
                if ($branch) $argv[] = "--branch={$branch}";
                if ($user)   $argv[] = "--user={$user}";
                if ($force)  $argv[] = "--force";
            }

            if ($logId) {
                $argv[] = "--log-id={$logId}";
            }

            // Always enable detailed logging to capture output
            $argv[] = '--log';

            // Run the command through the class directly
            $shipit->run($argv);

        } catch (\Throwable $e) {
            CLI::error("ShipIt Execution Error: " . $e->getMessage());
            log_message('error', '[ShipItRunCommand] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            exit(1);
        }
    }
}
